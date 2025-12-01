<?php

declare(strict_types=1);

use AntonioPrimera\WorkflowManager\Scaffolding\ScriptBuilder;
use Illuminate\Filesystem\Filesystem;

it('generates wrapper scripts for the Workflow Manager API', function () {
    $filesystem = new Filesystem;
    $workspace = sys_get_temp_dir().'/'.uniqid('script-builder-', true);
    $filesystem->ensureDirectoryExists($workspace.'/.dev-workflow/bin');

    $builder = new ScriptBuilder($filesystem);
    $builder->generate($workspace);

    $scriptPath = $workspace.'/.dev-workflow/bin/create-project';
    expect($filesystem->exists($scriptPath))->toBeTrue();

    $shared = $workspace.'/.dev-workflow/bin/_shared.sh';
    expect($filesystem->exists($shared))->toBeTrue();

    $sharedContent = $filesystem->get($shared);
    expect($sharedContent)->toContain('WORKFLOW_MANAGER_URL')
        ->toContain('Authorization: Bearer');

    $scriptContent = $filesystem->get($scriptPath);
    expect($scriptContent)->toContain('POST "$WORKFLOW_MANAGER_URL/api/wfm/projects"');

    $permissions = substr(sprintf('%o', fileperms($scriptPath)), -3);
    expect($permissions)->toBeIn(['744', '755', '775']);

    $filesystem->deleteDirectory($workspace);
});
