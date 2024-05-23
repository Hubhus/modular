<?php

namespace StubModuleNamespace\StubClassNamePrefix\Providers;

use Illuminate\Auth\Access\Gate;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory as ViewFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class StubClassNamePrefixServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerMigrations();
        $this->registerPolicies();
    }

    public function boot(): void
    {
        $this->bootRoutes();
        $this->bootViews();
        $this->bootBladeComponents();
    }

    protected function bootBladeComponents(): void
    {
        $this->callAfterResolving(BladeCompiler::class, function(BladeCompiler $blade) {
            $blade->componentNamespace(
                'StubModuleNamespace\\StubClassNamePrefix\\Views\\Components',
                'StubModuleNameSingular'
            );
        });
    }

    protected function bootViews(): void
    {
        $this->callAfterResolving('view', function(ViewFactory $viewFactory) {
            if ($viewsFolder = realpath($this->modulePath('resources/views'))) {
                $viewFactory->addNamespace('StubModuleNameSingular', $viewsFolder);
            }
        });
    }

    protected function bootRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }
        $routesFolder = $this->modulePath('routes');
        if (file_exists($routesFolder)) {
            $routeFiles = Finder::create()->in()->name('*.php')->files();
            /** @var \Symfony\Component\Finder\SplFileInfo $routeFile */
            foreach ($routeFiles as $routeFile) {
                require $routeFile->getRealPath();
            }
        }
    }

    protected function registerPolicies(): void
    {
        $modelsFolder = $this->modulePath('src/Models');
        $policiesFolder = $this->modulePath('src/Policies');
        if (file_exists($modelsFolder) && file_exists($policiesFolder)) {
            $this->app->resolving(Gate::class, function(Gate $gate) {
                $modelFiles = Finder::create()->in($this->modulePath('src/Models'))->name('*.php')->files();
                /** @var SplFileInfo $modelFile */
                foreach ($modelFiles as $modelFile) {
                    $modelFqn = \str($modelFile->getRealPath())
                        ->after($this->modulePath('src/Models'))
                        ->prepend('StubModuleNamespace\\StubClassNamePrefix\\Models')
                        ->before('.php')
                        ->replace('/', '\\');
                    $expectedPolicyFqn = $modelFqn->replace('Models', 'Policies')->append('Policy');
                    if (class_exists($expectedPolicyFqn->toString())) {
                        $gate->policy($modelFqn->toString(), $expectedPolicyFqn->toString());
                    } else {
                        // Look for a the policy in the root Policies namespace
                        $simplePolicyFqn = str('StubModuleNamespace\\StubClassNamePrefix')
                            ->append('\\Policies\\')
                            ->append($expectedPolicyFqn->afterLast('\\'));
                        if (class_exists($simplePolicyFqn->toString())) {
                            $gate->policy($modelFqn->toString(), $simplePolicyFqn->toString());
                        }
                    }
                }
            });
        }
    }

    protected function registerMigrations(): void
    {
        $migrationsFolder = $this->modulePath('database/migrations');
        if (file_exists($migrationsFolder) && $this->app->runningInConsole()) {
            $this->app->resolving(Migrator::class, function(Migrator $migrator) use ($migrationsFolder) {
                $files = Finder::create()->in($migrationsFolder)->name('*.php')->files();
                /** @var SplFileInfo $file */
                foreach ($files as $file) {
                    $migrator->path($file->getRealPath());
                }
            });
        }
    }

    protected function registerConfig(): void
    {
        $configFolder = $this->modulePath('config');
        if (file_exists($configFolder)) {
            $configFiles = Finder::create()->in($configFolder)->name('*.php')->files();
            if (!$configFiles->count()) {
                // Try looking for config.php in the module root
                $configFiles = Finder::create()->in($this->modulePath())->name('config.php')->files();
            }
            if ($configFiles->count()) {
                $iterator = $configFiles->getIterator();
                $iterator->rewind();
                // Assume only one config file
                /** @var SplFileInfo $configFile */
                $configFile = $iterator->current();
                if ($configPath = $configFile->getRealPath()) {
                    $this->mergeConfigFrom($configPath, 'StubModuleNameSingular');
                }
            }
        }
    }

    protected function modulePath(?string $path = null): string
    {
        return Str::of(base_path('modules/StubModuleNameSingular'))
            ->when($path, function(Stringable $moduleFolder, $path) {
                return $moduleFolder->append(Str::start($path, '/'));
            });

    }
}
