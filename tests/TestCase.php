<?php

declare(strict_types=1);

namespace JBernavaPrah\LighthouseErrorHandler\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JBernavaPrah\LighthouseErrorHandler\LighthouseErrorHandlerServiceProvider;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;
    use RefreshesSchemaCache;

    /**
     * @param Application $app
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            LighthouseServiceProvider::class,
            PaginationServiceProvider::class,
            LighthouseErrorHandlerServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {

        $app['config']->set('lighthouse.namespaces.models', ["Illuminate\Foundation\Auth"]);
        $app['config']->set('lighthouse.namespaces.errors', "JBernavaPrah\\LighthouseErrorHandler\\Tests\\Stubs\\Errors");
        $app['config']->set('lighthouse.namespaces.queries', "JBernavaPrah\\LighthouseErrorHandler\\Tests\\Stubs\\Queries");
    }
}
