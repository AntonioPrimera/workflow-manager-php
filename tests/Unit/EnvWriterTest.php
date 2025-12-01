<?php

declare(strict_types=1);

use AntonioPrimera\WorkflowManager\Environment\EnvWriter;
use Illuminate\Filesystem\Filesystem;

it('writes workflow manager credentials into env files', function () {
    $filesystem = new Filesystem;
    $workspace = sys_get_temp_dir().'/'.uniqid('env-writer-', true);
    $filesystem->ensureDirectoryExists($workspace);

    $filesystem->put($workspace.'/.env', "APP_NAME=Laravel\n");
    $filesystem->put($workspace.'/.env.example', "APP_NAME=Laravel\nWORKFLOW_MANAGER_URL=\nWORKFLOW_MANAGER_API_KEY=\nWORKFLOW_MANAGER_PROJECT_ID=\n");

    $writer = new EnvWriter($filesystem, $workspace);

    $result = $writer->persist(
        url: 'https://devstream.dev',
        token: 'secret-token',
        projectId: 'proj-123',
    );

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->warnings())->toBeEmpty();

    $env = $filesystem->get($workspace.'/.env');
    expect($env)->toContain('APP_NAME=Laravel')
        ->toContain('WORKFLOW_MANAGER_URL=https://devstream.dev')
        ->toContain('WORKFLOW_MANAGER_API_KEY=secret-token')
        ->toContain('WORKFLOW_MANAGER_PROJECT_ID=proj-123');

    $example = $filesystem->get($workspace.'/.env.example');
    expect($example)->toContain('WORKFLOW_MANAGER_URL=')
        ->toContain('WORKFLOW_MANAGER_API_KEY=')
        ->toContain('WORKFLOW_MANAGER_PROJECT_ID=');

    $filesystem->deleteDirectory($workspace);
});

it('returns warnings and snippets when env files are not writable', function () {
    $filesystem = new Filesystem;
    $workspace = sys_get_temp_dir().'/'.uniqid('env-writer-', true);
    $filesystem->ensureDirectoryExists($workspace);

    $filesystem->put($workspace.'/.env', "APP_NAME=Laravel\n");
    $filesystem->put($workspace.'/.env.example', "APP_NAME=Laravel\n");

    chmod($workspace.'/.env', 0444);

    $writer = new EnvWriter($filesystem, $workspace);

    $result = $writer->persist(
        url: 'https://devstream.dev',
        token: 'secret-token',
        projectId: 'proj-123',
    );

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->warnings())->not->toBeEmpty()
        ->and($result->snippets())->toHaveKey('.env');

    chmod($workspace.'/.env', 0644);
    $filesystem->deleteDirectory($workspace);
});
