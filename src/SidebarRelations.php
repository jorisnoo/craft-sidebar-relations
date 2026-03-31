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
        $config = Craft::$app->config->getConfigFromFile('sidebar-relations');

        if (!$config instanceof SidebarRelationsConfig || empty($config->sources)) {
            return;
        }

        foreach ($config->sources as $item) {
            $section = Craft::$app->entries->getSectionByHandle($item['section']);

            if (!$section) {
                continue;
            }

            $sourceKey = 'section:' . $section->uid;

            foreach ($event->sources as &$source) {
                if (($source['key'] ?? null) !== $sourceKey) {
                    continue;
                }

                $query = Entry::find()->section($item['relation']);

                if (!empty($item['where'])) {
                    foreach ($item['where'] as $field => $value) {
                        $query->$field($value);
                    }
                }

                $entries = $query->all();
                $nested = [];

                foreach ($entries as $entry) {
                    $nested[] = [
                        'key' => 'relatedTo:' . $entry->id,
                        'label' => $entry->title,
                        'criteria' => [
                            'sectionId' => $section->id,
                            'relatedTo' => $entry->id,
                        ],
                    ];
                }

                $source['nested'] = $nested;
                break;
            }
            unset($source);
        }
    }
}
