<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;

/**
 * Base class for all Structured Data schema definitions.
 *
 * Extend this class directly for schemas that only need route-level
 * context (routeName, url, locale, routeParams). For schemas that
 * need a route-bound model, extend ModelSchema instead. For Statamic
 * schemas, extend StatamicSchema.
 */
abstract class BaseSchema
{
    public function __construct(
        protected readonly SchemaContextContract $context,
    ) {}

    /**
     * @return class-string<SchemaContextContract>
     */
    public static function contextType(): string
    {
        return SchemaContextContract::class;
    }

    abstract public function applies(): bool;

    /**
     * @return array<int, string>
     */
    abstract public function scripts(): array;
}
