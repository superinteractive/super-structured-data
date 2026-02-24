<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Factories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Superinteractive\StructuredData\Contexts\LaravelSchemaContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;

class LaravelSchemaContextFactory implements SchemaContextFactoryContract
{
    public function make(mixed $entry = null): SchemaContextContract
    {
        $request = app(Request::class);
        $routeName = $request->route()?->getName();

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

        return new LaravelSchemaContext(
            routeName: $routeName,
            collection: $this->resolveCollection($entry),
            blueprint: $this->resolveBlueprint($entry),
            url: $url,
            locale: $locale,
            entry: $entry,
            requestData: [
                'method' => $request->method(),
                'path' => $request->path(),
            ],
        );
    }

    private function resolveCollection(mixed $entry): ?string
    {
        if (is_object($entry) && method_exists($entry, 'collectionHandle')) {
            $handle = $entry->collectionHandle();

            if (is_string($handle) && $handle !== '') {
                return $handle;
            }
        }

        $collection = data_get($entry, 'collection');

        if (! is_string($collection) || $collection === '') {
            return null;
        }

        return $collection;
    }

    private function resolveBlueprint(mixed $entry): ?string
    {
        if (is_object($entry) && method_exists($entry, 'blueprint')) {
            $blueprint = rescue(fn (): mixed => $entry->blueprint(), report: false);

            if (is_object($blueprint) && method_exists($blueprint, 'handle')) {
                $handle = $blueprint->handle();

                if (is_string($handle) && $handle !== '') {
                    return $handle;
                }
            }
        }

        $blueprint = data_get($entry, 'blueprint');

        if (! is_string($blueprint) || $blueprint === '') {
            return null;
        }

        return $blueprint;
    }
}
