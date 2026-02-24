<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Superinteractive\StructuredData\Support\ContextFactoryResolver;
use Superinteractive\StructuredData\Support\SchemaRunner;

class StructuredData extends Component
{
    /**
     * @param  array<int, mixed>  $breadcrumbs
     */
    public function __construct(
        private readonly ContextFactoryResolver $contextFactoryResolver,
        private readonly SchemaRunner $schemaRunner,
        public mixed $entry = null,
        public array $breadcrumbs = [],
    ) {}

    public function render(): View|Closure|string
    {
        if (! (bool) config('structured-data.enabled', true)) {
            return view('structured-data::components.structured-data', ['scripts' => []]);
        }

        $context = $this->contextFactoryResolver->make($this->entry);
        $scripts = $this->schemaRunner->scripts($context);

        return view('structured-data::components.structured-data', ['scripts' => $scripts]);
    }
}
