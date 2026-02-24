<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeSchemaCommand extends GeneratorCommand
{
    protected $name = 'make:schema';

    protected $description = 'Create a new schema class';

    protected $type = 'Schema';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/Schema.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $schemaPath = (string) config('structured-data.schema_path', 'Schemas');
        $namespaceSuffix = str_replace(['/', '\\'], '\\', mb_trim($schemaPath, '/\\'));

        if ($namespaceSuffix === '') {
            return mb_rtrim($rootNamespace, '\\');
        }

        return mb_rtrim($rootNamespace, '\\').'\\'.$namespaceSuffix;
    }

    protected function qualifyClass($name): string
    {
        if (! str_ends_with($name, 'Schema')) {
            $name .= 'Schema';
        }

        return parent::qualifyClass($name);
    }
}
