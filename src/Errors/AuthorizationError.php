<?php

namespace App\GraphQL\ErrorHandler\Errors;

use App\GraphQL\ErrorHandler\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\Access\AuthorizationException;
use JetBrains\PhpStorm\Pure;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AuthorizationError extends Error
{
    public const NAME = 'AuthorizationError';

    #[Pure] public static function fromLaravel(AuthorizationException $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode(), $exception);
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>
     */
    protected function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [];
    }
}
