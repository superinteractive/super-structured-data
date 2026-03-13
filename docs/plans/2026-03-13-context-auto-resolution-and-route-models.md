# Structured Data Context Auto-Resolution and Route Models Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make `<x-structured-data />` work without caller-supplied context in both Laravel and Statamic, while exposing route parameter and model helpers on the schema context so schemas can safely resolve and reuse the current model.

**Architecture:** Move current-page and current-route discovery into the context factories instead of Blade call sites. In Statamic mode, do not depend on Blade components automatically receiving `$page` or cascade data; resolve the current source through a chain of explicit override, route-bound content, then `Entry::findByUri(...)`, and fall back to URL-only context when no content exists. In Laravel mode, enrich both context implementations with shared route metadata (`routeParams`, bound route models, optional mutable `model`) so schema classes can match on route name, inspect parameters, and cache a resolved model for later use in `scripts()`.

**Tech Stack:** PHP 8.4, Laravel 12, Blade components, Orchestra Testbench/Pest, optional Statamic APIs, Spatie schema-org

## Findings

- Statamic documents that `$page` is always available in a Blade template, but also documents that cascade data is not automatically available inside Blade components unless context is explicitly passed. That means a package component cannot safely assume it can directly read `$page` from its own class lifecycle.
- Statamic also documents `Entry::findByUri($uri, $site)`, which gives the package a reliable request-based fallback for normal entry routes when no explicit source is passed.
- Laravel already exposes route names, parameters, and implicit route model binding on the current request. The package currently reduces that to only `routeName`, `collection`, and `blueprint`, so the richer routing context is being lost.
- The current context classes are `readonly`, which blocks the user flow you described (`$this->context->model = ...`). If you want a writable `model` slot, the contexts cannot remain `readonly`.

## Recommended Approach

- Standardize the public component usage on `<x-structured-data />`.
- Keep `entry` as a deprecated override for one release so existing Statamic templates do not break immediately.
- Add shared helpers to both context implementations for route params, bound models, and a writable/cached `model`.
- Implement Statamic auto-resolution as `explicit override -> route-bound Entry/Page -> Entry::findByUri(...) -> null`.
- Treat full `Page` auto-resolution as best-effort. If the current request is not backed by a Statamic page or entry, the context should still be valid with `url`, `routeName`, and request metadata only.

### Task 1: Normalize the component and factory API around a generic source override

**Files:**
- Create: `tests/Unit/StructuredDataComponentTest.php`
- Modify: `src/View/Components/StructuredData.php`
- Modify: `src/Support/ContextFactoryResolver.php`
- Modify: `src/Contracts/SchemaContextFactoryContract.php`

**Step 1: Write minimal implementation**

Keep the component usable as `<x-structured-data />`, but rename the internal value from Statamic-specific `entry` to generic `source` or `contextSource`. Preserve `entry` as a deprecated alias until the next major version.

```php
public function __construct(
    private readonly ContextFactoryResolver $contextFactoryResolver,
    private readonly SchemaRunner $schemaRunner,
    public mixed $source = null,
    public mixed $entry = null,
    public array $breadcrumbs = [],
) {}

$context = $this->contextFactoryResolver->make($this->source ?? $this->entry);
```

Also rename the factory contract and resolver signatures from `make(mixed $entry = null)` to `make(mixed $source = null)` so the API no longer implies Statamic-only behavior.

**Step 2: Confirm behavior is ready**

Render a minimal Blade string that uses `<x-structured-data />` with no props and confirm the factory receives `null`. Render a second Blade string with the legacy `:entry="$entry"` prop and confirm the same factory path still resolves.

**Step 3: Write tests after implementation**

In `tests/Unit/StructuredDataComponentTest.php`, cover the no-arg and legacy alias flows with mocks for `ContextFactoryResolver` and `SchemaRunner`.

```php
it('renders without an explicit source', function (): void {
    $resolver = Mockery::mock(ContextFactoryResolver::class);
    $runner = Mockery::mock(SchemaRunner::class);
    $context = Mockery::mock(SchemaContextContract::class);

    $resolver->shouldReceive('make')->once()->with(null)->andReturn($context);
    $runner->shouldReceive('scripts')->once()->with($context)->andReturn([]);

    $component = new StructuredData($resolver, $runner);

    expect($component->render()->render())->toBe('');
});
```

**Step 4: Prove tests can fail (negative control)**

Temporarily change the expected `with(null)` call to `with('legacy')` in the new no-arg test and confirm the mock expectation fails, then revert.

**Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/StructuredDataComponentTest.php -v`
Expected: PASS

### Task 2: Add shared route and model helpers to the schema context contract

**Files:**
- Create: `tests/Unit/SchemaContextHelpersTest.php`
- Modify: `src/Contracts/SchemaContextContract.php`
- Modify: `src/Contexts/LaravelSchemaContext.php`
- Modify: `src/Contexts/StatamicSchemaContext.php`
- Modify: `src/Schemas/BaseSchema.php`

**Step 1: Write minimal implementation**

Drop `readonly` from both context classes and add shared helper state:

- `public Fluent $routeParams`
- `public Fluent $boundModels`
- `public mixed $model = null`

Expose helper methods on the contract so schema authors have a stable API even if they do not want to touch public properties directly.

```php
use Illuminate\Support\Fluent;

