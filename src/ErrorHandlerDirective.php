<?php

namespace JBernavaPrah\ErrorHandler;

use GraphQL\Language\AST\Node;
use JBernavaPrah\ErrorHandler\Errors\AuthenticationError;
use JBernavaPrah\ErrorHandler\Errors\ValidationError;
use Closure;
use Exception;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Throwable;

final class ErrorHandlerDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    private ErrorHandlerRegistry $registry;

    public function __construct(ErrorHandlerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
# Helper for extract the original type of the union
directive @errorHandler (
    """
    Add here the errors that are wrapped by.
    """
    wrapWithErrors: [String!]

) on FIELD_DEFINITION
GRAPHQL;
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
                $value = $this->extractReturnType($info->fieldDefinition->astNode);

                return $this->registry->setResolveType(is_string($value) ? $value : null)
                    ->resolve($previousResolver, $root, $args, $context, $info);
            } catch (AuthenticationException $exception) {
                $this->registry->setResolveType(AuthenticationError::NAME);
                return (AuthenticationError::fromLaravel($exception))->resolve($root, $args, $context, $info);
            } catch (ValidationException $exception) {
                $this->registry->setResolveType(ValidationError::NAME);
                return (ValidationError::fromLaravel($exception))->resolve($root, $args, $context, $info);
            } catch (\Nuwave\Lighthouse\Exceptions\ValidationException $exception) {
                $this->registry->setResolveType(ValidationError::NAME);
                return (ValidationError::fromLighthouse($exception))->resolve($root, $args, $context, $info);
            } catch (Error $exception) {
                $this->registry->setResolveType($exception);
                return $exception->resolve($root, $args, $context, $info);
            }
        });

        return $next($fieldValue);
    }

    /**
     * @throws Exception
     */
    protected function extractReturnType(?FieldDefinitionNode $node): mixed
    {
        if (! $node) {
            return null;
        }

        $typeName = ASTHelper::getUnderlyingTypeName($node);

        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = app(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();

        $returnType = $documentAST->types[$typeName] ?? null;

        if (! $returnType || ! property_exists($returnType, 'directives') || ! ASTHelper::hasDirective($returnType, ErrorHandlerResolveToDirective::NAME)) {
            return null;
        }

        $directive = ASTHelper::firstByName($returnType->directives, ErrorHandlerResolveToDirective::NAME);

        assert($directive instanceof DirectiveNode, 'Impossible to find the directive node.');

        return ASTHelper::directiveArgValue($directive, 'type');
    }

    /**
     * @throws DefinitionException
     * @throws Throwable
     */
    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {

        /** @var ErrorHandlerManipulator $manipulator */
        $manipulator = app(ErrorHandlerManipulator::class);
        $manipulator->setDocumentAST($documentAST);
        $manipulator->setErrorsToMap($this->getMethodArgumentParts('wrapWithErrors'));
        $manipulator->manipulate($fieldDefinition, $parentType);
    }
}
