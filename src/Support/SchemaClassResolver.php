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
        $normalizedSchemaPath = trim($schemaPath, '/\\');
        $schemaDirectory = app_path($normalizedSchemaPath);

        if (! is_dir($schemaDirectory)) {
            return [];
        }

        $namespace = app()->getNamespace().str_replace(['/', '\\'], '\\', $normalizedSchemaPath);
        $schemaClasses = [];

        foreach (File::allFiles($schemaDirectory) as $file) {
            $relativePath = ltrim(str_replace($schemaDirectory, '', $file->getPathname()), DIRECTORY_SEPARATOR);

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
