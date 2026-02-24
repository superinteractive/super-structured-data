<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contexts;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;

final readonly class LaravelSchemaContext implements SchemaContextContract
{
    /**
     * @param  array<string, mixed>  $requestData
     */
    public function __construct(
        public ?string $routeName,
        public ?string $collection,
        public ?string $blueprint,
        public string $url,
        public ?string $locale,
        public mixed $entry = null,
        public mixed $page = null,
        public array $requestData = [],
    ) {}

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function collection(): ?string
    {
        return $this->collection;
    }

    public function blueprint(): ?string
    {
        return $this->blueprint;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function entry(): mixed
    {
        return $this->entry;
    }

    public function page(): mixed
    {
        return $this->page;
    }
}
