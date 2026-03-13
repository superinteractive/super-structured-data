<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeSchemaCommand extends GeneratorCommand
{
    protected $name = 'make:schema';

    protected $description = 'Create a new schema class';

    protected $type = 'Schema';

    public function handle()
    {
        if ($this->option('model') && $this->option('statamic')) {
            $this->components->error('The --model and --statamic options cannot be used together.');

            return self::FAILURE;
        }

        return parent::handle();
    }

    protected function getStub(): string
    {
        if ($this->option('model')) {
            return __DIR__.'/../../stubs/ModelSchema.stub';
        }

        if ($this->option('statamic')) {
            return __DIR__.'/../../stubs/StatamicSchema.stub';
        }

        return __DIR__.'/../../stubs/Schema.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $schemaPath = config('structured-data.schema_path', 'Schemas');

        if (! is_string($schemaPath)) {
            $schemaPath = 'Schemas';
        }

        $namespaceSuffix = str_replace(['/', '\\'], '\\', mb_trim($schemaPath, '/\\'));

        if ($namespaceSuffix === '') {
            return mb_rtrim((string) $rootNamespace, '\\');
        }

        return mb_rtrim((string) $rootNamespace, '\\').'\\'.$namespaceSuffix;
    }

    protected function qualifyClass($name): string
    {
        $name = (string) $name;

        if (! str_ends_with($name, 'Schema')) {
            $name .= 'Schema';
        }

        return parent::qualifyClass($name);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['model', 'm', InputOption::VALUE_NONE, 'Create a model-bound schema (extends ModelSchema)'],
            ['statamic', 's', InputOption::VALUE_NONE, 'Create a Statamic schema (extends StatamicSchema)'],
        ]);
    }
}
