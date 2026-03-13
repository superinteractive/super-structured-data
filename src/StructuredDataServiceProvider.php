<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Superinteractive\StructuredData\Commands\MakeSchemaCommand;
use Superinteractive\StructuredData\Contracts\SchemaContextFactoryContract;
use Superinteractive\StructuredData\Factories\LaravelSchemaContextFactory;
use Superinteractive\StructuredData\Factories\StatamicSchemaContextFactory;
use Superinteractive\StructuredData\Support\ContextFactoryResolver;
use Superinteractive\StructuredData\Support\SchemaClassResolver;
use Superinteractive\StructuredData\Support\SchemaRunner;
use Superinteractive\StructuredData\View\Components\StructuredData as StructuredDataComponent;

class StructuredDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/structured-data.php'), 'structured-data');

        $this->app->singleton(SchemaContextFactoryContract::class, $this->defaultContextFactoryClass());
        $this->app->singleton(SchemaClassResolver::class, static fn (): SchemaClassResolver => new SchemaClassResolver);
        $this->app->singleton(ContextFactoryResolver::class, fn (): ContextFactoryResolver => new ContextFactoryResolver(
            $this->app->make(SchemaContextFactoryContract::class),
        ));
        $this->app->singleton(SchemaRunner::class, fn (): SchemaRunner => new SchemaRunner($this->app, $this->app->make(SchemaClassResolver::class)));
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->packagePath('resources/views'), 'structured-data');
        Blade::component('structured-data', StructuredDataComponent::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->packagePath('config/structured-data.php') => config_path('structured-data.php'),
            ], 'structured-data-config');

            $this->publishes($this->homepageSchemaPublishPaths(), 'structured-data-homepage-schemas');

            $this->commands([
                MakeSchemaCommand::class,
            ]);
        }
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__).'/'.$path;
    }

    /**
     * @return array<string, string>
     */
    private function homepageSchemaPublishPaths(): array
    {
        $schemaDirectory = $this->schemaDirectory();

        return [
            $this->packagePath('stubs/HomepageOrganizationSchema.php.stub') => $schemaDirectory.'/HomepageOrganizationSchema.php',
            $this->packagePath('stubs/HomepageWebsiteSchema.php.stub') => $schemaDirectory.'/HomepageWebsiteSchema.php',
        ];
    }

    /**
     * @return class-string<SchemaContextFactoryContract>
     */
    private function defaultContextFactoryClass(): string
    {
        if (class_exists('Statamic\\Statamic')) {
            return StatamicSchemaContextFactory::class;
        }

        return LaravelSchemaContextFactory::class;
    }

    private function schemaDirectory(): string
    {
        $schemaPath = config('structured-data.schema_path', 'Schemas');

        if (! is_string($schemaPath)) {
            $schemaPath = 'Schemas';
        }

        $schemaPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, mb_trim($schemaPath, '/\\'));

        if ($schemaPath === '') {
            return app_path();
        }

        return app_path($schemaPath);
    }
}