interface SchemaContextContract
{
    public function routeName(): ?string;
    public function routeParams(): Fluent;
    public function routeParam(string $key, mixed $default = null): mixed;
    public function boundModels(): Fluent;
    public function boundModel(string $key, mixed $default = null): mixed;
    public function model(): mixed;
    public function setModel(mixed $model): mixed;
    public function collection(): ?string;
    public function blueprint(): ?string;
    public function url(): string;
    public function locale(): ?string;
    public function entry(): mixed;
    public function page(): mixed;
}
```

Update `BaseSchema` docblocks so the supported context surface now includes `routeParams`, `boundModels`, and `model`.

**Step 2: Confirm behavior is ready**

Instantiate a context directly and confirm all of these work before writing tests:

- `$context->routeParam('id')`
- `$context->boundModel('story')`
- `$context->setModel($story)`
- `$context->model`

**Step 3: Write tests after implementation**

Add `tests/Unit/SchemaContextHelpersTest.php` with one direct-construction test for Laravel context and one for Statamic context shape.

```php
it('stores route params and a mutable model', function (): void {
    $context = new LaravelSchemaContext(
        routeName: 'story',
        collection: null,
        blueprint: null,
        url: 'https://example.test/story/42',
        locale: 'en',
        routeParams: new Fluent(['id' => '42']),
        boundModels: new Fluent,
    );

    expect($context->routeParam('id'))->toBe('42');

    $story = new stdClass;
    $context->setModel($story);

    expect($context->model())->toBe($story)
        ->and($context->model)->toBe($story);
});
```

**Step 4: Prove tests can fail (negative control)**

Temporarily change the expected route param from `'42'` to `'43'` and confirm the new helper test fails, then revert.

**Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/SchemaContextHelpersTest.php -v`
Expected: PASS

### Task 3: Populate Laravel context with route params and bound models

**Files:**
- Create: `tests/Unit/LaravelSchemaContextFactoryTest.php`
- Modify: `src/Factories/LaravelSchemaContextFactory.php`
- Modify: `src/Contexts/LaravelSchemaContext.php`

**Step 1: Write minimal implementation**

Teach `LaravelSchemaContextFactory` to capture:

- raw route params for routes like `/story/{id}`
- bound models for routes that use implicit model binding
- a default `model` value when exactly one bound model exists

Keep existing `collection`, `blueprint`, `url`, and `locale` behavior intact.

```php
$route = $request->route();
$routeParams = $this->resolveRouteParams($route);
$boundModels = $this->resolveBoundModels($route);

return new LaravelSchemaContext(
    routeName: $routeName,
    collection: $this->resolveCollection($source),
    blueprint: $this->resolveBlueprint($source),
    url: $url,
    locale: $locale,
    entry: $source,
    routeParams: new Fluent($routeParams),
    boundModels: new Fluent($boundModels),
    model: count($boundModels) === 1 ? array_values($boundModels)[0] : null,
    requestData: [
        'method' => $request->method(),
        'path' => $request->path(),
    ],
);
```

If the route API exposes raw and bound parameters through different methods, prefer raw values for `routeParams` and bound objects for `boundModels`. Do not overload one bag with both.

**Step 2: Confirm behavior is ready**

Register two tiny test routes in a focused test:

- `/story/{id}` named `story`
- `/stories/{story}` named `stories.show` with a custom binder returning a fake model object

Before assertions, dump or inspect the created context once to make sure `routeParams->id` stays scalar on the first route and `model()` is prefilled on the second route.

**Step 3: Write tests after implementation**

In `tests/Unit/LaravelSchemaContextFactoryTest.php`, cover both scalar params and bound model routes.

```php
it('captures scalar route params', function (): void {
    Route::get('/story/{id}', fn () => 'ok')->name('story');

    $request = Request::create('/story/42');
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $context = app(LaravelSchemaContextFactory::class)->make();

    expect($context->routeName())->toBe('story')
        ->and($context->routeParam('id'))->toBe('42')
        ->and($context->model())->toBeNull();
});
```

**Step 4: Prove tests can fail (negative control)**

Temporarily assert that the scalar route test returns `'41'` or a non-null `model()` and confirm the test fails, then revert.

**Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/LaravelSchemaContextFactoryTest.php tests/Unit/SchemaContextHelpersTest.php -v`
Expected: PASS

### Task 4: Implement Statamic auto-resolution for the current entry or page

**Files:**
- Create: `src/Support/StatamicCurrentContentResolver.php`
- Modify: `src/Factories/StatamicSchemaContextFactory.php`
- Modify: `src/Contexts/StatamicSchemaContext.php`
- Modify: `src/View/Components/StructuredData.php`

**Step 1: Write minimal implementation**

Extract the Statamic discovery logic into a dedicated resolver so the auto-resolution rules stay readable and testable. The resolver order should be:

1. Explicit override passed from the component or caller
2. Any current route parameter that is already a `Page` or `Entry`
3. `Entry::findByUri($uri, $site)` using the current request URI and current site
4. `null`

```php
final class StatamicCurrentContentResolver
{
    public function resolve(mixed $source = null): EntryContract|Page|null
    {
        $explicit = $this->normalizeExplicitSource($source);

        if ($explicit !== null) {
            return $explicit;
        }

        foreach (request()->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Page || $parameter instanceof EntryContract) {
                return $parameter;
            }
        }

