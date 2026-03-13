<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Schemas;

/**
 * Base class for schemas that resolve a model from a route parameter.
 *
 * Define routeModelKey() to declare which route parameter holds the model.
 * Use model() to access the resolved value. For routes with implicit model
 * binding, model() returns the bound Eloquent model. For scalar route
 * parameters, model() returns the raw value.
 *
 * Always guard with an instanceof check in applies():
 *
 *     public function applies(): bool
 *     {
 *         return $this->model() instanceof Product;
 *     }
 */
abstract class ModelSchema extends BaseSchema
{
    private mixed $resolvedModel = null;

    private bool $modelResolved = false;

    /**
     * The route parameter name that holds the model.
     *
     * Must match the route definition, e.g., 'product' for /products/{product}.
     */
    abstract protected function routeModelKey(): string;

    /**
     * The resolved model from the route parameter.
     *
     * Cached after first resolution; safe to call multiple times.
     */
    public function model(): mixed
    {
        if (! $this->modelResolved) {
            $this->resolvedModel = $this->context->routeParam($this->routeModelKey(), null);
            $this->modelResolved = true;
        }

        return $this->resolvedModel;
    }
}
