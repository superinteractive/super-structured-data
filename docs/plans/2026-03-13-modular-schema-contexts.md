# Modular Schema Contexts Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the monolithic `SchemaContextContract` with a modular context hierarchy where schemas declare their context needs (`BaseSchema`, `ModelSchema`, or `StatamicSchema`), eliminating unused nullable fields and enabling fully auto-resolved `<x-structured-data />` with zero props.

**Architecture:** Three-tier schema system: `BaseSchema` uses `SchemaContextContract` (route, URL, locale, routeParams). `ModelSchema extends BaseSchema` adds a `model()` helper that reads a declared route parameter — model resolution is per-schema, not per-context, so no shared mutable state. `StatamicSchema extends BaseSchema` narrows its context to `StatamicContextContract` (adds source, collection, blueprint). Factories auto-resolve everything from the current request. The runner checks each schema's `contextType()` before instantiation and skips incompatible schemas.

**Tech Stack:** PHP 8.4, Laravel 12, Blade components, Orchestra Testbench/Pest, optional Statamic APIs, Spatie schema-org

**Security model:**
- Contexts are `final readonly` — immutable, no side effects between schemas.
- `ModelSchema::model()` is cached on the schema instance (private property), not shared context. One schema's resolution cannot leak to another.
- Schema authors must guard with `applies()` (e.g., `$this->model() instanceof Product`) — wrong route means null model, schema skipped.
- `SchemaRunner::sanitizeScript()` escapes `</script>` breakout attempts in JSON-LD output (existing, unchanged).
- No auto-serialization of models into output — schema authors explicitly pick fields via Spatie's typed builder.

---

## File Map

### New files
| File | Responsibility |
|------|---------------|
| `src/Contracts/StatamicContextContract.php` | Statamic-specific context interface extending base |
| `src/Contexts/LaravelContext.php` | Readonly context: route, url, locale, routeParams |
| `src/Contexts/StatamicContext.php` | Readonly context: adds source, collection, blueprint |
| `src/Schemas/ModelSchema.php` | Abstract base for route-model-bound schemas |
| `src/Schemas/StatamicSchema.php` | Abstract base for Statamic schemas |
| `stubs/ModelSchema.stub` | Generator stub for `--model` flag |
| `stubs/StatamicSchema.stub` | Generator stub for `--statamic` flag |
| `tests/Unit/LaravelContextTest.php` | Tests for LaravelContext value object |
| `tests/Unit/ModelSchemaTest.php` | Tests for ModelSchema model resolution |
| `tests/Unit/StatamicSchemaTest.php` | Tests for StatamicSchema context contract requirements |
| `tests/Unit/LaravelContextFactoryTest.php` | Tests for factory auto-resolution of route params |

### Modified files
| File | Change |
|------|--------|
| `src/Contracts/SchemaContextContract.php` | Remove `collection`, `blueprint`, `entry`, `page`; add `routeParams`, `routeParam` |
| `src/Contracts/SchemaContextFactoryContract.php` | Remove `$entry` parameter from `make()` |
| `src/Schemas/BaseSchema.php` | Add static `contextType()` method |
| `src/Factories/LaravelSchemaContextFactory.php` | Remove `$entry` param, auto-populate `routeParams`, return `LaravelContext` |
| `src/Factories/StatamicSchemaContextFactory.php` | Remove `$entry` param, add auto-resolution chain, return `StatamicContext` |
| `src/Support/ContextFactoryResolver.php` | Remove `$entry` param from `make()`, update default class references |
| `src/Support/SchemaRunner.php` | Add `contextType()` guard before schema instantiation |
| `src/View/Components/StructuredData.php` | Remove all props (`$entry`, `$breadcrumbs`) |
| `src/Commands/MakeSchemaCommand.php` | Add `--model` and `--statamic` option flags |
| `config/structured-data.php` | Rename `classes.class` → `classes.context`, update class references |
| `stubs/Schema.stub` | No change needed (already extends BaseSchema) |
| `stubs/HomepageOrganizationSchema.php.stub` | Extend `StatamicSchema` instead of `BaseSchema` |
| `stubs/HomepageWebsiteSchema.php.stub` | Extend `StatamicSchema` instead of `BaseSchema` |
| `tests/Unit/SchemaRunnerTest.php` | Update context construction (new `LaravelContext` constructor) |
| `tests/Unit/ContextFactoryResolverTest.php` | Update class references and remove `make()` argument |
| `tests/Feature/MakeSchemaCommandTest.php` | Add tests for `--model` and `--statamic` flags |
| `README.md` | Rewrite with new schema patterns |
| `CHANGELOG.md` | Document breaking changes |

### Deleted files
| File | Reason |
|------|--------|
| `src/Contexts/LaravelSchemaContext.php` | Replaced by `LaravelContext.php`; delete only after Tasks 3-5 migrate all references |
| `src/Contexts/StatamicSchemaContext.php` | Replaced by `StatamicContext.php`; delete only after Tasks 3-5 migrate all references |

---

## Chunk 1: Context Hierarchy and Schema Base Classes

### Task 1: Create slimmed-down contracts and new context implementations

**Files:**
- Modify: `src/Contracts/SchemaContextContract.php`
- Create: `src/Contracts/StatamicContextContract.php`
- Create: `src/Contexts/LaravelContext.php`
- Create: `src/Contexts/StatamicContext.php`
- Create: `tests/Unit/LaravelContextTest.php`

- [ ] **Step 1: Write tests for LaravelContext**

Create `tests/Unit/LaravelContextTest.php`:

