<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contexts\LaravelSchemaContext;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\Support\ContextFactoryResolver;

it('resolves a laravel context by default', function (): void {
    $resolver = app(ContextFactoryResolver::class);

    expect($resolver->resolveFactory())->toBeInstanceOf(LaravelSchemaContextFactory::class)
        ->and($resolver->make())->toBeInstanceOf(LaravelSchemaContext::class);
});

it('throws when configured context factory is invalid', function (): void {
    config()->set('structured-data.classes.factory', stdClass::class);

    $resolver = app(ContextFactoryResolver::class);

    expect(fn (): mixed => $resolver->resolveFactory())->toThrow(RuntimeException::class);
});
