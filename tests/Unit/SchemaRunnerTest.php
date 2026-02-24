<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Superinteractive\StructuredData\Contexts\LaravelSchemaContext;
use Superinteractive\StructuredData\Support\SchemaRunner;

it('returns scripts for applicable schemas in discovered order', function (): void {
    $schemaPath = 'Schemas/Runner';
    $directory = app_path($schemaPath);
    $appNamespace = mb_rtrim(app()->getNamespace(), '\\');
    $schemaNamespace = $appNamespace.'\\Schemas\\Runner';

    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    try {
        File::put($directory.'/ASecondSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class ASecondSchema extends BaseSchema
{
    public function applies(): bool
    {
        return true;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('second')->toScript()];
    }
}
PHP);

        File::put($directory.'/BSkippedSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class BSkippedSchema extends BaseSchema
{
    public function applies(): bool
    {
        return false;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('skipped')->toScript()];
    }
}
PHP);

        File::put($directory.'/CFirstSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class CFirstSchema extends BaseSchema
{
    public function applies(): bool
    {
        return true;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('first')->toScript()];
    }
}
PHP);

        require_once $directory.'/ASecondSchema.php';
        require_once $directory.'/BSkippedSchema.php';
        require_once $directory.'/CFirstSchema.php';

        config()->set('structured-data.schema_path', $schemaPath);

        $runner = app(SchemaRunner::class);

        $scripts = $runner->scripts(new LaravelSchemaContext(
            routeName: 'home',
            collection: 'pages',
            blueprint: 'home',
            url: 'https://example.test',
            locale: 'en',
        ));

        expect($scripts)->toHaveCount(2)
            ->and($scripts[0])->toContain('"name":"second"')
            ->and($scripts[1])->toContain('"name":"first"');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('sanitizes script-tag breakout payloads in schema scripts', function (): void {
    $schemaPath = 'Schemas/Security';
    $directory = app_path($schemaPath);
    $appNamespace = mb_rtrim(app()->getNamespace(), '\\');
    $schemaNamespace = $appNamespace.'\\Schemas\\Security';

    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    try {
        File::put($directory.'/MaliciousSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class MaliciousSchema extends BaseSchema
{
    public function applies(): bool
    {
        return true;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('</script><script>alert(1)</script>')->toScript()];
    }
}
PHP);

        require_once $directory.'/MaliciousSchema.php';

        config()->set('structured-data.schema_path', $schemaPath);

        $runner = app(SchemaRunner::class);

        $scripts = $runner->scripts(new LaravelSchemaContext(
            routeName: 'home',
            collection: 'pages',
            blueprint: 'home',
            url: 'https://example.test',
            locale: 'en',
        ));

        expect($scripts)->toHaveCount(1)
            ->and($scripts[0])->not->toContain('</script><script>alert(1)</script>')
            ->and($scripts[0])->not->toContain('</script><script>')
            ->and($scripts[0])->toContain('\u003C/script\u003E\u003Cscript\u003Ealert(1)\u003C/script\u003E');
    } finally {
        File::deleteDirectory($directory);
    }
});
