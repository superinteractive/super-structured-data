<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\Support\ContextFactoryResolver;

it('resolves a laravel context by default', function (): void {
    $resolver = app(ContextFactoryResolver::class);

    expect($resolver->resolveFactory())->toBeInstanceOf(LaravelSchemaContextFactory::class)
        ->and($resolver->make())->toBeInstanceOf(LaravelContext::class);
});

it('uses the bound context factory when the application overrides it', function (): void {
    $customFactory = new class implements SchemaContextFactoryContract
    {
        public function make(): SchemaContextContract
        {
            return new class implements SchemaContextContract
            {
                public function routeName(): ?string
                {
                    return 'custom.route';
                }

                public function url(): string
                {
                    return 'https://custom.test';
                }

                public function locale(): ?string
                {
                    return 'nl';
                }

                public function routeParams(): array
                {
                    return ['article' => 42];
                }

                public function routeParam(string $key, mixed $default = null): mixed
                {
                    return $this->routeParams()[$key] ?? $default;
                }
            };
        }
    };

    app()->singleton(SchemaContextFactoryContract::class, static fn () => $customFactory);

    $resolver = app(ContextFactoryResolver::class);
    $context = $resolver->make();

    expect($resolver->resolveFactory())->toBe($customFactory)
        ->and($context->routeName())->toBe('custom.route')
        ->and($context->url())->toBe('https://custom.test')
        ->and($context->routeParam('article'))->toBe(42);
});
