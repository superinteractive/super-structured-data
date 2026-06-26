<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Entries\EntryRepository;
use Statamic\Facades\Site;
use Statamic\Statamic;
use Superinteractive\StructuredData\Contexts\StatamicContext;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\StatamicSchemaContextFactory;

beforeEach(function (): void {
    if (! class_exists(Statamic::class)) {
        $this->markTestSkipped('Statamic is not installed.');
    }

    config()->set('filesystems.disks.standard', [
        'driver' => 'local',
        'root' => base_path(),
    ]);
});

it('switches to the statamic context factory when statamic is installed', function (): void {
    expect(app(SchemaContextFactoryContract::class))->toBeInstanceOf(StatamicSchemaContextFactory::class);
});

it('builds a statamic context when no content item can be resolved', function (): void {
    Site::shouldReceive('current')->andReturn(null);

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')->once()->andReturn(null);
    app()->instance(EntryRepository::class, $entries);

    $request = Request::create('/missing');
    app()->instance('request', $request);

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context)->toBeInstanceOf(StatamicContext::class)
        ->and($context->collection())->toBeNull()
        ->and($context->blueprint())->toBeNull();
});

it('resolves entries when a relative multisite site URL prefixes the request path', function (): void {
    Site::shouldReceive('current')->andReturn(statamicCompatibilitySite(
        handle: 'default',
        url: '/nl/',
        absoluteUrl: 'https://example.test/nl/',
        locale: 'nl_NL',
    ));

    $entry = statamicCompatibilityEntry(
        absoluteUrl: 'https://example.test/nl/nederland/foo',
        collection: 'properties',
        blueprint: 'property',
        locale: 'nl_NL',
    );

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')
        ->once()
        ->ordered()
        ->with('/nl/nederland/foo', 'default')
        ->andReturn(null);
    $entries->shouldReceive('findByUri')
        ->once()
        ->ordered()
        ->with('/nederland/foo', 'default')
        ->andReturn($entry);
    app()->instance(EntryRepository::class, $entries);

    bindStatamicCompatibilityRequest('/nl/nederland/foo');

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context->source())->toBe($entry)
        ->and($context->collection())->toBe('properties')
        ->and($context->blueprint())->toBe('property')
        ->and($context->locale())->toBe('nl_NL')
        ->and($context->url())->toBe('https://example.test/nl/nederland/foo');
});

it('resolves the site root when the request path matches the site URL prefix', function (): void {
    Site::shouldReceive('current')->andReturn(statamicCompatibilitySite(
        handle: 'default',
        url: '/nl/',
        absoluteUrl: 'https://example.test/nl/',
        locale: 'nl_NL',
    ));

    $entry = statamicCompatibilityEntry(
        absoluteUrl: 'https://example.test/nl/',
        collection: 'pages',
        blueprint: 'home',
        locale: 'nl_NL',
    );

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')
        ->once()
        ->ordered()
        ->with('/nl', 'default')
        ->andReturn(null);
    $entries->shouldReceive('findByUri')
        ->once()
        ->ordered()
        ->with('/', 'default')
        ->andReturn($entry);
    app()->instance(EntryRepository::class, $entries);

    bindStatamicCompatibilityRequest('/nl');

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context->source())->toBe($entry)
        ->and($context->collection())->toBe('pages')
        ->and($context->blueprint())->toBe('home')
        ->and($context->url())->toBe('https://example.test/nl/');
});

it('resolves entries when an absolute multisite site URL prefixes the request path', function (): void {
    Site::shouldReceive('current')->andReturn(statamicCompatibilitySite(
        handle: 'english',
        url: 'https://example.test/en/',
        absoluteUrl: 'https://example.test/en/',
        locale: 'en_US',
    ));

    $entry = statamicCompatibilityEntry(
        absoluteUrl: 'https://example.test/en/nederland/foo',
        collection: 'properties',
        blueprint: 'property',
        locale: 'en_US',
    );

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')
        ->once()
        ->ordered()
        ->with('/en/nederland/foo', 'english')
        ->andReturn(null);
    $entries->shouldReceive('findByUri')
        ->once()
        ->ordered()
        ->with('/nederland/foo', 'english')
        ->andReturn($entry);
    app()->instance(EntryRepository::class, $entries);

    bindStatamicCompatibilityRequest('/en/nederland/foo');

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context->source())->toBe($entry)
        ->and($context->collection())->toBe('properties')
        ->and($context->blueprint())->toBe('property')
        ->and($context->locale())->toBe('en_US');
});

