<?php

namespace Laravel\Horizon;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Connectors\RedisConnector;

class HorizonServiceProvider extends ServiceProvider
{
    use EventMap, ServiceBindings;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerEvents();
        $this->registerRoutes();
        $this->registerResources();
        $this->defineAssetPublishing();
        $this->registerRedisAlias();


    }

    /**
     * Register the Horizon job events.
     *
     * @return void
     */
    protected function registerEvents()
    {
        $events = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    /**
     * Register the Horizon routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        app()->router->group([
            'domain' => config('horizon.domain', null),
            'prefix' => config('horizon.path'),
            'namespace' => 'Laravel\Horizon\Http\Controllers',
//            'middleware' => config('horizon.middleware', 'web'),
        ], function ($router) {
            require __DIR__ . '/../routes/web.php';
        });
    }


    /**
     * Register the Horizon resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'horizon');
    }

    /**
     * Register redis factory.
     *
     * @return void
     */
    protected function registerRedisAlias()
    {
        $this->app->alias('redis', \Illuminate\Contracts\Redis\Factory::class);

        $this->app->make('redis');
    }

    /**
     * Register the custom queue connectors for Horizon.
     *
     * @return void
     */
    protected function registerQueueConnectors()
    {
        $this->app->resolving(QueueManager::class, function ($manager) {
            $manager->addConnector('redis', function () {
                return new RedisConnector($this->app['redis']);
            });
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!defined('HORIZON_PATH')) {
            define('HORIZON_PATH', realpath(__DIR__ . '/../'));
        }

        $this->app->bind(Console\WorkCommand::class, function ($app) {
            return new Console\WorkCommand($app['queue.worker'], $app['cache.store']);
        });

        $this->configure();
        $this->offerPublishing();
        $this->registerServices();
        $this->registerCommands();
        $this->registerQueueConnectors();
    }


    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
    {
        $this->publishes([
            HORIZON_PATH.'/public' => base_path('public/vendor/horizon'),
        ], 'horizon-assets');
    }

    /**
     * Setup the configuration for Horizon.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/horizon.php', 'horizon'
        );

        Horizon::use(config('horizon.use'));
    }

    /**
     * Setup the resource publishing groups for Horizon.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/horizon.php' => app()->basePath('config/horizon.php'),
            ], 'horizon-config');
        }
    }

    /**
     * Register Horizon's services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        foreach ($this->serviceBindings as $key => $value) {
            is_numeric($key)
                ? $this->app->singleton($value)
                : $this->app->singleton($key, $value);
        }
    }

    /**
     * Register the Horizon Artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCommand::class,
                Console\ForgetFailedCommand::class,
                Console\HorizonCommand::class,
                Console\ListCommand::class,
                Console\PurgeCommand::class,
                Console\PauseCommand::class,
                Console\ContinueCommand::class,
                Console\SupervisorCommand::class,
                Console\SupervisorsCommand::class,
                Console\TerminateCommand::class,
                Console\TimeoutCommand::class,
                Console\WorkCommand::class,
                Console\InstallCommand::class,
            ]);
        }

        $this->commands([Console\SnapshotCommand::class]);
    }
}
