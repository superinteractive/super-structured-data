<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Support;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;

class ContextFactoryResolver
{
    public function __construct(
        private readonly SchemaContextFactoryContract $factory,
    ) {}

    public function make(): SchemaContextContract
    {
        return $this->factory->make();
    }

    public function resolveFactory(): SchemaContextFactoryContract
    {
        return $this->factory;
    }
}