```php
<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;

it('implements SchemaContextContract', function (): void {
    $context = new LaravelContext(
        routeName: 'home',
        url: 'https://example.test',
        locale: 'en',
    );

    expect($context)->toBeInstanceOf(SchemaContextContract::class);
});

it('exposes route, url, locale, and route params', function (): void {
    $context = new LaravelContext(
        routeName: 'products.show',
        url: 'https://example.test/products/42',
        locale: 'en',
        routeParams: ['product' => '42'],
    );

    expect($context->routeName())->toBe('products.show')
        ->and($context->url())->toBe('https://example.test/products/42')
        ->and($context->locale())->toBe('en')
        ->and($context->routeParams())->toBe(['product' => '42'])
        ->and($context->routeParam('product'))->toBe('42')
        ->and($context->routeParam('missing', 'fallback'))->toBe('fallback');
});

it('defaults routeParams to empty array', function (): void {
    $context = new LaravelContext(
        routeName: null,
        url: 'https://example.test',
        locale: null,
    );

    expect($context->routeParams())->toBe([])
        ->and($context->routeParam('anything'))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/LaravelContextTest.php -v`
Expected: FAIL — class `LaravelContext` does not exist

- [ ] **Step 3: Update SchemaContextContract**

Replace `src/Contracts/SchemaContextContract.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface SchemaContextContract
{
    public function routeName(): ?string;

    public function url(): string;

    public function locale(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function routeParams(): array;

    public function routeParam(string $key, mixed $default = null): mixed;
}
```

- [ ] **Step 4: Create LaravelContext**

Create `src/Contexts/LaravelContext.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contexts;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;

final readonly class LaravelContext implements SchemaContextContract
{
    /**
     * @param  array<string, mixed>  $routeParams
     */
    public function __construct(
        public ?string $routeName,
        public string $url,
        public ?string $locale,
        public array $routeParams = [],
    ) {}

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/LaravelContextTest.php -v`
Expected: PASS

- [ ] **Step 6: Create StatamicContextContract**

Create `src/Contracts/StatamicContextContract.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface StatamicContextContract extends SchemaContextContract
{
    /**
     * The auto-resolved Statamic content item (Entry or Page).
     *
     * @return \Statamic\Contracts\Entries\Entry|\Statamic\Structures\Page|null
     */
    public function source(): mixed;

    public function collection(): ?string;

    public function blueprint(): ?string;
}
```

Note: `source()` returns `mixed` as the PHP type to avoid importing Statamic classes into the contract (which would crash in non-Statamic environments when the autoloader loads this interface). The `@return` PHPDoc gives IDEs the real type.

- [ ] **Step 7: Create StatamicContext**

Create `src/Contexts/StatamicContext.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contexts;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Structures\Page;
use Superinteractive\StructuredData\Contracts\StatamicContextContract;

final readonly class StatamicContext implements StatamicContextContract
{
    /**
     * @param  array<string, mixed>  $routeParams
     */
    public function __construct(
        public ?string $routeName,
        public string $url,
        public ?string $locale,
        public array $routeParams = [],
        public EntryContract|Page|null $source = null,
        public ?string $collection = null,
        public ?string $blueprint = null,
    ) {}

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function source(): EntryContract|Page|null
    {
        return $this->source;
    }

    public function collection(): ?string
    {
        return $this->collection;
    }

    public function blueprint(): ?string
    {
        return $this->blueprint;
    }
}
```

- [ ] **Step 8: Keep legacy context files in place for this commit**

Do not delete `src/Contexts/LaravelSchemaContext.php` or `src/Contexts/StatamicSchemaContext.php` in this task.

Reason: factories, tests, and config still reference them until Tasks 3-5. Deleting them here would leave the codebase broken between commits.

- [ ] **Step 9: Commit**

```bash
git add src/Contracts/ src/Contexts/ tests/Unit/LaravelContextTest.php
git commit -m "feat: replace monolithic context with modular hierarchy

- SchemaContextContract slimmed to: routeName, url, locale, routeParams
- StatamicContextContract extends base with: source, collection, blueprint
- LaravelContext: readonly value object for Laravel-only schemas
- StatamicContext: readonly value object for Statamic schemas
- Removed collection/blueprint/entry/page from base contract
- Legacy context classes retained temporarily until all references are migrated

BREAKING CHANGE: SchemaContextContract interface changed"
```

---

### Task 2: Create schema base class hierarchy

**Files:**
- Modify: `src/Schemas/BaseSchema.php`
- Create: `src/Schemas/ModelSchema.php`
- Create: `src/Schemas/StatamicSchema.php`
- Create: `tests/Unit/ModelSchemaTest.php`
- Create: `tests/Unit/StatamicSchemaTest.php`

- [ ] **Step 1: Write tests for ModelSchema**

Create `tests/Unit/ModelSchemaTest.php`:

```php
<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Schemas\BaseSchema;
use Superinteractive\StructuredData\Schemas\ModelSchema;

it('resolves model from route param via routeModelKey', function (): void {
    $fakeProduct = new stdClass;
    $fakeProduct->name = 'Widget';

    $context = new LaravelContext(
        routeName: 'products.show',
        url: 'https://example.test/products/1',
        locale: 'en',
        routeParams: ['product' => $fakeProduct],
    );

    $schema = new class($context) extends ModelSchema
    {
        protected function routeModelKey(): string
        {
            return 'product';
        }

        public function applies(): bool
        {
            return $this->model() instanceof stdClass;
        }

        public function scripts(): array
        {
            return ['<script type="application/ld+json">{"name":"'.$this->model()->name.'"}</script>'];
        }
    };

    expect($schema->applies())->toBeTrue()
        ->and($schema->scripts()[0])->toContain('"name":"Widget"');
});

it('returns null model when route param is missing', function (): void {
    $context = new LaravelContext(
        routeName: 'home',
        url: 'https://example.test',
        locale: 'en',
    );

    $schema = new class($context) extends ModelSchema
    {
        protected function routeModelKey(): string
        {
            return 'product';
        }

        public function applies(): bool
        {
            return $this->model() instanceof stdClass;
        }

        public function scripts(): array
        {
            return [];
        }
    };

    expect($schema->applies())->toBeFalse()
        ->and($schema->model())->toBeNull();
});

it('caches model resolution across multiple calls', function (): void {
    $callCount = 0;
    $context = Mockery::mock(SchemaContextContract::class);
    $context->shouldReceive('routeParam')
        ->with('item', null)
        ->once()
        ->andReturn('resolved-value');

    $schema = new class($context) extends ModelSchema
    {
        protected function routeModelKey(): string
        {
            return 'item';
        }

        public function applies(): bool
        {
            return true;
        }

        public function scripts(): array
        {
            return [];
        }
    };

    // Call model() three times — routeParam should only be called once
    $schema->model();
    $schema->model();
    $result = $schema->model();

    expect($result)->toBe('resolved-value');
});

it('exposes contextType as SchemaContextContract for BaseSchema', function (): void {
    expect(BaseSchema::contextType())->toBe(SchemaContextContract::class);
});

it('exposes contextType as SchemaContextContract for ModelSchema', function (): void {
    expect(ModelSchema::contextType())->toBe(SchemaContextContract::class);
});
```

