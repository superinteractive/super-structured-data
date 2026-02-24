<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Support;

use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Superinteractive\StructuredData\Contexts\LaravelSchemaContext;
use Superinteractive\StructuredData\Contexts\StatamicSchemaContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\Factories\StatamicSchemaContextFactory;

class ContextFactoryResolver
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(mixed $entry = null): SchemaContextContract
    {
        $context = $this->resolveFactory()->make($entry);
        $expectedClass = $this->resolveContextClass();

        if (! $context instanceof $expectedClass) {
            throw new RuntimeException(sprintf(
                'Configured classes.class [%s] does not match resolved context instance [%s].',
                $expectedClass,
                $context::class,
            ));
        }

        return $context;
    }

    public function resolveFactory(): SchemaContextFactoryContract
    {
        $factoryClass = config('structured-data.classes.factory', $this->defaultFactoryClass());

        if (! is_string($factoryClass) || $factoryClass === '') {
            throw new RuntimeException('Invalid structured-data.classes.factory configuration.');
        }

        $factory = $this->container->make($factoryClass);

        if (! $factory instanceof SchemaContextFactoryContract) {
            throw new RuntimeException(sprintf(
                'Configured classes.factory [%s] must implement [%s].',
                $factoryClass,
                SchemaContextFactoryContract::class,
            ));
        }

        return $factory;
    }

    /**
     * @return class-string<SchemaContextContract>
     */
    public function resolveContextClass(): string
    {
        $contextClass = config('structured-data.classes.class', $this->defaultContextClass());

        if (! is_string($contextClass) || $contextClass === '') {
            throw new RuntimeException('Invalid structured-data.classes.class configuration.');
        }

        if (! is_subclass_of($contextClass, SchemaContextContract::class)) {
            throw new RuntimeException(sprintf(
                'Configured classes.class [%s] must implement [%s].',
                $contextClass,
                SchemaContextContract::class,
            ));
        }

        return $contextClass;
    }

    /**
     * @return class-string<SchemaContextContract>
     */
    private function defaultContextClass(): string
    {
        if (class_exists('Statamic\\Statamic')) {
            return StatamicSchemaContext::class;
        }

        return LaravelSchemaContext::class;
    }

    /**
     * @return class-string<SchemaContextFactoryContract>
     */
    private function defaultFactoryClass(): string
    {
        if (class_exists('Statamic\\Statamic')) {
            return StatamicSchemaContextFactory::class;
        }

        return LaravelSchemaContextFactory::class;
    }
}
