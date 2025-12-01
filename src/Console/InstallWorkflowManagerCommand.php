<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Console;

use AntonioPrimera\WorkflowManager\Api\WorkflowManagerClient;
use AntonioPrimera\WorkflowManager\Documentation\DocumentationGenerator;
use AntonioPrimera\WorkflowManager\Environment\EnvWriter;
use AntonioPrimera\WorkflowManager\Scaffolding\ScriptBuilder;
use Illuminate\Console\Command;

/**
 * Installs Workflow Manager integration into a Laravel application.
 */
class InstallWorkflowManagerCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'workflow-manager:install
        {--url= : Workflow Manager base URL}
        {--token= : Workflow Manager API token}
        {--project= : Preferred project name}
        {--metadata= : JSON metadata payload to send when creating the project}
        {--force : Overwrite existing artefacts}';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['wm:install'];

    /**
     * @var string
     */
    protected $description = 'Provision or reuse a Workflow Manager project and scaffold local tooling.';

    public function __construct(
        private readonly EnvWriter $envWriter,
        private readonly DocumentationGenerator $documentationGenerator,
        private readonly ScriptBuilder $scriptBuilder,
    ) {
        parent::__construct();
    }

    /**
     * Executes the installer command.
     */
    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('url') ?: 'https://devstream.dev'), '/');
        $token = (string) $this->option('token');
        $projectName = (string) $this->option('project');
        $metadata = $this->parseMetadata((string) $this->option('metadata'));

        if ($token === '' || $projectName === '') {
            $this->error('Both --token and --project options are required in non-interactive mode.');

            return self::FAILURE;
        }

        $client = new WorkflowManagerClient($baseUrl, $token);

        try {
            $projects = $client->listProjects();
        } catch (\RuntimeException $exception) {
            $this->error('Authentication with Workflow Manager failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $matching = $this->findProjectByName($projects, $projectName);

        if ($matching !== null) {
            $projectId = (string) ($matching['id'] ?? '');
            $projectLabel = (string) ($matching['name'] ?? $projectName);
            $this->info('Reusing existing Workflow Manager project: '.$projectLabel);
        } else {
            $payload = [];

            if (! empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $created = $client->createProject($projectName, $payload);
            $projectId = (string) ($created['id'] ?? '');
            $this->info('Provisioned Workflow Manager project: '.$projectName);
        }

        if ($projectId === '') {
            $this->error('Unable to determine project ID from Workflow Manager response.');

            return self::FAILURE;
        }

        $envResult = $this->envWriter->persist($baseUrl, $token, $projectId);

        if ($envResult->wasSuccessful()) {
            $this->info('Stored Workflow Manager credentials in .env');
        } else {
            foreach ($envResult->warnings() as $warning) {
                $this->warn($warning);
            }

            foreach ($envResult->snippets() as $file => $snippet) {
                $this->line($file.' snippet:'.PHP_EOL.$snippet);
            }
        }

        $this->documentationGenerator->generate([
            'base_url' => $baseUrl,
            'project_name' => $projectName,
            'project_id' => $projectId,
        ]);

        $this->scriptBuilder->generate(base_path());
        $this->info('Generated CLI wrappers in .dev-workflow/bin');

        $this->line('Next steps: run ./.dev-workflow/bin/list-workflows to confirm connectivity.');

        return self::SUCCESS;
    }

    /**
     * Attempts to decode metadata from CLI input.
     *
     * @return array<string, mixed>
     */
    private function parseMetadata(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Locates a project by case-insensitive name.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return array<string, mixed>|null
     */
    private function findProjectByName(array $projects, string $name): ?array
    {
        foreach ($projects as $project) {
            $projectName = (string) ($project['name'] ?? '');

            if ($projectName !== '' && strcasecmp($projectName, $name) === 0) {
                return $project;
            }
        }

        return null;
    }
}
