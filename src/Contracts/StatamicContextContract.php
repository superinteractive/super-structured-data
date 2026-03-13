<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

interface StatamicContextContract extends SchemaContextContract
{
    /**
     * The auto-resolved Statamic content item (Entry or Page).
     *
     * @return \Statamic\Contracts\Entries\Entry|\Statamic\Structures\Page|null
     */
    public function source(): mixed;

    public function collection(): ?string;

    public function blueprint(): ?string;
}
