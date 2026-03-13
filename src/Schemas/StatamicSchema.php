<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

use Superinteractive\StructuredData\Contracts\StatamicContextContract;

/**
 * Base class for Statamic schemas.
 *
 * Provides access to the auto-resolved Statamic source (Entry or Page),
 * plus collection and blueprint. Schemas extending this class are
 * automatically skipped in non-Statamic environments.
 */
abstract class StatamicSchema extends BaseSchema
{
    public function __construct(
        StatamicContextContract $context,
    ) {
        parent::__construct($context);
    }

    /**
     * @return class-string<StatamicContextContract>
     */
    public static function contextType(): string
    {
        return StatamicContextContract::class;
    }
}
