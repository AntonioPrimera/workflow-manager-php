<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Environment;

/**
 * Carries the outcome of persisting Workflow Manager environment settings.
 */
class EnvWriteResult
{
    /**
     * @param  array<int, string>  $warnings
     * @param  array<string, string>  $snippets
     */
    public function __construct(
        private readonly bool $successful,
        private readonly array $warnings = [],
        private readonly array $snippets = [],
    ) {}

    /**
     * Indicates whether all writes were successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Returns any warnings encountered during persistence.
     *
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Provides copy-paste snippets when direct writes were not possible.
     *
     * @return array<string, string>
     */
    public function snippets(): array
    {
        return $this->snippets;
    }
}