- [ ] **Step 2: Write tests for StatamicSchema**

Create `tests/Unit/StatamicSchemaTest.php`:

```php
<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contracts\StatamicContextContract;
use Superinteractive\StructuredData\Schemas\StatamicSchema;

it('exposes contextType as StatamicContextContract for StatamicSchema', function (): void {
    expect(StatamicSchema::contextType())->toBe(StatamicContextContract::class);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/ModelSchemaTest.php tests/Unit/StatamicSchemaTest.php -v`
Expected: FAIL — `ModelSchema` and `StatamicSchema` do not exist yet

- [ ] **Step 4: Update BaseSchema with contextType()**

Replace `src/Schemas/BaseSchema.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;

/**
 * Base class for all Structured Data schema definitions.
 *
 * Extend this class directly for schemas that only need route-level
 * context (routeName, url, locale, routeParams). For schemas that
 * need a route-bound model, extend ModelSchema instead. For Statamic
 * schemas, extend StatamicSchema.
 */
abstract class BaseSchema
{
    public function __construct(
        protected readonly SchemaContextContract $context,
    ) {}

    /**
     * @return class-string<SchemaContextContract>
     */
    public static function contextType(): string
    {
        return SchemaContextContract::class;
    }

    abstract public function applies(): bool;

    /**
     * @return array<int, string>
     */
    abstract public function scripts(): array;
}
```

- [ ] **Step 5: Create ModelSchema**

Create `src/Schemas/ModelSchema.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

/**
 * Base class for schemas that resolve a model from a route parameter.
 *
 * Define routeModelKey() to declare which route parameter holds the model.
 * Use model() to access the resolved value. For routes with implicit model
 * binding, model() returns the bound Eloquent model. For scalar route
 * parameters, model() returns the raw value.
 *
 * Always guard with an instanceof check in applies():
 *
 *     public function applies(): bool
 *     {
 *         return $this->model() instanceof Product;
 *     }
 */
abstract class ModelSchema extends BaseSchema
{
    private mixed $resolvedModel = null;

    private bool $modelResolved = false;

    /**
     * The route parameter name that holds the model.
     *
     * Must match the route definition, e.g., 'product' for /products/{product}.
     */
    abstract protected function routeModelKey(): string;

    /**
     * The resolved model from the route parameter.
     *
     * Cached after first resolution — safe to call multiple times.
     */
    public function model(): mixed
    {
        if (! $this->modelResolved) {
            $this->resolvedModel = $this->context->routeParam($this->routeModelKey());
            $this->modelResolved = true;
        }

        return $this->resolvedModel;
    }
}
```

Note: uses a `$modelResolved` flag instead of null-coalescing so that a legitimately `null` route param is cached (not re-queried). The `model()` method is public so tests can call it directly, but schema authors primarily use it inside `applies()` and `scripts()`.

- [ ] **Step 6: Create StatamicSchema**

Create `src/Schemas/StatamicSchema.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

use Superinteractive\StructuredData\Contracts\StatamicContextContract;

/**
 * Base class for Statamic schemas.
 *
 * Provides access to the auto-resolved Statamic source (Entry or Page),
 * plus collection and blueprint. Schemas extending this class are
 * automatically skipped in non-Statamic environments.
 */
abstract class StatamicSchema extends BaseSchema
{
    public function __construct(
        protected readonly StatamicContextContract $context,
    ) {
        parent::__construct($context);
    }

    /**
     * @return class-string<StatamicContextContract>
     */
    public static function contextType(): string
    {
        return StatamicContextContract::class;
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/ModelSchemaTest.php tests/Unit/StatamicSchemaTest.php -v`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Schemas/ tests/Unit/ModelSchemaTest.php tests/Unit/StatamicSchemaTest.php
git commit -m "feat: add ModelSchema and StatamicSchema base classes

- BaseSchema.contextType() for runner compatibility checks
- ModelSchema: per-schema model resolution from route params, cached
- StatamicSchema: narrows context to StatamicContextContract
- ModelSchema.model() is public for testability, cached with flag"
```

---

## Chunk 2: Factories, Runner, and Component

### Task 3: Update factories and resolver for auto-resolution

**Files:**
- Modify: `src/Contracts/SchemaContextFactoryContract.php`
- Modify: `src/Factories/LaravelSchemaContextFactory.php`
- Modify: `src/Factories/StatamicSchemaContextFactory.php`
- Modify: `src/Support/ContextFactoryResolver.php`
- Create: `tests/Unit/LaravelContextFactoryTest.php`

- [ ] **Step 1: Write tests for the Laravel factory**

Create `tests/Unit/LaravelContextFactoryTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;

it('creates a LaravelContext with route name and url', function (): void {
    Route::get('/about', fn () => 'ok')->name('about');

    $request = Request::create('/about');
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $context = app(LaravelSchemaContextFactory::class)->make();

    expect($context)->toBeInstanceOf(LaravelContext::class)
        ->and($context->routeName())->toBe('about')
        ->and($context->routeParams())->toBe([]);
});

it('captures scalar route parameters', function (): void {
    Route::get('/story/{id}', fn () => 'ok')->name('story');

    $request = Request::create('/story/42');
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $context = app(LaravelSchemaContextFactory::class)->make();

    expect($context->routeName())->toBe('story')
        ->and($context->routeParam('id'))->toBe('42');
});

