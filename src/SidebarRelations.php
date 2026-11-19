<?php

namespace Noo\CraftSidebarRelations;

use Craft;
use craft\elements\Entry;
use craft\events\RegisterElementSourcesEvent;
use craft\services\ElementSources;
use InvalidArgumentException;
use Noo\CraftSidebarRelations\config\SidebarRelationsConfig;
use Throwable;
use yii\base\Event;
use yii\base\Module;

class SidebarRelations extends Module
{
    private ?array $items = null;

    public function init(): void
    {
        Craft::setAlias('@Noo/CraftSidebarRelations', __DIR__);

        parent::init();

        Craft::$app->onInit(function () {
            Event::on(
                Entry::class,
                Entry::EVENT_REGISTER_SOURCES,
                function (RegisterElementSourcesEvent $event) {
                    $this->addNestedSources($event);
                }
            );
        });
    }

    private function addNestedSources(RegisterElementSourcesEvent $event): void
    {
        if ($event->context !== ElementSources::CONTEXT_INDEX || ! ($user = Craft::$app->getUser()->getIdentity())) {
            return;
        }

        $indices = [];
        foreach ($event->sources as $index => $source) {
            if (isset($source['key'])) {
                $indices[$source['key']] = $index;
            }
        }

        foreach ($this->items ??= $this->loadItems() as $index => $item) {
            try {
                if (! is_array($item) || ! is_string($item['section'] ?? null) || trim($item['section']) === ''
                    || ! is_string($item['relation'] ?? null) || trim($item['relation']) === '') {
                    throw new InvalidArgumentException('Expected non-empty section and relation handles.');
                }
                $entries = Craft::$app->getEntries();
                $section = $entries->getSectionByHandle($item['section']);
                $relation = $entries->getSectionByHandle($item['relation']);
                if (! $section || ! $relation) {
                    throw new InvalidArgumentException('Unknown section or relation handle.');
                }
                $key = 'section:'.$section->uid;
                if (! isset($indices[$key]) || ! $user->can("viewEntries:$relation->uid")) {
                    continue;
                }

                $where = $item['where'] ?? [];
                if (! is_array($where)) {
                    throw new InvalidArgumentException('Expected an array of where criteria.');
                }
                $limit = $item['limit'] ?? $where['limit'] ?? 100;
                unset($where['limit']);
                if (! is_int($limit) || $limit < 1) {
                    throw new InvalidArgumentException('Expected a positive integer limit.');
                }

                $parent = $event->sources[$indices[$key]];
                $siteIds = array_intersect(Craft::$app->getSites()->getEditableSiteIds(), $parent['sites'] ?? $section->getSiteIds(), $relation->getSiteIds());
                $nested = [];
                $seen = array_fill_keys(array_column($parent['nested'] ?? [], 'key'), true);
                $metadata = array_intersect_key($parent, array_flip(['data', 'defaultSort', 'structureId', 'structureEditable']));
                $multiSite = Craft::$app->getIsMultiSite();
                foreach ($siteIds as $siteId) {
                    foreach ($this->relationTitles($relation->id, $siteId, $where, $limit) as $id => $title) {
                        $childKey = 'relatedTo:'.$id.($multiSite ? ':'.$siteId : '');
                        if (isset($seen[$childKey])) {
                            continue;
                        }
                        $criteria = $parent['criteria'];
                        $criteria['relatedTo'] = isset($criteria['relatedTo'])
                            ? Entry::find()->relatedTo($criteria['relatedTo'])->andRelatedTo($id)->relatedTo
                            : $id;
                        $nested[] = [
                            'key' => $childKey,
                            'label' => $title,
                            'sites' => [$siteId],
                            'criteria' => $criteria,
                        ] + $metadata;
                        $seen[$childKey] = true;
                    }
                }
                if ($nested !== []) {
                    $event->sources[$indices[$key]]['nested'] = array_merge($parent['nested'] ?? [], $nested);
                }
            } catch (Throwable $e) {
                Craft::warning("Skipping sidebar-relations source $index: {$e->getMessage()}", __METHOD__);
            }
        }
    }

    private function loadItems(): array
    {
        try {
            $config = Craft::$app->getConfig()->getConfigFromFile('sidebar-relations');
            if ($config instanceof SidebarRelationsConfig) {
                return $config->sources;
            }
            if ($config === []) {
                return [];
            }
            if (! is_array($config) || ! is_array($config['sources'] ?? null)) {
                throw new InvalidArgumentException('Expected SidebarRelationsConfig or an array with a sources list.');
            }

            return $config['sources'];
        } catch (Throwable $e) {
            Craft::warning("Skipping sidebar-relations config: {$e->getMessage()}", __METHOD__);

            return [];
        }
    }

    private function relationTitles(int $sectionId, int $siteId, array $where, int $limit): array
    {
        $query = Entry::find();
        if ($where !== []) {
            $filters = array_flip([...$query->criteriaAttributes(), 'orderBy', 'offset', 'type', 'authorGroup']);
            foreach ($where as $field => $value) {
                if (! isset($filters[$field]) || ! $query->hasMethod($field)) {
                    throw new InvalidArgumentException("Unknown relation query filter: $field.");
                }
                $query->$field($value);
            }
        }

        // Apply the scope after configurable filters; never broaden section, site or visibility.
        return $query->sectionId($sectionId)->siteId($siteId)->editable(true)->limit($limit)
            ->select(['elements.id', 'elements_sites.title'])->cache()->pairs();
    }
}
