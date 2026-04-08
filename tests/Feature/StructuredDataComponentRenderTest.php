<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

it('renders the structured data blade component alias', function (): void {
    $schemaPath = 'Schemas/BladeRender';
    $directory = app_path($schemaPath);
    $schemaNamespace = rtrim(app()->getNamespace(), '\\').'\\Schemas\\BladeRender';

    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);

    try {
        File::put($directory.'/HomeSchema.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$schemaNamespace};

use Spatie\SchemaOrg\Schema;
use Superinteractive\StructuredData\Schemas\BaseSchema;

class HomeSchema extends BaseSchema
{
    public function applies(): bool
    {
        return true;
    }

    public function scripts(): array
    {
        return [Schema::webSite()->name('blade-render')->toScript()];
    }
}
PHP);

        require_once $directory.'/HomeSchema.php';

        config()->set('structured-data.schema_path', $schemaPath);

        $html = Blade::render('<x-structured-data />');

        expect($html)->toContain('application/ld+json')
            ->and($html)->toContain('"name":"blade-render"');
    } finally {
        File::deleteDirectory($directory);
    }
});
