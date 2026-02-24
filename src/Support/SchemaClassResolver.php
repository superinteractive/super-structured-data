<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Support;

use Illuminate\Support\Facades\File;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class SchemaClassResolver
{
    /**
     * @var array<int, class-string<BaseSchema>>|null
     */
    private ?array $resolvedClasses = null;

    /**
     * @return array<int, class-string<BaseSchema>>
     */
    public function classes(): array
    {
        if ($this->resolvedClasses !== null) {
            return $this->resolvedClasses;
        }

        $discovered = $this->discoverSchemaClasses();

        $this->resolvedClasses = array_values(
            array_filter(
                $discovered,
                fn (string $schemaClass): bool => $this->isSchemaClass($schemaClass),
            ),
        );

        return $this->resolvedClasses;
    }

    /**
     * @return array<int, string>
     */
    private function discoverSchemaClasses(): array
    {
        $schemaPath = (string) config('structured-data.schema_path', 'Schemas');
        $schemaDirectory = app_path($schemaPath);

        if (! is_dir($schemaDirectory)) {
            return [];
        }

        $namespace = app()->getNamespace().str_replace(['/', '\\'], '\\', mb_trim($schemaPath, '/\\'));
        $schemaClasses = [];

        foreach (File::allFiles($schemaDirectory) as $file) {
            $relativePath = str_replace($schemaDirectory, '', $file->getPathname());

            if (function_exists('mb_ltrim')) {
                $relativePath = mb_ltrim($relativePath, DIRECTORY_SEPARATOR);
            } else {
                while (str_starts_with($relativePath, DIRECTORY_SEPARATOR)) {
                    $relativePath = mb_substr($relativePath, 1);
                }
            }

            $class = $namespace.'\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
            $schemaClasses[] = $class;
        }

        return $schemaClasses;
    }

    private function isSchemaClass(string $schemaClass): bool
    {
        if ($schemaClass === '' || ! class_exists($schemaClass)) {
            return false;
        }

        return is_subclass_of($schemaClass, BaseSchema::class);
    }
}
