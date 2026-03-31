# Craft Sidebar Relations

A [Craft CMS](https://craftcms.com/) module that adds nested element sources to the control panel sidebar based on entry relations. Filter a section's entries by their related entries from another section, directly from the sidebar navigation.

## Features

- Adds nested sources under any section in the entries sidebar
- Filter entries by their relations to entries in another section
- Supports custom query conditions on the relation source
- Config-based setup with a fluent API

## Requirements

- PHP 8.2+
- Craft CMS 5

## Installation

```bash
composer require jorisnoo/craft-sidebar-relations
```

Then register the module in your `config/app.php`:

```php
return [
    'modules' => [
        'sidebar-relations' => \Noo\CraftSidebarRelations\SidebarRelations::class,
    ],
    'bootstrap' => ['sidebar-relations'],
];
```

## Configuration

Create a `config/sidebar-relations.php` file in your Craft project:

```php
use Noo\CraftSidebarRelations\config\SidebarRelationsConfig;

return SidebarRelationsConfig::create()
    ->sources([
        [
            'section' => 'articles',
            'relation' => 'categories',
        ],
        [
            'section' => 'projects',
            'relation' => 'clients',
            'where' => [
                'level' => 1,
            ],
        ],
    ]);
```

### Source Options

Each source in the array accepts the following keys:

- `section` — The handle of the section to add nested sources to
- `relation` — The handle of the section whose entries will become the nested source items
- `where` — (optional) Additional query parameters applied to the relation query (e.g. `level`, `status`, `orderBy`)

In the example above, the "Articles" section in the sidebar would get nested items for each entry in the "Categories" section. Clicking a nested item filters the article list to only show articles related to that category.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
