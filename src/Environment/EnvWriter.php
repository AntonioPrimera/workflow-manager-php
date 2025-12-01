<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Environment;

use Illuminate\Filesystem\Filesystem;

use function collect;

/**
 * Persists Workflow Manager configuration into host project environment files.
 */
class EnvWriter
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $basePath,
    ) {}

    /**
     * Writes Workflow Manager values into .env and .env.example.
     */
    public function persist(string $url, string $token, string $projectId): EnvWriteResult
    {
        $warnings = [];
        $snippets = [];

        $envPath = $this->path('.env');
        $examplePath = $this->path('.env.example');

        $this->ensureFileExists($envPath);
        $this->ensureFileExists($examplePath);

        if ($this->isWritable($envPath)) {
            $this->writeValues($envPath, [
                'WORKFLOW_MANAGER_URL' => $url,
                'WORKFLOW_MANAGER_API_KEY' => $token,
                'WORKFLOW_MANAGER_PROJECT_ID' => $projectId,
            ]);
        } else {
            $warnings[] = '.env is not writable. Please update it manually.';
            $snippets['.env'] = $this->snippet([
                'WORKFLOW_MANAGER_URL' => $url,
                'WORKFLOW_MANAGER_API_KEY' => $token,
                'WORKFLOW_MANAGER_PROJECT_ID' => $projectId,
            ]);
        }

        if ($this->isWritable($examplePath)) {
            $this->writeValues($examplePath, [
                'WORKFLOW_MANAGER_URL' => '',
                'WORKFLOW_MANAGER_API_KEY' => '',
                'WORKFLOW_MANAGER_PROJECT_ID' => '',
            ]);
        } else {
            $warnings[] = '.env.example is not writable. Please update it manually.';
            $snippets['.env.example'] = $this->snippet([
                'WORKFLOW_MANAGER_URL' => '',
                'WORKFLOW_MANAGER_API_KEY' => '',
                'WORKFLOW_MANAGER_PROJECT_ID' => '',
            ]);
        }

        return new EnvWriteResult(count($warnings) === 0, $warnings, $snippets);
    }

    /**
     * Ensures a file exists before attempting to write to it.
     */
    private function ensureFileExists(string $path): void
    {
        if (! $this->filesystem->exists($path)) {
            $this->filesystem->ensureDirectoryExists(dirname($path));
            $this->filesystem->put($path, '');
        }
    }

    /**
     * Determines if a file is writable.
     */
    private function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Writes or updates the given key/value pairs in the target file.
     *
     * @param  array<string, string>  $values
     */
    private function writeValues(string $path, array $values): void
    {
        $content = $this->filesystem->exists($path)
            ? rtrim($this->filesystem->get($path))
            : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, $line, $content);
            } else {
                $content = trim($content) !== ''
                    ? $content.PHP_EOL.$line
                    : $line;
            }
        }

        $content = rtrim($content).PHP_EOL;

        $this->filesystem->put($path, $content);
    }

    /**
     * Builds a snippet string for manual insertion.
     *
     * @param  array<string, string>  $values
     */
    private function snippet(array $values): string
    {
        return collect($values)
            ->map(fn (string $value, string $key) => $key.'='.$value)
            ->implode(PHP_EOL);
    }

    /**
     * Resolves a path relative to the project base path.
     */
    private function path(string $relative): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR);
    }
}
