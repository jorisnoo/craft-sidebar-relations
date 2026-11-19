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
- `limit` — Maximum number of titles per configured source **per site**, default `100`. Set a positive integer to show more or fewer items. Use `where.orderBy` to choose which items appear first.

In the example above, the "Articles" section in the sidebar would get nested items for each entry in the "Categories" section. Clicking a nested item filters the article list to only show articles related to that category.

The module runs on the full entries index, not in element-picker dialogs. Each site gets its own labels from live entries by default; Craft’s site and section permissions and peer-entry restrictions apply. Children keep the parent section’s filters, New entry menu, sort order, and structure settings. Title queries use Craft’s invalidatable element-query cache.

Several items can target the same section. Their children are appended in configuration order; repeated matches appear only once per site. A plain array with a `sources` key (including Craft’s multi-environment format) also works. Invalid items are skipped with a Craft log warning. Filters cannot override the configured relation section, site scope, or permissions.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
