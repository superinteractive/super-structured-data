<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('creates a schema file in app schemas', function (): void {
    $name = 'PackageGenerated';
    $className = $name.'Schema';
    $path = app_path('Schemas/'.$className.'.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    Artisan::call('make:schema', ['name' => $name]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('class '.$className.' extends BaseSchema');

    File::delete($path);
});

it('creates a schema file in the configured schema path', function (): void {
    $schemaPath = 'Schemas/Generated';
    $name = 'NestedPath';
    $className = $name.'Schema';
    $path = app_path($schemaPath.'/'.$className.'.php');
    $directory = app_path('Schemas/Generated');

    File::deleteDirectory($directory);
    config()->set('structured-data.schema_path', $schemaPath);

    Artisan::call('make:schema', ['name' => $name]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('namespace App\\Schemas\\Generated;')
        ->and($contents)->toContain('class '.$className.' extends BaseSchema');

    File::deleteDirectory($directory);
});

it('creates a model schema with --model flag', function (): void {
    $name = 'ProductGenerated';
    $className = $name.'Schema';
    $path = app_path('Schemas/'.$className.'.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    Artisan::call('make:schema', ['name' => $name, '--model' => true]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('class '.$className.' extends ModelSchema')
        ->and($contents)->toContain('protected function routeModelKey(): string');

    File::delete($path);
});

it('creates a statamic schema with --statamic flag', function (): void {
    $name = 'ArticleGenerated';
    $className = $name.'Schema';
    $path = app_path('Schemas/'.$className.'.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    Artisan::call('make:schema', ['name' => $name, '--statamic' => true]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('class '.$className.' extends StatamicSchema');

    File::delete($path);
});

it('rejects using --model and --statamic together', function (): void {
    $name = 'ConflictedGenerated';
    $path = app_path('Schemas/'.$name.'Schema.php');

    if (File::exists($path)) {
        File::delete($path);
    }

    $exitCode = Artisan::call('make:schema', [
        'name' => $name,
        '--model' => true,
        '--statamic' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('cannot be used together')
        ->and(File::exists($path))->toBeFalse();
});

it('normalizes configured schema path slashes before building the namespace', function (): void {
    $schemaPath = '/Schemas/Generated\\';
    $name = 'SlashTrimmed';
    $className = $name.'Schema';
    $path = app_path('Schemas/Generated/'.$className.'.php');
    $directory = app_path('Schemas/Generated');

    File::deleteDirectory($directory);
    config()->set('structured-data.schema_path', $schemaPath);

    Artisan::call('make:schema', ['name' => $name]);

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('namespace App\\Schemas\\Generated;')
        ->and($contents)->toContain('class '.$className.' extends BaseSchema');

    File::deleteDirectory($directory);
});
