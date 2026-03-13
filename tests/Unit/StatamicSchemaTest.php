<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contracts\StatamicContextContract;
use Superinteractive\StructuredData\Schemas\StatamicSchema;

it('exposes contextType as StatamicContextContract for StatamicSchema', function (): void {
    expect(StatamicSchema::contextType())->toBe(StatamicContextContract::class);
});
