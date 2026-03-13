# Super Structured Data

`superinteractive/super-structured-data` provides a small schema runtime for JSON-LD in Laravel applications, with optional Statamic-aware context support.

## Features

- Auto-discovers schema classes from `app/Schemas` or a configured schema path.
- Renders JSON-LD with a zero-prop Blade component: `<x-structured-data />`.
- Supports three schema styles: `BaseSchema`, `ModelSchema`, and `StatamicSchema`.
- Ships with optional config publishing and homepage schema stubs.
- Includes `make:schema`, `make:schema --model`, and `make:schema --statamic`.

## Installation

```bash
composer require superinteractive/super-structured-data
```

## Optional Configuration

```bash
php artisan vendor:publish --tag=structured-data-config
```

Publish the config only if you want to disable rendering or move schemas out of `app/Schemas`.

## Publish Homepage Schema Stubs

```bash
php artisan vendor:publish --tag=structured-data-homepage-schemas
```

This publishes:

- `app/Schemas/HomepageOrganizationSchema.php`
- `app/Schemas/HomepageWebsiteSchema.php`

If you changed `structured-data.schema_path`, the stubs publish into that configured schema directory instead.

## Rendering

Render structured data in Blade with no props:

```blade
<x-structured-data />
```

The package resolves the current request context automatically.

## Create a New Schema

Artisan:

```bash
php artisan make:schema Home
php artisan make:schema Product --model
php artisan make:schema Article --statamic
```

Statamic please:

```bash
php please make:schema Home
php please make:schema Product --model
php please make:schema Article --statamic
```

The command appends `Schema` automatically. The `--model` and `--statamic` flags are mutually exclusive.

## Configuration

Default package config:

```php
return [
    'enabled' => true,
    'schema_path' => 'Schemas',
];
```

### Advanced: Override the Context Factory Binding

If you need app-specific context resolution, bind your own factory in a service provider:

```php
$this->app->singleton(
    \Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract::class,
    \App\StructuredData\CustomContextFactory::class,
);
```

Your custom factory must implement `Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract` and return a context implementing `SchemaContextContract`. If you want it to work with `StatamicSchema`, return a context that also implements `StatamicContextContract`.

## Writing Schemas

Reference material:

- Spatie schema-org GitHub: [spatie/schema-org](https://github.com/spatie/schema-org)
- Schema.org reference types: [schema.org/docs/full.html](https://schema.org/docs/full.html)

### BaseSchema

Use `BaseSchema` when route-level metadata is enough.

```php
<?php

declare(strict_types=1);

namespace App\Schemas;

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class HomeSchema extends BaseSchema
{
    public function applies(): bool
    {
        return $this->context->routeName() === 'home';
    }

    public function scripts(): array
    {
        return [
            Schema::webSite()
                ->name(config('app.name'))
                ->url(config('app.url'))
                ->toScript(),
        ];
    }
}
```

### ModelSchema

Use `ModelSchema` when the current route provides a bound model or scalar route parameter.

```php
<?php

declare(strict_types=1);

namespace App\Schemas;

use App\Models\Product;
use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\ModelSchema;

class ProductSchema extends ModelSchema
{
    protected function routeModelKey(): string
    {
        return 'product';
    }

    public function applies(): bool
    {
        return $this->model() instanceof Product;
    }

    public function scripts(): array
    {
        /** @var Product $product */
        $product = $this->model();

        return [
            Schema::product()
                ->name($product->name)
                ->description($product->description)
                ->toScript(),
        ];
    }
}
```

### StatamicSchema

Use `StatamicSchema` when you need auto-resolved Statamic entry or page context.

```php
<?php

declare(strict_types=1);

namespace App\Schemas;

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\StatamicSchema;

class ArticleSchema extends StatamicSchema
{
    public function applies(): bool
    {
        return $this->context->collection() === 'articles';
    }

    public function scripts(): array
    {
        $entry = $this->context->source();

        return [
            Schema::article()
                ->headline($entry->get('title'))
                ->datePublished($entry->get('date'))
                ->toScript(),
        ];
    }
}
```

## Upgrade Notes

- `<x-structured-data />` no longer accepts props; context is fully auto-resolved.
- `SchemaContextContract` now exposes `routeName()`, `url()`, `locale()`, `routeParams()`, and `routeParam()`.
- Statamic-specific access moved to `StatamicContextContract` via `source()`, `collection()`, and `blueprint()`.
- `requestData` was removed from context objects. Read request metadata directly from Laravel's request container when needed.
- `structured-data.classes` config was removed. Override `SchemaContextFactoryContract` in the container for custom context resolution.
- Context classes were renamed from `LaravelSchemaContext` and `StatamicSchemaContext` to `LaravelContext` and `StatamicContext`.

## Testing

Run package tests:

```bash
composer test
```

Format package code:

```bash
vendor/bin/pint
```

## Support Matrix

- PHP: 8.4+
- Laravel: 12+
- Statamic: optional
