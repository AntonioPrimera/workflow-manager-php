<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Tests;

use AntonioPrimera\WorkflowManager\WorkflowManagerInstallerServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $workspace;

    protected Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/'.Str::uuid();

        $this->filesystem = new Filesystem;
        $this->filesystem->ensureDirectoryExists($this->workspace);

        $this->app->setBasePath($this->workspace);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [WorkflowManagerInstallerServiceProvider::class];
    }

    protected function workspacePath(string $path = ''): string
    {
        return rtrim($this->workspace, '/').($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    protected function ensureEnvFiles(): void
    {
        $this->filesystem->put($this->workspacePath('.env'), "APP_NAME=Laravel\n");
        $this->filesystem->put($this->workspacePath('.env.example'), "APP_NAME=Laravel\nWORKFLOW_MANAGER_URL=\nWORKFLOW_MANAGER_API_KEY=\nWORKFLOW_MANAGER_PROJECT_ID=\n");
    }
}
