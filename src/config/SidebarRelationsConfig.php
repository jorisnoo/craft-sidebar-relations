<?php

namespace Noo\CraftSidebarRelations\config;

use craft\config\BaseConfig;

class SidebarRelationsConfig extends BaseConfig
{
    public array $sources = [];

    public function sources(array $sources): self
    {
        $this->sources = $sources;

        return $this;
    }
}
