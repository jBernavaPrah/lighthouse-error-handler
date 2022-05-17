<?php

namespace JBernavaPrah\ErrorHandler;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class ErrorHandlerServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register()
    {
        $this->app->singleton(ErrorHandlerRegistry::class);
        $this->app->singleton(ErrorHandlerManipulator::class);


            }


    /**
     * Bootstrap any application services.
     *
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function boot(Dispatcher $dispatcher, Config $config): void
    {

        $lighthouseMiddleware = $config->get('lighthouse.field_middleware', []);
        assert(is_array($lighthouseMiddleware));
        $config->set('lighthouse.field_middleware', Arr::prepend($lighthouseMiddleware, ErrorHandlerDirective::class));


        $this->mergeConfigFrom(
            __DIR__ . '/../config/lighthouse-error-handler.php',
            'lighthouse-error-handler',
        );

        $this->publishes([
            __DIR__ . '/../config/lighthouse-error-handler.php' => config_path('lighthouse-error-handler.php'),
        ], 'lighthouse-error-handler');

        $dispatcher->listen(
            ManipulateAST::class,
            ASTManipulator::class,
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );
    }
}
