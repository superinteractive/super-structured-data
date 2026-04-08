<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Contracts\Entries\EntryRepository;
use Statamic\Facades\Site;
use Statamic\Statamic;
use Superinteractive\StructuredData\Contexts\StatamicContext;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\StatamicSchemaContextFactory;

beforeEach(function (): void {
    if (! class_exists(Statamic::class)) {
        $this->markTestSkipped('Statamic is not installed.');
    }

    config()->set('filesystems.disks.standard', [
        'driver' => 'local',
        'root' => base_path(),
    ]);
});

it('switches to the statamic context factory when statamic is installed', function (): void {
    expect(app(SchemaContextFactoryContract::class))->toBeInstanceOf(StatamicSchemaContextFactory::class);
});

it('builds a statamic context when no content item can be resolved', function (): void {
    Site::shouldReceive('current')->andReturn(null);

    $entries = Mockery::mock(EntryRepository::class);
    $entries->shouldReceive('findByUri')->once()->andReturn(null);
    app()->instance(EntryRepository::class, $entries);

    $request = Request::create('/missing');
    app()->instance('request', $request);

    $context = app(StatamicSchemaContextFactory::class)->make();

    expect($context)->toBeInstanceOf(StatamicContext::class)
        ->and($context->collection())->toBeNull()
        ->and($context->blueprint())->toBeNull();
});
