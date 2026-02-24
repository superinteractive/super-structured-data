<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Factories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Structures\Page;
use Superinteractive\StructuredData\Contexts\StatamicSchemaContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;

class StatamicSchemaContextFactory implements SchemaContextFactoryContract
{
    public function make(mixed $entry = null): SchemaContextContract
    {
        $source = $this->resolveSource($entry);
        $page = $source instanceof Page ? $source : null;
        $resolvedEntry = $this->resolveEntry($source);

        return new StatamicSchemaContext(
            entry: $resolvedEntry,
            routeName: $this->resolveRouteName(),
            collection: $this->resolveCollection($resolvedEntry, $page),
            blueprint: $this->resolveBlueprint($resolvedEntry, $page),
            url: $this->resolveUrl($source, $resolvedEntry),
            locale: $this->resolveLocale($resolvedEntry, $page),
            page: $page,
            requestData: $this->resolveRequestData(),
        );
    }

    private function resolveRouteName(): ?string
    {
        $request = app(Request::class);
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return null;
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRequestData(): array
    {
        $request = app(Request::class);

        return [
            'method' => $request->method(),
            'path' => $request->path(),
        ];
    }

    private function resolveUrl(?EntryContract $source, ?EntryContract $entry): string
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

    private function resolveLocale(?EntryContract $entry, ?Page $page): ?string
    {
        if ($entry instanceof EntryContract && method_exists($entry, 'locale')) {
            $locale = $entry->locale();

            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        if ($page instanceof Page) {
            $site = rescue(fn (): mixed => $page->site(), report: false);
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

    private function resolveSource(mixed $entry): ?EntryContract
    {
        if ($entry instanceof Page) {
            return $entry;
        }

        if ($entry instanceof EntryContract) {
            return $entry;
        }

        if (is_string($entry) && $entry !== '') {
            $resolvedEntry = EntryFacade::find($entry);

            if ($resolvedEntry instanceof EntryContract) {
                return $resolvedEntry;
            }
        }

        return null;
    }

    private function resolveEntry(?EntryContract $source): ?EntryContract
    {
        if ($source instanceof Page) {
            $entry = rescue(fn (): mixed => $source->entry(), report: false);

            return $entry instanceof EntryContract ? $entry : null;
        }

        return $source;
    }

    private function resolveCollection(?EntryContract $entry, ?Page $page): ?string
    {
        if ($entry instanceof EntryContract) {
            return $this->resolveCollectionHandle($entry);
        }

        if ($page instanceof Page) {
            $collectionFromRouteData = data_get($this->resolvePageRouteData($page), 'collection');

            if (is_string($collectionFromRouteData) && $collectionFromRouteData !== '') {
                return $collectionFromRouteData;
            }

            $collection = rescue(fn (): mixed => $page->collection(), report: false);

            if (is_object($collection) && method_exists($collection, 'handle')) {
                $handle = $collection->handle();

                if (is_string($handle) && $handle !== '') {
                    return $handle;
                }
            }
        }

        return null;
    }

    private function resolveBlueprint(?EntryContract $entry, ?Page $page): ?string
    {
        if ($entry instanceof EntryContract) {
            return $this->resolveBlueprintHandle($entry);
        }

        if ($page instanceof Page) {
            $blueprintFromRouteData = data_get($this->resolvePageRouteData($page), 'blueprint');

            if (is_string($blueprintFromRouteData) && $blueprintFromRouteData !== '') {
                return $blueprintFromRouteData;
            }

            $blueprint = rescue(fn (): mixed => $page->blueprint(), report: false);

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
