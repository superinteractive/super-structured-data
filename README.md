# Super Structured Data

`superinteractive/super-structured-data` provides a small schema runtime for JSON-LD in Laravel applications, with optional Statamic-aware context support.

## Features

- Auto-discovery of schemas from `app/Schemas` (or a configurable schema path).
- Blade component output via `<x-structured-data>`.
- Publishable config and homepage schema stubs.
- `make:schema` generator command.
- Configurable context class and context factory.

## Installation

```bash
composer require superinteractive/super-structured-data
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=structured-data-config
```

## Publish Homepage Schema Stubs

```bash
php artisan vendor:publish --tag=structured-data-homepage-schemas
```

This publishes:

- `app/Schemas/HomepageOrganizationSchema.php`
- `app/Schemas/HomepageWebsiteSchema.php`

## Create a New Schema

Artisan:

```bash
php artisan make:schema Product
```

Statamic please (when Statamic is installed):

```bash
php please make:schema Product
```

The command appends `Schema` automatically, so `Product` generates `ProductSchema`.

## Configuration

Default package config:

```php
return [
    'enabled' => true,
    'schema_path' => 'Schemas',
    'classes' => [
        'class' => class_exists('Statamic\\Statamic')
            ? 'Superinteractive\\StructuredData\\Contexts\\StatamicSchemaContext'
            : 'Superinteractive\\StructuredData\\Contexts\\LaravelSchemaContext',
        'factory' => class_exists('Statamic\\Statamic')
            ? 'Superinteractive\\StructuredData\\Factories\\StatamicSchemaContextFactory'
            : 'Superinteractive\\StructuredData\\Factories\\LaravelSchemaContextFactory',
    ],
];
```

### Override Context Classes

Laravel-only example:

```php
return [
    'classes' => [
        'class' => Superinteractive\StructuredData\Contexts\LaravelSchemaContext::class,
        'factory' => Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory::class,
    ],
];
```

Custom app-specific context example:

```php
return [
    'classes' => [
        'class' => App\StructuredData\CustomSchemaContext::class,
        'factory' => App\StructuredData\CustomSchemaContextFactory::class,
    ],
];
```

Your custom factory must implement `Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract` and return an instance of the configured `classes.class`.

## Writing Schemas

Generated schemas extend `Superinteractive\StructuredData\Schemas\BaseSchema`.

Reference material:
- Spatie schema-org GitHub: [spatie/schema-org](https://github.com/spatie/schema-org)
- Spatie schema-org package docs: [packagist.org/packages/spatie/schema-org](https://packagist.org/packages/spatie/schema-org)
- Schema.org reference types: [schema.org/docs/full.html](https://schema.org/docs/full.html)

Example:

```php
<?php

declare(strict_types=1);

namespace App\Schemas;

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class ProductSchema extends BaseSchema
{
    public function applies(): bool
    {
        return $this->context->routeName() === 'products.show';
    }

    public function scripts(): array
    {
        if (! $this->applies()) {
            return [];
        }

        return [
            Schema::product()->name('Example product')->toScript(),
        ];
    }
}
```

## Rendering

Use the component in Blade:

```blade
<x-structured-data :entry="$entry" />
```

`entry` is optional. In Laravel-only projects, you can omit it.

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
