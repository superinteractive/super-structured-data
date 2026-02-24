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