it('keeps the current URI lookup when the request path is already unprefixed', function (): void {
    Site::shouldReceive('current')->andReturn(statamicCompatibilitySite(
        handle: 'default',
        url: '/nl/',
        absoluteUrl: 'https://example.test/nl/',
        locale: 'nl_NL',
    ));

    $entry = statamicCompatibilityEntry(
        absoluteUrl: 'https://example.test/nl/nederland/foo',
        collection: 'properties',
        blueprint: 'property',
        locale: 'nl_NL',
    );

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')
        ->once()
        ->with('/nederland/foo', 'default')
        ->andReturn($entry);
    app()->instance(EntryRepository::class, $entries);

    bindStatamicCompatibilityRequest('/nederland/foo');

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context->source())->toBe($entry)
        ->and($context->collection())->toBe('properties')
        ->and($context->blueprint())->toBe('property');
});

it('does not strip unrelated path prefixes', function (): void {
    Site::shouldReceive('current')->andReturn(statamicCompatibilitySite(
        handle: 'default',
        url: '/nl/',
        absoluteUrl: 'https://example.test/nl/',
        locale: 'nl_NL',
    ));

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')
        ->once()
        ->with('/newsletter/foo', 'default')
        ->andReturn(null);
    app()->instance(EntryRepository::class, $entries);

    bindStatamicCompatibilityRequest('/newsletter/foo');

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context->source())->toBeNull()
        ->and($context->collection())->toBeNull()
        ->and($context->blueprint())->toBeNull();
});

it('keeps route-bound statamic content ahead of fallback URI lookup', function (): void {
    Site::shouldReceive('current')->never();

    $entry = statamicCompatibilityEntry(
        absoluteUrl: 'https://example.test/nl/route-bound',
        collection: 'properties',
        blueprint: 'property',
        locale: 'nl_NL',
    );

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')->never();
    app()->instance(EntryRepository::class, $entries);

    $request = bindStatamicCompatibilityRequest('/nl/route-bound');
    $request->setRouteResolver(fn (): object => new class($entry)
    {
        public function __construct(private readonly EntryContract $entry) {}

        /**
         * @return array<string, EntryContract>
         */
        public function parameters(): array
        {
            return ['entry' => $this->entry];
        }

        public function getName(): string
        {
            return 'statamic.pages.show';
        }
    });

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context->source())->toBe($entry)
        ->and($context->routeName())->toBe('statamic.pages.show')
        ->and($context->collection())->toBe('properties')
        ->and($context->blueprint())->toBe('property');
});

function bindStatamicCompatibilityRequest(string $path): Request
{
    $request = Request::create($path, 'GET');

    app()->instance('request', $request);
    app()->instance(Request::class, $request);

    return $request;
}

function statamicCompatibilitySite(
    string $handle = 'default',
    string $url = '/nl/',
    string $absoluteUrl = 'https://example.test/nl/',
    string $locale = 'nl_NL',
): object {
    return new class($handle, $url, $absoluteUrl, $locale)
    {
        public function __construct(
            private readonly string $handle,
            private readonly string $url,
            private readonly string $absoluteUrl,
            private readonly string $locale,
        ) {}

        public function handle(): string
        {
            return $this->handle;
        }

        public function url(): string
        {
            return $this->url;
        }

        public function absoluteUrl(): string
        {
            return $this->absoluteUrl;
        }

        public function locale(): string
        {
            return $this->locale;
        }
    };
}

function statamicCompatibilityEntry(
    string $absoluteUrl = 'https://example.test/nl/nederland/foo',
    string $collection = 'properties',
    string $blueprint = 'property',
    string $locale = 'nl_NL',
): EntryContract {
    $entry = Mockery::mock(EntryContract::class);

    $entry->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);
    $entry->shouldReceive('locale')->andReturn($locale);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('blueprint')->andReturn(new class($blueprint)
    {
        public function __construct(private readonly string $handle) {}

        public function handle(): string
        {
            return $this->handle;
        }
    });

    return $entry;
}
