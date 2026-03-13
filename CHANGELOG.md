# Changelog

All notable changes to this package will be documented in this file.

## 0.2.0 - 2026-03-13

### Breaking

- `<x-structured-data />` no longer accepts props. Context is now fully auto-resolved from the current request.
- `SchemaContextContract` was slimmed down. `collection()`, `blueprint()`, `entry()`, and `page()` were removed from the base contract.
- `requestData` was removed from context implementations. Use Laravel request APIs directly when request metadata is needed.
- `structured-data.classes` config was removed. Override `SchemaContextFactoryContract` in the container for custom context resolution.
- Context classes were renamed from `LaravelSchemaContext` to `LaravelContext` and from `StatamicSchemaContext` to `StatamicContext`.

### Added

- `ModelSchema` for route-model-bound schemas.
- `StatamicSchema` for Statamic-aware schemas.
- `StatamicContextContract` for Statamic-specific context access.
- `make:schema --model` and `make:schema --statamic` generator flags. They are mutually exclusive.
- Statamic auto-resolution chain: route-bound content, then `Entry::findByUri()`, then `null`.
- `routeParams()` and `routeParam()` on all contexts.

### Changed

- Homepage schema stub publishing now respects `structured-data.schema_path`.

## 0.1.0 - 2026-02-10

- Initial package extraction from app-local structured data runtime.
- Added configurable context class and factory support.
- Added Laravel and Statamic context implementations.
- Added schema auto-discovery from `app/Schemas`.
- Added schema generator command.
