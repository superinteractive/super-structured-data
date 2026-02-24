<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Superinteractive\StructuredData\Support\SchemaClassResolver;

it('discovers schema classes from configured schema path and filters non-schema classes', function (): void {
    $schemaPath = 'Schemas/Resolver';
    $directory = app_path($schemaPath);
    $appNamespace = mb_rtrim(app()->getNamespace(), '\\');
    $schemaNamespace = $appNamespace.'\\Schemas\\Resolver';

    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    try {
        File::put($directory.'/AResolverSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Superinteractive\StructuredData\Schemas\BaseSchema;

class AResolverSchema extends BaseSchema
{
    public function applies(): bool
    {
        return false;
    }

    public function scripts(): array
    {
        return [];
    }
}
PHP);

        File::put($directory.'/NotResolverSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

class NotResolverSchema
{
}
PHP);

        File::put($directory.'/ZResolverSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Superinteractive\StructuredData\Schemas\BaseSchema;

class ZResolverSchema extends BaseSchema
{
    public function applies(): bool
    {
        return false;
    }

    public function scripts(): array
    {
        return [];
    }
}
PHP);

        require_once $directory.'/AResolverSchema.php';
        require_once $directory.'/NotResolverSchema.php';
        require_once $directory.'/ZResolverSchema.php';

        config()->set('structured-data.schema_path', $schemaPath);

        $resolver = app(SchemaClassResolver::class);

        expect($resolver->classes())->toBe([
            $schemaNamespace.'\\AResolverSchema',
            $schemaNamespace.'\\ZResolverSchema',
        ]);
    } finally {
        File::deleteDirectory($directory);
    }
});
