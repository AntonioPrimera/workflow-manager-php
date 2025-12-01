<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Documentation;

use Illuminate\Filesystem\Filesystem;

use function str_replace;

/**
 * Publishes Workflow Manager reference documents into the host project.
 */
class DocumentationGenerator
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $basePath,
        private readonly string $stubPath,
    ) {}

    /**
     * Generates or refreshes documentation artefacts.
     *
     * @param  array<string, string>  $context
     */
    public function generate(array $context): void
    {
        $this->publish('WORKFLOW_MANAGER.md', 'workflow_manager.md.stub', $context);
        $this->publish('agents-instructions.md', 'agents-instructions.md.stub', $context);
    }

    /**
     * Writes the resolved stub to the base path.
     */
    private function publish(string $filename, string $stubName, array $context): void
    {
        $destination = $this->basePath.DIRECTORY_SEPARATOR.$filename;
        $stub = $this->stubPath.DIRECTORY_SEPARATOR.$stubName;

        if (! $this->filesystem->exists($stub)) {
            return;
        }

        $contents = $this->filesystem->get($stub);

        foreach ($context as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        $this->filesystem->put($destination, $contents);
    }
}
