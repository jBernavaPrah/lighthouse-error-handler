<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use Illuminate\Auth\Access\AuthorizationException;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthenticationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthorizationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\ValidationError;
use Closure;
use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
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
    Use this type when there are no errors.
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

            try {
                $this->resolveType->setResolveType($this->directiveArgValue("defaultType"));
                return $previousResolver($root, $args, $context, $info);
            } catch (AuthorizationException $exception) {
                return AuthorizationError::fromLaravel($exception)->resolve($root, $args, $context, $info);
            } catch (AuthenticationException $exception) {
                return AuthenticationError::fromLaravel($exception)->resolve($root, $args, $context, $info);
            } catch (ValidationException $exception) {
                return ValidationError::fromLaravel($exception)->resolve($root, $args, $context, $info);
            } catch (\Nuwave\Lighthouse\Exceptions\ValidationException $exception) {
                return ValidationError::fromLighthouse($exception)->resolve($root, $args, $context, $info);
            } catch (Error $exception) {
                return $exception->resolve($root, $args, $context, $info);
            }

        });

        return $next($fieldValue);
    }

}
