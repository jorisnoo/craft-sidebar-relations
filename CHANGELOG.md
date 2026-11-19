# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-11-19

- Preserve the parent section’s permissions, filters, New entry action, sorting and structure settings when selecting nested entries.
- Show site-specific relation titles only from sections and entries the current user can view. Children from multiple rules or other extensions are no longer overwritten.
- Limit each rule to 100 titles per site by default (adjust `limit` if you need more). Cache title-only queries, and skip element-picker dialogs.
- Accept array and environment-specific configuration alongside the fluent API. Invalid sources are logged and skipped without breaking the entries index.

## [1.0.0](https://github.com/jorisnoo/craft-sidebar-relations/releases/tag/1.0.0) (2026-05-12)

### Features

- add SidebarRelationsConfig class for type-safe configuration ([69d83c7](https://github.com/jorisnoo/craft-sidebar-relations/commit/69d83c76e206a10de7d82e94561b375eadee3284))

### Continuous Integration

- simplify dependabot auto-merge workflow ([63a24ac](https://github.com/jorisnoo/craft-sidebar-relations/commit/63a24ac635e06bde4274fb6461ac2a66ab8b61fd))

### Chores

- add project configuration and documentation files ([f7c1c5a](https://github.com/jorisnoo/craft-sidebar-relations/commit/f7c1c5a26c8c67395d101d5a1dfdeca5414590e9))
