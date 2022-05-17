<?php

namespace JBernavaPrah\ErrorHandler;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\ArrayShape;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class Error extends \Exception
{
    public static string $comment = '';

    public static function definition(): string
    {
        $reflectionClass = new \ReflectionClass(static::class);
        $comment = static::$comment ?: Str::of($reflectionClass->getShortName())->headline();

        $attributes = Collection::wrap($reflectionClass->getMethod('resolver')
            ->getAttributes(ArrayShape::class))
            ->mapWithKeys(fn (\ReflectionAttribute $attribute, string $_): array => Collection::wrap($attribute->getArguments())
                ->mapWithKeys(fn (array $arg) => $arg)
                ->toArray())
            ->implode(function (string $value, string $key) {
                return "$key: $value";
            });

        return /** @lang GraphQL */ "
\"\"\"
{$comment}
\"\"\"
type {$reflectionClass->getShortName()} implements Error {
    message: String!
    $attributes
}
";
    }

    /**
     * @param mixed $root
     * @param array<string|int, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string|int, mixed>
     */
    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return array_merge([
            '__typename' => (new \ReflectionClass(static::class))->getShortName(),
            'message' => $this->getMessage(),
        ], $this->resolver($root, $args, $context, $info));
    }

    /**
     * @param mixed $root
     * @param array<string|int, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string|int, mixed>
     */
    abstract protected function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array;
}
