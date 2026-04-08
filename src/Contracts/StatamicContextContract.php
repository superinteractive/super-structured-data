<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Contracts;

use Statamic\Contracts\Entries\Entry;
use Statamic\Structures\Page;

interface StatamicContextContract extends SchemaContextContract
{
    /**
     * The auto-resolved Statamic content item (Entry or Page).
     *
     * @return Entry|Page|null
     */
    public function source(): mixed;

    public function collection(): ?string;

    public function blueprint(): ?string;
}
