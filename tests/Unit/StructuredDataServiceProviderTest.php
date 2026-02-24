<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Superinteractive\StructuredData\Commands\MakeSchemaCommand;
use Superinteractive\StructuredData\StructuredDataServiceProvider;

it('registers publish paths for config and homepage schema stubs', function (): void {
    /** @var array<string, string> $configPublishPaths */
    $configPublishPaths = ServiceProvider::pathsToPublish(StructuredDataServiceProvider::class, 'structured-data-config');

    /** @var array<string, string> $schemaPublishPaths */
    $schemaPublishPaths = ServiceProvider::pathsToPublish(StructuredDataServiceProvider::class, 'structured-data-homepage-schemas');

    expect($configPublishPaths)->not->toBeEmpty()
        ->and($schemaPublishPaths)->not->toBeEmpty();
});

it('registers the make schema command', function (): void {
    $commands = app('Illuminate\\Contracts\\Console\\Kernel')->all();

    expect($commands)->toHaveKey('make:schema')
        ->and($commands['make:schema'])->toBeInstanceOf(MakeSchemaCommand::class);
});
