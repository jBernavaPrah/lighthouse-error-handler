<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use ReflectionException;

class FieldManipulator
{
    public function __construct(protected ErrorService $errorService)
    {
    }

    public function unionName(FieldDefinitionNode $node, ObjectTypeDefinitionNode $parentType): string
    {
        return Str::of($node->name->value)->camel()->ucfirst()
            ->append(Str::of($parentType->name->value)->camel()->ucfirst()
                ->append('Result'));
    }


    /**
     * @param DocumentAST $documentAST
     * @param ObjectTypeDefinitionNode $parentType
     * @return void
     */
    public function manipulate(
        DocumentAST              $documentAST,
        ObjectTypeDefinitionNode $parentType
    ): void
    {
        /** @var  array<\GraphQL\Language\AST\FieldDefinitionNode>|\GraphQL\Language\AST\NodeList<\GraphQL\Language\AST\FieldDefinitionNode> $fields */
        $fields = collect($parentType->fields)
            ->map(function (FieldDefinitionNode $node, string $_) use ($parentType, $documentAST) {
                if ($node->type instanceof ListTypeNode) {
                    throw new DefinitionException("Field \"{$node->name->value}\" of type \"{$parentType->name->value}\" cannot be a list.");
                }

                if (ASTHelper::underlyingType($node) instanceof ScalarTypeDefinitionNode) {
                    throw new DefinitionException("Field {$node->name->value} cannot by a scalar type. Please convert it to Type.");
                }


                $unionType = $this->generateUnionType($node, $parentType);
                $documentAST->setTypeDefinition($unionType);
                $returnTypeName = ASTHelper::getUnderlyingTypeName($node);
                $modelName = addslashes(ASTHelper::modelName($node) ?: '');

                $node->type = Parser::typeReference(/** @lang GraphQL */ "{$unionType->name->value}" . ($node->type instanceof NonNullTypeNode ? '!' : ''));

                foreach ($node->directives as $directive) {
                    if (ASTHelper::directiveArgValue($directive, 'model', '_NA') === '_NA') {
                        $directive->arguments[] = Parser::constArgument(/** @lang GraphQL */ <<<GRAPHQL
model: "$modelName"
GRAPHQL
                        );
                    }
                }

                $node->directives = ASTHelper::prepend($node->directives, Parser::constDirective(/** @lang GraphQL */ <<<GRAPHQL
@errorable(defaultType: "{$returnTypeName}")
GRAPHQL
                ));
                return $node;
            })
            ->toArray();


        ASTHelper::mergeUniqueNodeList($parentType->fields, $fields, true);
    }

    /**
     * @throws DefinitionException
     * @throws ReflectionException
     */
    protected function generateUnionType(FieldDefinitionNode $node, ObjectTypeDefinitionNode $parentType): UnionTypeDefinitionNode
    {
        $unionType = Collection::wrap($this->getUnderlyingTypeNames($node))
            ->merge($this->errorService->getThrowableErrorsFromField($node))
            ->implode(' | ');

        $unionName = $this->unionName($node, $parentType);

        return Parser::unionTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Union to handle the underlying {$node->name->value}"
    union $unionName @union(resolveType: "JBernavaPrah\\\LighthouseErrorHandler\\\UnionResolveType@resolveType") = $unionType
GRAPHQL
        );
    }

    /**
     * @return array<string|int,mixed>
     * @throws DefinitionException
     */
    protected function getUnderlyingTypeNames(FieldDefinitionNode $node): array
    {
        $nodeType = ASTHelper::underlyingType($node);

        if ($nodeType instanceof UnionTypeDefinitionNode) {
            $names = [];
            foreach ($nodeType->types as $type) {
                $names[] = ASTHelper::getUnderlyingTypeName($type);
            }
            return $names;
        }

        return [$nodeType->name->value];
    }
}
