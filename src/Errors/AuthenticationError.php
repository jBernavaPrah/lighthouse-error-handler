<?php

namespace App\GraphQL\ErrorHandler\Errors;

use App\GraphQL\ErrorHandler\Error;
use GraphQL\Type\Definition\ResolveInfo;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AuthenticationError extends Error
{
    public const NAME = 'AuthenticationError';

    /**
     * @var array<string|int, string>
     */
    private array $guards;

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

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>
     */
    #[ArrayShape(['guards' => '[String]'])] protected function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [
            'guards' => $this->guards,
        ];
    }
}
