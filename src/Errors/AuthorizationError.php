<?php

namespace JBernavaPrah\LighthouseErrorHandler\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use JBernavaPrah\LighthouseErrorHandler\Error;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

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

    #[ArrayShape(['message' => 'string'])]
    public function resolver(): array
    {
        return [
            'message' => $this->getMessage(),
        ];
    }
}
