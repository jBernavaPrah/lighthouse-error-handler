<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use Closure;
use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class ErrorableDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Do not use this directive directly, it is automatically added to the schema
when using the Error Handler extension.
"""
directive @errorable(
    """
    Resolve to this type when there are no errors.
    """
    defaultType: String!
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function __construct(protected UnionResolveType $resolveType)
    {
    }

    /**
     * Wrap around the final field resolver.
     *
     * @param FieldValue $fieldValue
     * @param Closure $next
     * @return FieldValue
     * @throws Exception
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        /** @var Closure $previousResolver */
        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $info) use ($previousResolver) {
            $this->resolveType->setResolveType($this->directiveArgValue('defaultType'));
            return $previousResolver($root, $args, $context, $info);
        });

        return $next($fieldValue);
    }
}
