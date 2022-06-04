<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class LighthouseErrorHandlerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(FieldManipulator::class);
        $this->app->singleton(UnionResolveType::class);

    }


    /**
     * Bootstrap any application services.
     *
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function boot(Dispatcher $dispatcher, Repository $config): void
    {

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
