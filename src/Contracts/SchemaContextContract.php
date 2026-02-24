<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface SchemaContextContract
{
    public function routeName(): ?string;

    public function collection(): ?string;

    public function blueprint(): ?string;

    public function url(): string;

    public function locale(): ?string;

    public function entry(): mixed;

    public function page(): mixed;
}
