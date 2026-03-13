<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contexts;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;

final readonly class LaravelContext implements SchemaContextContract
{
    /**
     * @param  array<string, mixed>  $routeParams
     */
    public function __construct(
        public ?string $routeName,
        public string $url,
        public ?string $locale,
        public array $routeParams = [],
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
}
