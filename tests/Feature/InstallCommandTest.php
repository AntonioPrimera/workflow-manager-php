<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('registers the install command', function () {
    $commands = $this->app['Illuminate\\Contracts\\Console\\Kernel']->all();

    expect($commands)->toHaveKey('workflow-manager:install')
        ->and($commands)->toHaveKey('wm:install');
});

it('provisions a new project when none exist and scaffolds tooling', function () {
    $this->ensureEnvFiles();

    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $requests[] = [$request->method(), $request->url(), $request->body()];

        if ($request->method() === 'GET') {
            return Http::response(['data' => []], 200);
        }

        if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/wfm/projects')) {
            return Http::response([
                'data' => [
                    'id' => 'proj-123',
                    'name' => 'Demo Project',
                ],
            ], 201);
        }

        return Http::response([], 404);
    });

    $this->artisan('workflow-manager:install', [
        '--url' => 'https://devstream.dev',
        '--token' => 'test-token',
        '--project' => 'Demo Project',
        '--metadata' => '{"timezone":"UTC"}',
        '--force' => true,
    ])->expectsOutputToContain('Provisioned Workflow Manager project: Demo Project')
        ->expectsOutputToContain('Stored Workflow Manager credentials in .env')
        ->expectsOutputToContain('Generated CLI wrappers in .dev-workflow/bin')
        ->assertExitCode(0);

    $env = $this->filesystem->get($this->workspacePath('.env'));
    expect($env)->toContain('WORKFLOW_MANAGER_URL=https://devstream.dev')
        ->toContain('WORKFLOW_MANAGER_API_KEY=test-token')
        ->toContain('WORKFLOW_MANAGER_PROJECT_ID=proj-123');

    $envExample = $this->filesystem->get($this->workspacePath('.env.example'));
    expect($envExample)->toContain('WORKFLOW_MANAGER_URL=')
        ->toContain('WORKFLOW_MANAGER_API_KEY=')
        ->toContain('WORKFLOW_MANAGER_PROJECT_ID=');

    expect($this->filesystem->exists($this->workspacePath('WORKFLOW_MANAGER.md')))->toBeTrue();
    expect($this->filesystem->exists($this->workspacePath('agents-instructions.md')))->toBeTrue();

    $scripts = [
        'create-project',
        'list-projects',
        'create-workflow',
        'list-workflows',
        'get-workflow',
        'create-step',
        'list-steps',
        'update-step-status',
        'create-document',
        'get-document',
        'update-document',
        '_shared.sh',
    ];

    foreach ($scripts as $script) {
        expect($this->filesystem->exists($this->workspacePath('.dev-workflow/bin/'.$script)))->toBeTrue();
    }

    expect($requests)->toHaveCount(2);
    expect($requests[0][0])->toBe('GET');
    expect($requests[1][0])->toBe('POST');
});

it('reuses an existing project when the name matches', function () {
    $this->ensureEnvFiles();

    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $requests[] = [$request->method(), $request->url(), $request->body()];

        if ($request->method() === 'GET') {
            return Http::response([
                'data' => [
                    ['id' => 'proj-abc', 'name' => 'Demo Project'],
                ],
            ], 200);
        }

        throw new \RuntimeException('POST should not be called when project already exists.');
    });

    $this->artisan('wm:install', [
        '--url' => 'https://devstream.dev',
        '--token' => 'test-token',
        '--project' => 'Demo Project',
        '--force' => true,
    ])->expectsOutputToContain('Reusing existing Workflow Manager project: Demo Project')
        ->expectsOutputToContain('Stored Workflow Manager credentials in .env')
        ->assertExitCode(0);

    $env = $this->filesystem->get($this->workspacePath('.env'));
    expect($env)->toContain('WORKFLOW_MANAGER_PROJECT_ID=proj-abc');

    expect($requests)->toHaveCount(1);
    expect($requests[0][0])->toBe('GET');
});

it('fails fast when the API token is invalid', function () {
    $this->ensureEnvFiles();

    Http::fake(fn () => Http::response([], 401));

    $this->artisan('workflow-manager:install', [
        '--url' => 'https://devstream.dev',
        '--token' => 'bad-token',
        '--project' => 'Demo Project',
        '--force' => true,
    ])->expectsOutputToContain('Authentication with Workflow Manager failed')
        ->assertExitCode(1);
});
