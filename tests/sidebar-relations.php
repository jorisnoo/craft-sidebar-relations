<?php

use Noo\CraftSidebarRelations\config\SidebarRelationsConfig;

$categories = ['section' => 'articles', 'relation' => 'categories', 'where' => ['orderBy' => 'title asc'], 'limit' => 1];
$sources = [
    ['section' => 'articles'],
    ['relation' => 'categories'],
    ['section' => 'unknown', 'relation' => 'categories'],
    ['section' => 'articles', 'relation' => 'unknown'],
    ['section' => 'articles', 'relation' => 'categories', 'where' => 'invalid'],
    ['section' => 'articles', 'relation' => 'categories', 'where' => ['notAFilter' => 1]],
    ['section' => 'articles', 'relation' => 'categories', 'limit' => 0],
    $categories,
    $categories,
    ['section' => 'articles', 'relation' => 'authors'],
];

return match (getenv('SIDEBAR_CONFIG')) {
    'invalid' => ['sources' => false],
    'typed' => SidebarRelationsConfig::create()->sources($sources),
    default => ['*' => ['sources' => []], 'testing' => ['sources' => $sources]],
};
