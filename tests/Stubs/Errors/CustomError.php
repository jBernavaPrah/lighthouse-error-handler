<?php

namespace JBernavaPrah\LighthouseErrorHandler\Tests\Stubs\Errors;

use GraphQL\Type\Definition\ResolveInfo;
use JBernavaPrah\LighthouseErrorHandler\Error;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CustomError extends Error
{

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<GRAPHQL
"""
Custom Error
"""
type CustomError implements Error {
    message: String!
}
GRAPHQL;
    }

    public function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [
            "message" => $this->getMessage()
        ];
    }
}
