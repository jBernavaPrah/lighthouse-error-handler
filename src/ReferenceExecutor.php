<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthenticationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthorizationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\ValidationError;
use Nuwave\Lighthouse\Exceptions\ValidationException;

class ReferenceExecutor extends \GraphQL\Executor\ReferenceExecutor
{
    /**
     * Isolates the "ReturnOrAbrupt" behavior to not de-opt the `resolveField` function.
     * Returns the result of resolveFn or the abrupt-return Error object.
     *
     * @param mixed $rootValue
     *
     * @return \Throwable|Promise|mixed
     */
    protected function resolveFieldValueOrError(
        FieldDefinition $fieldDef,
        FieldNode       $fieldNode,
        callable        $resolveFn,
        $rootValue,
        ResolveInfo     $info
    ) {
        try {
            // Build a map of arguments from the field.arguments AST, using the
            // variables scope to fulfill any variable references.
            $args = Values::getArgumentValues(
                $fieldDef,
                $fieldNode,
                $this->exeContext->variableValues
            );
            $contextValue = $this->exeContext->contextValue;

            try {
                return $resolveFn($rootValue, $args, $contextValue, $info);
            } catch (AuthorizationException $exception) {
                return AuthorizationError::fromLaravel($exception)->resolve();
            } catch (AuthenticationException $exception) {
                return AuthenticationError::fromLaravel($exception)->resolve();
            } catch (\Illuminate\Validation\ValidationException $exception) {
                return ValidationError::fromLaravel($exception)->resolve();
            } catch (ValidationException $exception) {
                return ValidationError::fromLighthouse($exception)->resolve();
            } catch (Error $exception) {
                return $exception->resolve();
            }
        } catch (\Throwable $error) {
            return $error;
        }
    }
}
