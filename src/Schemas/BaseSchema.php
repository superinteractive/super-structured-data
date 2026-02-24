<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

use Superinteractive\StructuredData\Contracts\SchemaContextContract;

/**
 * Base class for all Structured Data schema definitions.
 *
 * Instructions for LLMs and AI coding tools:
 * - Always extend this class for new schema implementations.
 * - Use only `$this->context` to decide whether a schema applies to the
 *   current request/page (`routeName`, `collection`, `blueprint`, `url`,
 *   `locale`, `entry`, `page`).
 * - Keep matching logic inside the concrete schema class (`applies()`).
 * - Keep each concrete schema focused on a single schema concern.
 * - Return deterministic script arrays from `scripts()`.
 * - Avoid side effects inside schema classes.
 */
abstract class BaseSchema
{
    public function __construct(
        protected readonly SchemaContextContract $context,
    ) {}

    abstract public function applies(): bool;

    /**
     * @return array<int, string>
     */
    abstract public function scripts(): array;
}
