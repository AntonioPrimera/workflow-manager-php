<?php

declare(strict_types=1);

use AntonioPrimera\WorkflowManager\Api\WorkflowManagerClient;
use Illuminate\Support\Facades\Http;

it('lists projects using the Workflow Manager API', function () {
    Http::fake([
        'https://devstream.dev/api/wfm/projects' => Http::response([
            'data' => [
                ['id' => 'proj-1', 'name' => 'Demo'],
                ['id' => 'proj-2', 'name' => 'Second'],
            ],
        ], 200),
    ]);

    $client = new WorkflowManagerClient('https://devstream.dev', 'token-123');

    $projects = $client->listProjects();

    expect($projects)->toHaveCount(2)
        ->and($projects[0]['id'])->toBe('proj-1');
});

it('creates a new project via the API', function () {
    Http::fake([
        'https://devstream.dev/api/wfm/projects' => Http::response([
            'data' => ['id' => 'proj-xyz', 'name' => 'New Project'],
        ], 201),
    ]);

    $client = new WorkflowManagerClient('https://devstream.dev', 'token-123');

    $project = $client->createProject('New Project', [
        'metadata' => ['timezone' => 'UTC'],
    ]);

    expect($project['id'])->toBe('proj-xyz');
});

it('throws when authentication fails', function () {
    Http::fake([
        'https://devstream.dev/api/wfm/projects' => Http::response([], 401),
    ]);

    $client = new WorkflowManagerClient('https://devstream.dev', 'token-123');

    $client->listProjects();
})->throws(\RuntimeException::class, 'Authentication with Workflow Manager failed');