it('captures bound model route parameters', function (): void {
    $fakeModel = new stdClass;
    $fakeModel->name = 'Test Product';

    Route::get('/products/{product}', fn () => 'ok')->name('products.show');

    $request = Request::create('/products/1');
    $route = Route::getRoutes()->match($request);
    $route->setParameter('product', $fakeModel);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $context = app(LaravelSchemaContextFactory::class)->make();

    expect($context->routeParam('product'))->toBe($fakeModel)
        ->and($context->routeParam('product')->name)->toBe('Test Product');
});

it('handles requests with no route', function (): void {
    $request = Request::create('/not-found');
    app()->instance('request', $request);

    $context = app(LaravelSchemaContextFactory::class)->make();

    expect($context->routeName())->toBeNull()
        ->and($context->routeParams())->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/LaravelContextFactoryTest.php -v`
Expected: FAIL — factory still returns old `LaravelSchemaContext`

- [ ] **Step 3: Update SchemaContextFactoryContract**

Replace `src/Contracts/SchemaContextFactoryContract.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface SchemaContextFactoryContract
{
    public function make(): SchemaContextContract;
}
```

- [ ] **Step 4: Rewrite LaravelSchemaContextFactory**

Replace `src/Factories/LaravelSchemaContextFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Factories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;

class LaravelSchemaContextFactory implements SchemaContextFactoryContract
{
    public function make(): SchemaContextContract
    {
        $request = app(Request::class);
        $route = $request->route();

        $routeName = $route?->getName();

        if (! is_string($routeName) || $routeName === '') {
            $routeName = null;
        }

        $url = URL::current();

        if (! is_string($url) || $url === '') {
            $url = URL::to('/');
        }

        $locale = app()->getLocale();

        if ($locale === '') {
            $locale = null;
        }

        return new LaravelContext(
            routeName: $routeName,
            url: $url,
            locale: $locale,
            routeParams: $route?->parameters() ?? [],
        );
    }
}
```

- [ ] **Step 5: Rewrite StatamicSchemaContextFactory**

Replace `src/Factories/StatamicSchemaContextFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Factories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Structures\Page;
use Superinteractive\StructuredData\Contexts\StatamicContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;

class StatamicSchemaContextFactory implements SchemaContextFactoryContract
{
    public function make(): SchemaContextContract
    {
        $request = app(Request::class);
        $route = $request->route();
        $source = $this->resolveSource($request);
        $entry = $this->resolveEntry($source);

        return new StatamicContext(
            routeName: $this->resolveRouteName($route),
            url: $this->resolveUrl($source, $entry),
            locale: $this->resolveLocale($entry, $source),
            routeParams: $route?->parameters() ?? [],
            source: $source,
            collection: $this->resolveCollection($entry, $source),
            blueprint: $this->resolveBlueprint($entry, $source),
        );
    }

    /**
     * Auto-resolution chain:
     * 1. Route-bound Entry or Page (from implicit model binding)
     * 2. Entry::findByUri() using current request URI
     * 3. null (non-content routes)
     */
    private function resolveSource(Request $request): EntryContract|Page|null
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Page || $parameter instanceof EntryContract) {
                return $parameter;
            }
        }

        $uri = '/'.ltrim($request->path(), '/');
        $site = Site::current()?->handle();
        $entry = EntryFacade::findByUri($uri === '//' ? '/' : $uri, $site);

        return $entry instanceof EntryContract ? $entry : null;
    }

    private function resolveEntry(EntryContract|Page|null $source): ?EntryContract
    {
        if ($source instanceof Page) {
            $entry = rescue(fn (): mixed => $source->entry(), report: false);

            return $entry instanceof EntryContract ? $entry : null;
        }

        return $source instanceof EntryContract ? $source : null;
    }

    private function resolveRouteName(mixed $route): ?string
    {
        $name = $route?->getName();

        if (! is_string($name) || $name === '') {
            return null;
        }

        return $name;
    }

    private function resolveUrl(EntryContract|Page|null $source, ?EntryContract $entry): string
    {
        if ($source instanceof EntryContract) {
            $url = $this->resolveAbsoluteUrl($source);

            if ($url !== null) {
                return $url;
            }
        }

        if ($entry instanceof EntryContract) {
            $url = $this->resolveAbsoluteUrl($entry);

            if ($url !== null) {
                return $url;
            }
        }

        $url = URL::current();

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return URL::to('/');
    }

    private function resolveAbsoluteUrl(EntryContract $entry): ?string
    {
        if (! method_exists($entry, 'absoluteUrl')) {
            return null;
        }

        $url = $entry->absoluteUrl();

        if (! is_string($url) || $url === '') {
            return null;
        }

        return $url;
    }

    private function resolveLocale(?EntryContract $entry, EntryContract|Page|null $source): ?string
    {
        if ($entry instanceof EntryContract && method_exists($entry, 'locale')) {
            $locale = $entry->locale();

            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        if ($source instanceof Page) {
            $site = rescue(fn (): mixed => $source->site(), report: false);
            $siteHandle = is_object($site) && method_exists($site, 'handle') ? $site->handle() : null;

            if (is_string($siteHandle) && $siteHandle !== '') {
                return $siteHandle;
            }
        }

        $siteLocale = Site::current()?->locale();

        if (is_string($siteLocale) && $siteLocale !== '') {
            return $siteLocale;
        }

        $appLocale = app()->getLocale();

        if ($appLocale === '') {
            return null;
        }

        return $appLocale;
    }

    private function resolveCollection(?EntryContract $entry, EntryContract|Page|null $source): ?string
    {
        if ($entry instanceof EntryContract) {
            return $this->resolveCollectionHandle($entry);
        }

        if ($source instanceof Page) {
            $routeData = rescue(fn (): mixed => $source->routeData(), report: false);

            if (is_array($routeData)) {
                $collection = data_get($routeData, 'collection');

                if (is_string($collection) && $collection !== '') {
                    return $collection;
                }
            }

            $collection = rescue(fn (): mixed => $source->collection(), report: false);

            if (is_object($collection) && method_exists($collection, 'handle')) {
                $handle = $collection->handle();

                if (is_string($handle) && $handle !== '') {
                    return $handle;
                }
            }
        }

        return null;
    }

    private function resolveBlueprint(?EntryContract $entry, EntryContract|Page|null $source): ?string
    {
        if ($entry instanceof EntryContract) {
            return $this->resolveBlueprintHandle($entry);
        }

        if ($source instanceof Page) {
            $routeData = rescue(fn (): mixed => $source->routeData(), report: false);

            if (is_array($routeData)) {
                $blueprint = data_get($routeData, 'blueprint');

                if (is_string($blueprint) && $blueprint !== '') {
                    return $blueprint;
                }
            }

            $blueprint = rescue(fn (): mixed => $source->blueprint(), report: false);

            if (is_object($blueprint) && method_exists($blueprint, 'handle')) {
                $handle = $blueprint->handle();

                if (is_string($handle) && $handle !== '') {
                    return $handle;
                }
            }
        }

        return null;
    }

    private function resolveCollectionHandle(EntryContract $entry): ?string
    {
        $collectionHandle = null;

        if (method_exists($entry, 'collectionHandle')) {
            $collectionHandle = $entry->collectionHandle();
        }

        if (! is_string($collectionHandle) || $collectionHandle === '') {
            $collection = method_exists($entry, 'collection') ? $entry->collection() : data_get($entry, 'collection');

            $collectionHandle = is_object($collection) && method_exists($collection, 'handle')
                ? $collection->handle()
                : $collection;
        }

        if (! is_string($collectionHandle) || $collectionHandle === '') {
            return null;
        }

        return $collectionHandle;
    }

    private function resolveBlueprintHandle(EntryContract $entry): ?string
    {
        $blueprint = rescue(fn (): mixed => $entry->blueprint(), report: false);

        if (is_object($blueprint) && method_exists($blueprint, 'handle')) {
            $handle = $blueprint->handle();

            if (is_string($handle) && $handle !== '') {
                return $handle;
            }
        }

        $blueprintHandle = data_get($entry, 'blueprint');

        if (! is_string($blueprintHandle) || $blueprintHandle === '') {
            return null;
        }

        return $blueprintHandle;
    }
}
```

- [ ] **Step 6: Update ContextFactoryResolver**

Replace `src/Support/ContextFactoryResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Support;

