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
