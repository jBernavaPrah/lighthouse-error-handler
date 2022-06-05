<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use GraphQL\Executor\Executor;
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
        $this->app->singleton(GenerateValidationCodeEnum::class);

        Executor::setImplementationFactory([ReferenceExecutor::class, 'create']);
    }


    /**
     * Bootstrap any application services.
     *
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function boot(Dispatcher $dispatcher): void
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