use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Contexts\StatamicContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\Factories\StatamicSchemaContextFactory;

class ContextFactoryResolver
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(): SchemaContextContract
    {
        $context = $this->resolveFactory()->make();
        $expectedClass = $this->resolveContextClass();

        if (! $context instanceof $expectedClass) {
            throw new RuntimeException(sprintf(
                'Configured classes.context [%s] does not match resolved context instance [%s].',
                $expectedClass,
                $context::class,
            ));
        }

        return $context;
    }

    public function resolveFactory(): SchemaContextFactoryContract
    {
        $factoryClass = config('structured-data.classes.factory', $this->defaultFactoryClass());

        if (! is_string($factoryClass) || $factoryClass === '') {
            throw new RuntimeException('Invalid structured-data.classes.factory configuration.');
        }

        $factory = $this->container->make($factoryClass);

        if (! $factory instanceof SchemaContextFactoryContract) {
            throw new RuntimeException(sprintf(
                'Configured classes.factory [%s] must implement [%s].',
                $factoryClass,
                SchemaContextFactoryContract::class,
            ));
        }

        return $factory;
    }

    /**
     * @return class-string<SchemaContextContract>
     */
    public function resolveContextClass(): string
    {
        $contextClass = config('structured-data.classes.context', $this->defaultContextClass());

        if (! is_string($contextClass) || $contextClass === '') {
            throw new RuntimeException('Invalid structured-data.classes.context configuration.');
        }

        if (! is_subclass_of($contextClass, SchemaContextContract::class)) {
            throw new RuntimeException(sprintf(
                'Configured classes.context [%s] must implement [%s].',
                $contextClass,
                SchemaContextContract::class,
            ));
        }

        return $contextClass;
    }

    /**
     * @return class-string<SchemaContextContract>
     */
    private function defaultContextClass(): string
    {
        if (class_exists('Statamic\\Statamic')) {
            return StatamicContext::class;
        }

        return LaravelContext::class;
    }

    /**
     * @return class-string<SchemaContextFactoryContract>
     */
    private function defaultFactoryClass(): string
    {
        if (class_exists('Statamic\\Statamic')) {
            return StatamicSchemaContextFactory::class;
        }

        return LaravelSchemaContextFactory::class;
    }
}
```

- [ ] **Step 7: Run factory tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/LaravelContextFactoryTest.php -v`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Contracts/SchemaContextFactoryContract.php src/Factories/ src/Support/ContextFactoryResolver.php tests/Unit/LaravelContextFactoryTest.php
git commit -m "feat: factories auto-resolve context from request, no params

- SchemaContextFactoryContract.make() takes no arguments
- LaravelSchemaContextFactory populates routeParams from route
- StatamicSchemaContextFactory: route-bound -> Entry::findByUri -> null
- ContextFactoryResolver reads classes.context config key (was classes.class)"
```

---

### Task 4: Update component and runner

**Files:**
- Modify: `src/View/Components/StructuredData.php`
- Modify: `src/Support/SchemaRunner.php`
- Modify: `tests/Unit/SchemaRunnerTest.php`
- Modify: `tests/Unit/ContextFactoryResolverTest.php`

- [ ] **Step 1: Strip all props from the Blade component**

Replace `src/View/Components/StructuredData.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Superinteractive\StructuredData\Support\ContextFactoryResolver;
use Superinteractive\StructuredData\Support\SchemaRunner;

class StructuredData extends Component
{
    public function __construct(
        private readonly ContextFactoryResolver $contextFactoryResolver,
        private readonly SchemaRunner $schemaRunner,
    ) {}

