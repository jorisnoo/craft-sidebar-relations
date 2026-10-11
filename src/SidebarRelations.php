<?php

namespace Noo\CraftSidebarRelations;

use Craft;
use craft\elements\Entry;
use craft\events\RegisterElementSourcesEvent;
use Noo\CraftSidebarRelations\config\SidebarRelationsConfig;
use yii\base\Event;
use yii\base\Module;

class SidebarRelations extends Module
{
    private ?SidebarRelationsConfig $config = null;

    private bool $configLoaded = false;

    public function init(): void
    {
        Craft::setAlias('@Noo/CraftSidebarRelations', __DIR__);

        parent::init();

        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_SOURCES,
            function (RegisterElementSourcesEvent $event) {
                $this->addNestedSources($event);
            }
        );
    }

    private function config(): ?SidebarRelationsConfig
    {
        if (!$this->configLoaded) {
            $config = Craft::$app->config->getConfigFromFile('sidebar-relations');
            $this->config = $config instanceof SidebarRelationsConfig ? $config : null;
            $this->configLoaded = true;
        }

        return $this->config;
    }

    private function addNestedSources(RegisterElementSourcesEvent $event): void
    {
        $config = $this->config();

        if ($config === null || empty($config->sources)) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        foreach ($config->sources as $item) {
            $section = Craft::$app->entries->getSectionByHandle($item['section']);
            $relationSection = Craft::$app->entries->getSectionByHandle($item['relation']);

            if (!$section || !$relationSection) {
                continue;
            }

            // Don't reveal relation entry titles to users who can't view that section.
            if ($user && !$user->can('viewEntries:' . $relationSection->uid)) {
                continue;
            }

            $sourceKey = 'section:' . $section->uid;

            foreach ($event->sources as &$source) {
                if (($source['key'] ?? null) !== $sourceKey) {
                    continue;
                }

                $nested = [];

                foreach ($this->relationTitles($relationSection->handle, $item['where'] ?? []) as $id => $title) {
                    $nested[] = [
                        // Scoped by the parent section, so sections sharing a relation section don't collide.
                        'key' => 'relatedTo:' . $section->uid . ':' . $id,
                        'label' => $title,
                        'criteria' => [
                            'sectionId' => $section->id,
                            'relatedTo' => $id,
                        ],
                    ];
                }

                $source['nested'] = $nested;
                break;
            }
            unset($source);
        }
    }

    /**
     * Titles of the relation section's entries, keyed by ID. Only these two
     * columns are fetched, and the result is query-cached with Craft's element
     * cache tags, so it is invalidated when an entry in that section changes.
     *
     * @return array<int, string>
     */
    private function relationTitles(string $sectionHandle, array $where): array
    {
        $query = Entry::find()->section($sectionHandle);

        foreach ($where as $field => $value) {
            $query->$field($value);
        }

        return $query
            ->select(['elements.id', 'elements_sites.title'])
            ->cache()
            ->pairs();
    }
}
