<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface SchemaContextFactoryContract
{
    public function make(): SchemaContextContract;
}