    public function render(): View|Closure|string
    {
        if (! (bool) config('structured-data.enabled', true)) {
            return view('structured-data::components.structured-data', ['scripts' => []]);
        }

        $context = $this->contextFactoryResolver->make();
        $scripts = $this->schemaRunner->scripts($context);

        return view('structured-data::components.structured-data', ['scripts' => $scripts]);
    }
}
```

- [ ] **Step 2: Add contextType guard to SchemaRunner**

Replace `src/Support/SchemaRunner.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Support;

use Illuminate\Contracts\Container\Container;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;

class SchemaRunner
{
    public function __construct(
        private readonly Container $container,
        private readonly SchemaClassResolver $schemaClassResolver,
    ) {}

    /**
     * @return array<int, string>
     */
    public function scripts(SchemaContextContract $context): array
    {
        $scripts = [];

        foreach ($this->schemaClassResolver->classes() as $schemaClass) {
            $requiredContext = $schemaClass::contextType();

            if (! $context instanceof $requiredContext) {
                continue;
            }

            $schema = $this->container->make($schemaClass, ['context' => $context]);

            if (! $schema->applies()) {
                continue;
            }

            foreach ($schema->scripts() as $script) {
                if (! is_string($script) || $script === '') {
                    continue;
                }

                $scripts[] = $this->sanitizeScript($script);
            }
        }

        return $scripts;
    }

    private function sanitizeScript(string $script): string
    {
        if (preg_match('/^(<script[^>]*>)(.*?)(<\/script>)$/si', $script, $matches) !== 1) {
            return $script;
        }

        $decoded = json_decode(mb_trim($matches[2]), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $script;
        }

        $safeJson = json_encode(
            $decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        if ($safeJson === false) {
            return $script;
        }

        return $matches[1].$safeJson.$matches[3];
    }
}
```

- [ ] **Step 3: Add a runner regression test for incompatible context types**

Add this test to `tests/Unit/SchemaRunnerTest.php` before updating the existing context constructors:

```php
it('skips schemas whose required context type does not match the resolved context', function (): void {
    $schemaPath = 'Schemas/RunnerContextTypes';
    $directory = app_path($schemaPath);
    $appNamespace = mb_rtrim(app()->getNamespace(), '\\');
    $schemaNamespace = $appNamespace.'\\Schemas\\RunnerContextTypes';

    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    try {
        File::put($directory.'/ACompatibleSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class ACompatibleSchema extends BaseSchema
{
    public function applies(): bool
    {
        return true;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('compatible')->toScript()];
    }
}
PHP);

        File::put($directory.'/BIncompatibleStatamicSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\StatamicSchema;

class BIncompatibleStatamicSchema extends StatamicSchema
{
    public function applies(): bool
    {
        return true;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('incompatible')->toScript()];
    }
}
PHP);

        require_once $directory.'/ACompatibleSchema.php';
        require_once $directory.'/BIncompatibleStatamicSchema.php';

        config()->set('structured-data.schema_path', $schemaPath);

        $runner = app(SchemaRunner::class);

        $scripts = $runner->scripts(new LaravelContext(
            routeName: 'home',
            url: 'https://example.test',
            locale: 'en',
        ));

        expect($scripts)->toHaveCount(1)
            ->and($scripts[0])->toContain('"name":"compatible"')
            ->and($scripts[0])->not->toContain('"name":"incompatible"');
    } finally {
        File::deleteDirectory($directory);
    }
});
```

Without the `contextType()` guard, this test fails when the runner tries to instantiate `BIncompatibleStatamicSchema` with a `LaravelContext`.

- [ ] **Step 4: Update SchemaRunnerTest**

The test constructs contexts directly. Update both tests in `tests/Unit/SchemaRunnerTest.php` to use `LaravelContext`:

Change every occurrence of:
```php
use Superinteractive\StructuredData\Contexts\LaravelSchemaContext;
```
to:
```php
use Superinteractive\StructuredData\Contexts\LaravelContext;
```

Change every context construction from:
```php
$runner->scripts(new LaravelSchemaContext(
    routeName: 'home',
    collection: 'pages',
    blueprint: 'home',
    url: 'https://example.test',
    locale: 'en',
))
```
to:
```php
$runner->scripts(new LaravelContext(
    routeName: 'home',
    url: 'https://example.test',
    locale: 'en',
))
```

- [ ] **Step 5: Update ContextFactoryResolverTest**

Replace `tests/Unit/ContextFactoryResolverTest.php`:

```php
<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\Support\ContextFactoryResolver;

it('resolves a laravel context by default', function (): void {
    $resolver = app(ContextFactoryResolver::class);

    expect($resolver->resolveFactory())->toBeInstanceOf(LaravelSchemaContextFactory::class)
        ->and($resolver->make())->toBeInstanceOf(LaravelContext::class);
});

it('throws when configured context factory is invalid', function (): void {
    config()->set('structured-data.classes.factory', stdClass::class);

    $resolver = app(ContextFactoryResolver::class);

    expect(fn (): mixed => $resolver->resolveFactory())->toThrow(RuntimeException::class);
});
```

- [ ] **Step 6: Run full test suite**

Run: `vendor/bin/pest -v`
Expected: PASS (some tests may still fail due to config key change — fix in Task 5)

- [ ] **Step 7: Commit**

```bash
git add src/View/Components/StructuredData.php src/Support/SchemaRunner.php tests/Unit/SchemaRunnerTest.php tests/Unit/ContextFactoryResolverTest.php
git commit -m "feat: zero-prop component and context-type-aware runner

