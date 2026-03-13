<?php

declare(strict_types=1);

namespace Superinteractive\StructuredData\Support;

use Illuminate\Contracts\Container\Container;
use Superinteractive\StructuredData\Contracts\SchemaContextContract;

class SchemaRunner
{
    public function __construct(
        private readonly Container $container,
        private readonly SchemaClassResolver $schemaClassResolver,
    ) {}

    /**
     * @return array<int, string>
     */
    public function scripts(SchemaContextContract $context): array
    {
        $scripts = [];

        foreach ($this->schemaClassResolver->classes() as $schemaClass) {
            $requiredContext = $schemaClass::contextType();

            if (! $context instanceof $requiredContext) {
                continue;
            }

            $schema = $this->container->make($schemaClass, ['context' => $context]);

            if (! $schema->applies()) {
                continue;
            }

            foreach ($schema->scripts() as $script) {
                if (! is_string($script) || $script === '') {
                    continue;
                }

                $scripts[] = $this->sanitizeScript($script);
            }
        }

        return $scripts;
    }

    private function sanitizeScript(string $script): string
    {
        if (preg_match('/^(<script[^>]*>)(.*?)(<\/script>)$/si', $script, $matches) !== 1) {
            return $script;
        }

        $decoded = json_decode(mb_trim($matches[2]), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $script;
        }

        $safeJson = json_encode(
            $decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        if ($safeJson === false) {
            return $script;
        }

        return $matches[1].$safeJson.$matches[3];
    }
}
