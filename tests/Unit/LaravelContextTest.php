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
