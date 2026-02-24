<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Superinteractive\StructuredData\StructuredDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(app_path('Schemas'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            StructuredDataServiceProvider::class,
        ];
    }
}
