<?php

namespace JBernavaPrah\LighthouseErrorHandler\Errors;

use JBernavaPrah\LighthouseErrorHandler\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\Access\AuthorizationException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AuthorizationError extends Error
{

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<GRAPHQL
"""
Authorization Error
"""
type AuthorizationError implements Error {
    message: String!
}
GRAPHQL;
    }

    #[Pure] public static function fromLaravel(AuthorizationException $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode(), $exception);
    }

    #[ArrayShape(["message" => "string"])]
    public function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [
            "message" => $this->getMessage()
        ];
    }
}
