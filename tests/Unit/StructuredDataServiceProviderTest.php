<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Superinteractive\StructuredData\Commands\MakeSchemaCommand;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\StructuredDataServiceProvider;

it('registers publish paths for config and homepage schema stubs', function (): void {
    /** @var array<string, string> $configPublishPaths */
    $configPublishPaths = ServiceProvider::pathsToPublish(StructuredDataServiceProvider::class, 'structured-data-config');

    /** @var array<string, string> $schemaPublishPaths */
    $schemaPublishPaths = ServiceProvider::pathsToPublish(StructuredDataServiceProvider::class, 'structured-data-homepage-schemas');

    expect($configPublishPaths)->not->toBeEmpty()
        ->and($schemaPublishPaths)->not->toBeEmpty();
});

it('publishes homepage schema stubs into the configured schema path', function (): void {
    config()->set('structured-data.schema_path', 'Content/Schemas');

    (new StructuredDataServiceProvider(app()))->boot();

    /** @var array<string, string> $schemaPublishPaths */
    $schemaPublishPaths = ServiceProvider::pathsToPublish(StructuredDataServiceProvider::class, 'structured-data-homepage-schemas');

    expect(array_values($schemaPublishPaths))->toContain(
        app_path('Content/Schemas/HomepageOrganizationSchema.php'),
        app_path('Content/Schemas/HomepageWebsiteSchema.php'),
    );
});

it('binds the default context factory contract', function (): void {
    expect(app(SchemaContextFactoryContract::class))->toBeInstanceOf(LaravelSchemaContextFactory::class);
});

it('registers the make schema command', function (): void {
    $commands = app('Illuminate\\Contracts\\Console\\Kernel')->all();

    expect($commands)->toHaveKey('make:schema')
        ->and($commands['make:schema'])->toBeInstanceOf(MakeSchemaCommand::class);
});