- <x-structured-data /> takes no props, fully auto-resolved
- SchemaRunner checks contextType() before instantiating schemas
- Incompatible schemas silently skipped (e.g. StatamicSchema in Laravel)"
```

---

## Chunk 3: Tooling, Config, and Documentation

### Task 5: Update command, stubs, config, and legacy cleanup

**Files:**
- Modify: `src/Commands/MakeSchemaCommand.php`
- Create: `stubs/ModelSchema.stub`
- Create: `stubs/StatamicSchema.stub`
- Modify: `stubs/HomepageOrganizationSchema.php.stub`
- Modify: `stubs/HomepageWebsiteSchema.php.stub`
- Modify: `config/structured-data.php`
- Modify: `tests/Feature/MakeSchemaCommandTest.php`
- Verify: `src/StructuredDataServiceProvider.php`

- [ ] **Step 1: Create ModelSchema stub**

Create `stubs/ModelSchema.stub`:

```php
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Superinteractive\StructuredData\Schemas\ModelSchema;

class {{ class }} extends ModelSchema
{
    protected function routeModelKey(): string
    {
        return ''; // e.g. 'product' for /products/{product}
    }

    public function applies(): bool
    {
        return false; // e.g. $this->model() instanceof \App\Models\Product
    }

    /**
     * @return array<int, string>
     */
    public function scripts(): array
    {
        return [];
    }
}
```

- [ ] **Step 2: Create StatamicSchema stub**

Create `stubs/StatamicSchema.stub`:

```php
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Superinteractive\StructuredData\Schemas\StatamicSchema;

class {{ class }} extends StatamicSchema
{
    public function applies(): bool
    {
        return false; // e.g. $this->context->collection() === 'articles'
    }

    /**
     * @return array<int, string>
     */
    public function scripts(): array
    {
        return [];
    }
}
```

- [ ] **Step 3: Update MakeSchemaCommand**

Replace `src/Commands/MakeSchemaCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeSchemaCommand extends GeneratorCommand
{
    protected $name = 'make:schema';

    protected $description = 'Create a new schema class';

    protected $type = 'Schema';

    public function handle()
    {
        if ($this->option('model') && $this->option('statamic')) {
            $this->components->error('The --model and --statamic options cannot be used together.');

            return false;
        }

        return parent::handle();
    }

    protected function getStub(): string
    {
        if ($this->option('model')) {
            return __DIR__.'/../../stubs/ModelSchema.stub';
        }

        if ($this->option('statamic')) {
            return __DIR__.'/../../stubs/StatamicSchema.stub';
        }

        return __DIR__.'/../../stubs/Schema.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $schemaPath = config('structured-data.schema_path', 'Schemas');

        if (! is_string($schemaPath)) {
            $schemaPath = 'Schemas';
        }

        $namespaceSuffix = str_replace(['/', '\\'], '\\', mb_trim($schemaPath, '/\\'));

        if ($namespaceSuffix === '') {
            return mb_rtrim((string) $rootNamespace, '\\');
        }

        return mb_rtrim((string) $rootNamespace, '\\').'\\'.$namespaceSuffix;
    }

    protected function qualifyClass($name): string
    {
        $name = (string) $name;

        if (! str_ends_with($name, 'Schema')) {
            $name .= 'Schema';
        }

        return parent::qualifyClass($name);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['model', 'm', InputOption::VALUE_NONE, 'Create a model-bound schema (extends ModelSchema)'],
            ['statamic', 's', InputOption::VALUE_NONE, 'Create a Statamic schema (extends StatamicSchema)'],
        ]);
    }
}
```

This keeps the parent-compatible untyped `qualifyClass()` signature, preserves the existing slash trimming in `getDefaultNamespace()`, and makes the flag behavior explicit by rejecting `--model --statamic`.

- [ ] **Step 4: Update homepage stubs to extend StatamicSchema**

In `stubs/HomepageOrganizationSchema.php.stub`, change:
- `use Superinteractive\StructuredData\Schemas\BaseSchema;` → `use Superinteractive\StructuredData\Schemas\StatamicSchema;`
- `class HomepageOrganizationSchema extends BaseSchema` → `class HomepageOrganizationSchema extends StatamicSchema`

In `stubs/HomepageWebsiteSchema.php.stub`, change:
- `use Superinteractive\StructuredData\Schemas\BaseSchema;` → `use Superinteractive\StructuredData\Schemas\StatamicSchema;`
- `class HomepageWebsiteSchema extends BaseSchema` → `class HomepageWebsiteSchema extends StatamicSchema`

- [ ] **Step 5: Update config**

Replace `config/structured-data.php`:

```php
<?php

declare(strict_types=1);

