<?php

namespace JBernavaPrah\ErrorHandler;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

final class ErrorHandlerResolveToDirective extends BaseDirective
{
    public const NAME = '_errorHandlerResolveTo';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
# Internal directive
directive @_errorHandlerResolveTo (
    type: String!
) on UNION
GRAPHQL;
    }
}