        $uri = '/'.ltrim(request()->path(), '/');
        $site = Site::current()?->handle();
        $entry = EntryFacade::findByUri($uri === '//' ? '/' : $uri, $site);

        return $entry instanceof EntryContract ? $entry : null;
    }
}
```

Inject that resolver into `StatamicSchemaContextFactory` and keep the existing `collection`, `blueprint`, `locale`, and `url` derivation logic. The factory should continue to resolve `page` when the source is a `Page`, and otherwise populate `entry` only.

**Step 2: Confirm behavior is ready**

Manual Statamic smoke check in a real host app:

1. Put `<x-structured-data />` directly in a collection entry Blade view and confirm `entry()` resolves without passing any props.
2. Render the same component from inside another Blade component and confirm the request/URI fallback still resolves the entry.
3. Hit a custom controller route that is not backed by Statamic content and confirm `entry()` and `page()` are null while `url()` and `routeName()` still work.

**Step 3: Write tests after implementation**

Do not invent fake Statamic behavior in the core package test suite. Either:

- add `statamic/cms` to `require-dev` and create `tests/Feature/StatamicCurrentContentResolverTest.php`, or
- keep the package test suite Laravel-only and record the manual smoke checklist above as the required verification path for this feature

If you choose the first option, cover explicit override, route-bound content, and URI fallback in that test file. If you choose the second option, keep `StatamicCurrentContentResolver` small and document the gap in the final implementation notes.

**Step 4: Prove tests can fail (negative control)**

For automated Statamic tests, temporarily assert a mismatched URI or wrong collection handle and confirm the test fails. For the manual path, temporarily break the URI lookup and confirm the collection entry smoke check stops resolving an entry, then revert.

**Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS for package tests, plus all three manual Statamic smoke checks succeed if Statamic verification is being done outside this package

### Task 5: Update documentation, examples, and release notes

**Files:**
- Modify: `README.md`
- Modify: `stubs/Schema.stub`
- Modify: `CHANGELOG.md`

**Step 1: Write minimal implementation**

Update the docs so the package now teaches one default component API:

```blade
<x-structured-data />
```

Document explicit source passing as an edge-case override rather than the normal Statamic usage. Also replace the current schema example with one that shows the new route helpers:

```php
public function applies(): bool
{
    return $this->context->routeName() === 'story';
}

public function scripts(): array
{
    $story = $this->context->model()
        ?? $this->context->setModel(
            Story::findOrFail($this->context->routeParam('id'))
        );

    return [
        Schema::article()->headline($story->title)->toScript(),
    ];
}
```

Also add one short note that the package method is `scripts()`, not `buildSchemas()`, so the docs stay aligned with the current API.

**Step 2: Confirm behavior is ready**

Read the README start-to-finish once after the edits and make sure these statements are all true at the same time:

- Laravel docs never mention `entry` as the normal flow
- Statamic docs show `<x-structured-data />` with no props
- The schema example compiles against the new context helpers

**Step 3: Write tests after implementation**

If you changed the stub output shape or generated example comments, add a focused assertion in `tests/Feature/MakeSchemaCommandTest.php` so the generated schema still matches the documented API.

```php
expect($contents)->toContain('public function scripts(): array');
```

**Step 4: Prove tests can fail (negative control)**

Temporarily assert the old `:entry="$entry"` example still exists in the README-related expectation or that the stub contains `buildSchemas`, confirm failure, then revert.

**Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/MakeSchemaCommandTest.php -v`
Expected: PASS

## Manual Verification Checklist

- Laravel scalar route: `/story/{id}` exposes the scalar identifier through `routeParam('id')`.
- Laravel bound route: `/stories/{story}` exposes the bound model through `boundModel('story')` and pre-populates `model()` when there is exactly one bound model.
- Statamic collection entry view resolves the current entry from `<x-structured-data />` with no props.
- Statamic nested Blade component usage still resolves the current entry via request/URI fallback.
- Non-content routes keep working with a context that has no `entry`/`page` but still has `routeName`, `url`, and request metadata.

## Assumptions and Risks

- Recommended compatibility strategy: keep `entry` as a deprecated alias for one release, then remove it in the next major version.
- Recommended context strategy: allow a writable `model` slot. If you want strict immutability instead, switch the design to `withModel()` before implementation and do not mix both approaches.
- Statamic `Page` auto-resolution is best-effort. URI fallback can reliably recover entries, but it cannot manufacture a `Page` object for routes that do not expose one.
- If you keep `statamic/cms` out of `require-dev`, the package will retain a manual verification gap for Statamic-specific behavior. Document that explicitly when shipping the change.
