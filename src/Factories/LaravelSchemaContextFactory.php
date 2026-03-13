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
