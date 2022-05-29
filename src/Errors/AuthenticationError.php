<?php

namespace JBernavaPrah\LighthouseErrorHandler\Errors;

use JBernavaPrah\LighthouseErrorHandler\Error;
use GraphQL\Type\Definition\ResolveInfo;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AuthenticationError extends Error
{

    /**
     * @var array<string|int, string>
     */
    public array $guards;

    /**
     * @param string $message
     * @param array<string|int, string> $guards
     */
    #[Pure] public function __construct(string $message = 'Unauthenticated.', array $guards = [])
    {
        parent::__construct($message);
        $this->guards = $guards;
    }

    #[Pure] public static function fromLaravel(\Illuminate\Auth\AuthenticationException $exception): AuthenticationError
    {
        return new self($exception->getMessage(), $exception->guards());
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<GRAPHQL
"""
Authentication Error
"""
type AuthenticationError  implements Error {
    message: String!
    guards: [String!]!
}
GRAPHQL;
    }

    #[ArrayShape(['message' => "string", 'guards' => "mixed"])]
    public function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [
            'message' => $this->getMessage(),
            'guards' => $this->guards,
        ];
    }
}
