<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface SchemaContextContract
{
    public function routeName(): ?string;

    public function url(): string;

    public function locale(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function routeParams(): array;

    public function routeParam(string $key, mixed $default = null): mixed;
}
