<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager;

use AntonioPrimera\WorkflowManager\Console\InstallWorkflowManagerCommand;
use AntonioPrimera\WorkflowManager\Documentation\DocumentationGenerator;
use AntonioPrimera\WorkflowManager\Environment\EnvWriter;
use AntonioPrimera\WorkflowManager\Scaffolding\ScriptBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the Workflow Manager installer package.
 */
class WorkflowManagerInstallerServiceProvider extends ServiceProvider
{
    /**
     * Registers container bindings.
     */
    public function register(): void
    {
        $this->app->singleton(EnvWriter::class, function ($app) {
            return new EnvWriter($app['files'], $app->basePath());
        });

        $this->app->singleton(DocumentationGenerator::class, function ($app) {
            return new DocumentationGenerator(
                $app['files'],
                $app->basePath(),
                __DIR__.'/../stubs/documentation'
            );
        });

        $this->app->singleton(ScriptBuilder::class, fn ($app) => new ScriptBuilder($app['files']));

        $this->app->singleton(InstallWorkflowManagerCommand::class, function ($app) {
            return new InstallWorkflowManagerCommand(
                $app->make(EnvWriter::class),
                $app->make(DocumentationGenerator::class),
                $app->make(ScriptBuilder::class)
            );
        });
    }

    /**
     * Boots console commands.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallWorkflowManagerCommand::class,
            ]);
        }
    }
}
