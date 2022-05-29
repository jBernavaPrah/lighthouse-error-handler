<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\NullableType;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\MultiTag;
use Jasny\PhpdocParser\Tag\PhpDocumentor\TypeTag;
use Jasny\PhpdocParser\TagSet;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Support\Utils;
use ReflectionClass;
use ReflectionException;

class FieldManipulator
{

    public function __construct(protected ErrorService $errorService)
    {
    }

    function unionName(FieldDefinitionNode $node, ObjectTypeDefinitionNode $parentType): string
    {
        return Str::of($node->name->value)->camel()->ucfirst()
            ->append(Str::of($parentType->name->value)->camel()->ucfirst()
                ->append("Result"));
    }


    /**
     * @param DocumentAST $documentAST
     * @param ObjectTypeDefinitionNode $parentType
     * @return void
     */
    public function manipulate(DocumentAST              $documentAST,
                               ObjectTypeDefinitionNode $parentType): void
    {

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

                $node->type = Parser::typeReference(/** @lang GraphQL */ "{$unionType->name->value}" . ($node->type instanceof NonNullTypeNode ? "!" : ""));
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
    union $unionName = $unionType
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
            return collect($nodeType->types)
                ->map(fn($type) => ASTHelper::getUnderlyingTypeName($type))->toArray();
        }

        return [$nodeType->name->value];
    }
}
