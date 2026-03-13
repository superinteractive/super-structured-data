<?php

declare(strict_types=1);

use Superinteractive\StructuredData\Contexts\LaravelContext;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;
use Superinteractive\StructuredData\Schemas\BaseSchema;
use Superinteractive\StructuredData\Schemas\ModelSchema;

it('resolves model from route param via routeModelKey', function (): void {
    $fakeProduct = new stdClass;
    $fakeProduct->name = 'Widget';

    $context = new LaravelContext(
        routeName: 'products.show',
        url: 'https://example.test/products/1',
        locale: 'en',
        routeParams: ['product' => $fakeProduct],
    );

    $schema = new class($context) extends ModelSchema
    {
        protected function routeModelKey(): string
        {
            return 'product';
        }

        public function applies(): bool
        {
            return $this->model() instanceof stdClass;
        }

        public function scripts(): array
        {
            return ['<script type="application/ld+json">{"name":"'.$this->model()->name.'"}</script>'];
        }
    };

    expect($schema->applies())->toBeTrue()
        ->and($schema->scripts()[0])->toContain('"name":"Widget"');
});

it('returns null model when route param is missing', function (): void {
    $context = new LaravelContext(
        routeName: 'home',
        url: 'https://example.test',
        locale: 'en',
    );

    $schema = new class($context) extends ModelSchema
    {
        protected function routeModelKey(): string
        {
            return 'product';
        }

        public function applies(): bool
        {
            return $this->model() instanceof stdClass;
        }

        public function scripts(): array
        {
            return [];
        }
    };

    expect($schema->applies())->toBeFalse()
        ->and($schema->model())->toBeNull();
});

it('caches model resolution across multiple calls', function (): void {
    $context = \Mockery::mock(SchemaContextContract::class);
    $context->shouldReceive('routeParam')
        ->with('item', null)
        ->once()
        ->andReturn('resolved-value');

    $schema = new class($context) extends ModelSchema
    {
        protected function routeModelKey(): string
        {
            return 'item';
        }

        public function applies(): bool
        {
            return true;
        }

        public function scripts(): array
        {
            return [];
        }
    };

    $schema->model();
    $schema->model();
    $result = $schema->model();

    expect($result)->toBe('resolved-value');
});

it('exposes contextType as SchemaContextContract for BaseSchema', function (): void {
    expect(BaseSchema::contextType())->toBe(SchemaContextContract::class);
});

it('exposes contextType as SchemaContextContract for ModelSchema', function (): void {
    expect(ModelSchema::contextType())->toBe(SchemaContextContract::class);
});
