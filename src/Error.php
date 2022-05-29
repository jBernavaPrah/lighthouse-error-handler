<?php

namespace JBernavaPrah\LighthouseErrorHandler;


use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class Error extends \Exception
{
    abstract public static function definition(): string;

    abstract public function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array;

    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {

        return array_merge([
            "__typename" => (new \ReflectionClass(static::class))->getShortName(),
        ], $this->resolver($root, $args, $context, $info));
    }

}
