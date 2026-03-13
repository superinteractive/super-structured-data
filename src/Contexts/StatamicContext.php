<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contexts;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Structures\Page;
use Superinteractive\StructuredData\Contracts\StatamicContextContract;

final readonly class StatamicContext implements StatamicContextContract
{
    /**
     * @param  array<string, mixed>  $routeParams
     */
    public function __construct(
        public ?string $routeName,
        public string $url,
        public ?string $locale,
        public array $routeParams = [],
        public EntryContract|Page|null $source = null,
        public ?string $collection = null,
        public ?string $blueprint = null,
    ) {}

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function source(): EntryContract|Page|null
    {
        return $this->source;
    }

    public function collection(): ?string
    {
        return $this->collection;
    }

    public function blueprint(): ?string
    {
        return $this->blueprint;
    }
}
