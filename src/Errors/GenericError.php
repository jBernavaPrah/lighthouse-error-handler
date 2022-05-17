<?php

namespace App\GraphQL\ErrorHandler\Errors;

use App\GraphQL\ErrorHandler\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Throwable;

class GenericError extends Error
{
    public const NAME = 'GenericError';

    public static function fromLaravel(Throwable $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode());
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