return [

    'enabled' => true,

    'schema_path' => 'Schemas',

    'classes' => [
        'context' => class_exists('Statamic\\Statamic')
            ? 'Superinteractive\\StructuredData\\Contexts\\StatamicContext'
            : 'Superinteractive\\StructuredData\\Contexts\\LaravelContext',
        'factory' => class_exists('Statamic\\Statamic')
            ? 'Superinteractive\\StructuredData\\Factories\\StatamicSchemaContextFactory'
            : 'Superinteractive\\StructuredData\\Factories\\LaravelSchemaContextFactory',
    ],

];
```

- [ ] **Step 6: Delete legacy context classes once all references are migrated**

Delete `src/Contexts/LaravelSchemaContext.php` and `src/Contexts/StatamicSchemaContext.php`.

Before deleting them, run:

```bash
rg -n "LaravelSchemaContext|StatamicSchemaContext" src tests config README CHANGELOG
```

Expected: only the old context class files themselves, or intentional upgrade-note references in docs/changelog, remain.

- [ ] **Step 7: Write tests for --model and --statamic flags**

Add to `tests/Feature/MakeSchemaCommandTest.php`:

```php
it('creates a model schema with --model flag', function (): void {
    $name = 'ProductGenerated';
    $className = $name.'Schema';
    $path = app_path('Schemas/'.$className.'.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    Artisan::call('make:schema', ['name' => $name, '--model' => true]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('class '.$className.' extends ModelSchema')
        ->and($contents)->toContain('protected function routeModelKey(): string');

    File::delete($path);
});

it('creates a statamic schema with --statamic flag', function (): void {
    $name = 'ArticleGenerated';
    $className = $name.'Schema';
    $path = app_path('Schemas/'.$className.'.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    Artisan::call('make:schema', ['name' => $name, '--statamic' => true]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('class '.$className.' extends StatamicSchema');

    File::delete($path);
});

it('rejects using --model and --statamic together', function (): void {
    $name = 'ConflictedGenerated';
    $path = app_path('Schemas/'.$name.'Schema.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    $exitCode = Artisan::call('make:schema', [
        'name' => $name,
        '--model' => true,
        '--statamic' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('cannot be used together')
        ->and(File::exists($path))->toBeFalse();
});

it('normalizes configured schema path slashes before building the namespace', function (): void {
    $schemaPath = '/Schemas/Generated\\';
    $name = 'SlashTrimmed';
    $className = $name.'Schema';
    $path = app_path('Schemas/Generated/'.$className.'.php');
    $directory = app_path('Schemas/Generated');

    File::deleteDirectory($directory);
    config()->set('structured-data.schema_path', $schemaPath);

    Artisan::call('make:schema', ['name' => $name]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('namespace App\\Schemas\\Generated;')
        ->and($contents)->toContain('class '.$className.' extends BaseSchema');

    File::deleteDirectory($directory);
});
```

- [ ] **Step 8: Verify ServiceProvider remains unchanged**

Read `src/StructuredDataServiceProvider.php` and confirm:
- the `ContextFactoryResolver` and `SchemaRunner` registrations are class-agnostic and need no constructor changes
- the published homepage stub paths are unchanged because the filenames did not move

If that stays true, do not edit the file in this task.

- [ ] **Step 9: Run full test suite**

Run: `vendor/bin/pest -v`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add src/Commands/ stubs/ config/ tests/Feature/MakeSchemaCommandTest.php
git rm src/Contexts/LaravelSchemaContext.php src/Contexts/StatamicSchemaContext.php
git commit -m "feat: make:schema --model/--statamic flags, updated config

- make:schema generates ModelSchema or StatamicSchema with flags
- make:schema rejects conflicting --model/--statamic usage
- Homepage stubs now extend StatamicSchema
- Config key renamed: classes.class -> classes.context
- Context class references updated to LaravelContext/StatamicContext"
```

---

### Task 6: Documentation

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Rewrite README.md**

Key changes:
- **Rendering section**: Show only `<x-structured-data />` with no props. Remove `:entry="$entry"` example entirely.
- **Writing Schemas section**: Show three examples:
  1. `BaseSchema` — homepage/site-wide schema using `routeName()`
  2. `ModelSchema` — product page using `routeModelKey()` + `model()`
  3. `StatamicSchema` — article page using `collection()` + `source()`
- **Configuration section**: Update to show `classes.context` key (was `classes.class`)
- **Create a New Schema section**: Document `--model` and `--statamic` as mutually exclusive flags
- **Upgrade notes section**: Document that `requestData` is removed from context objects; schemas should read request metadata from Laravel's request container when needed

Example schema for README:

```php
// BaseSchema — route-level matching, no model needed
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

// ModelSchema — route model binding
// Route: Route::get('/products/{product}', ...)->name('products.show');
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
        return [
            Schema::product()
                ->name($this->model()->name)
                ->description($this->model()->description)
                ->toScript(),
        ];
    }
}

// StatamicSchema — auto-resolved entry/page
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

- [ ] **Step 2: Update CHANGELOG.md**

Add entry documenting:
- BREAKING: `<x-structured-data />` no longer accepts props
- BREAKING: `SchemaContextContract` slimmed — `collection()`, `blueprint()`, `entry()`, `page()` removed from base
- BREAKING: `requestData` removed from context implementations; use Laravel's request APIs directly if needed
- BREAKING: Config key `classes.class` renamed to `classes.context`
- BREAKING: Context classes renamed: `LaravelSchemaContext` → `LaravelContext`, `StatamicSchemaContext` → `StatamicContext`
- Added: `ModelSchema` base class for route-model-bound schemas
- Added: `StatamicSchema` base class for Statamic schemas
- Added: `StatamicContextContract` interface
- Added: `make:schema --model` and `make:schema --statamic` generator flags (mutually exclusive)
- Added: Statamic auto-resolution (route-bound → `Entry::findByUri` → null)
- Added: `routeParams()` and `routeParam()` on all contexts

- [ ] **Step 3: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: rewrite README and CHANGELOG for modular context system"
```

---

## Manual Verification Checklist

These cannot be automated in the package test suite (no Statamic in require-dev):

- [ ] Statamic collection entry view: `<x-structured-data />` auto-resolves the current entry via `Entry::findByUri()`
- [ ] Statamic page view: source is the `Page`, `collection()` and `blueprint()` derived from its entry
- [ ] Statamic non-content route: `source()` is null, `routeName()` and `url()` still work
- [ ] Laravel model-bound route: `ModelSchema` resolves the bound model via `routeModelKey()`
- [ ] Laravel scalar param route: `routeParam('id')` returns the raw string value
- [ ] Laravel route with no params: `routeParams()` is empty, `routeName()` works
- [ ] `StatamicSchema` in a Laravel-only app: silently skipped by runner (no error)
- [ ] `make:schema --model --statamic` exits with an error and does not write a file

## Design Decisions

| Decision | Rationale |
|----------|-----------|
| Model resolution on schema, not context | Per-schema concern — different schemas need different models. Avoids shared mutable state between schemas. |
| Contexts stay `final readonly` | Immutability prevents side effects. One schema's work can't affect another. |
| `source(): mixed` in StatamicContextContract | Avoids importing Statamic types into the interface, which would crash autoloading in non-Statamic environments. PHPDoc provides IDE support. |
| `$modelResolved` flag instead of null-coalescing | A `null` route param is a valid cached result — must distinguish "not yet resolved" from "resolved to null". |
| No backwards compatibility | Package is pre-production. Clean break avoids deprecation aliases and dual code paths. |
| `contextType()` static method | Runner checks compatibility without reflection or try/catch. Explicit, fast, testable. |
| No `entry()` or `page()` on StatamicContextContract | `source()` returns either. Schemas use `instanceof` to distinguish. Keeps the interface minimal. |
