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
            $sourceUrl = $this->resolveAbsoluteUrl($source);

            if ($sourceUrl !== null) {
                return $sourceUrl;
            }
        }

        if ($entry instanceof EntryContract) {
            $entryUrl = $this->resolveAbsoluteUrl($entry);

            if ($entryUrl !== null) {
                return $entryUrl;
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

        return $source;
    }

    private function resolveCollection(?EntryContract $entry, EntryContract|Page|null $source): ?string
    {
        if ($entry instanceof EntryContract) {
            return $this->resolveCollectionHandle($entry);
        }

        if ($source instanceof Page) {
            $collectionFromRouteData = data_get($this->resolvePageRouteData($source), 'collection');

            if (is_string($collectionFromRouteData) && $collectionFromRouteData !== '') {
                return $collectionFromRouteData;
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
            $blueprintFromRouteData = data_get($this->resolvePageRouteData($source), 'blueprint');

            if (is_string($blueprintFromRouteData) && $blueprintFromRouteData !== '') {
                return $blueprintFromRouteData;
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

    /**
     * @return array<string, mixed>
     */
    private function resolvePageRouteData(Page $page): array
    {
        $routeData = rescue(fn (): mixed => $page->routeData(), report: false);

        if (! is_array($routeData)) {
            return [];
        }

        return $routeData;
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
