<?php

namespace JBernavaPrah\ErrorHandler;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
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

class ErrorHandlerManipulator
{
    /**
     * @var DocumentAST
     */
    protected DocumentAST $documentAST;

    /**
     * @var array<string|int,mixed>
     */
    protected array $errorsToMap = [];

    private Config $config;

    public function __construct( Config $config)
    {

        $this->config = $config;
    }



    /**
     * @param FieldDefinitionNode $node
     * @param ObjectTypeDefinitionNode $parentType
     * @return void
     * @throws ReflectionException
     * @throws DefinitionException
     */
    public function manipulate(FieldDefinitionNode $node, ObjectTypeDefinitionNode &$parentType)
    {
        $types = $this->getUnderlyingTypeNames($node);

        $unionType = Collection::wrap($types)
            ->merge($this->errorsToMap)
            ->merge($this->getErrorsFromClass($node))
            ->implode(' | ');

        $unionName = Str::of($node->name->value)->camel()->ucfirst()
            ->append(Str::of($parentType->name->value)->camel()->ucfirst()
                ->append($this->config->get('lighthouse-error-handler.union_name')));


        $unionTypeDefinition = Parser::unionTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Error Handler for {$node->name->value}"
    union $unionName @union(resolveType: "JBernavaPrah\\\ErrorHandler\\\ErrorHandlerRegistry@resolveType") = $unionType
GRAPHQL
        );


        $mapToType = ASTHelper::getUnderlyingTypeName($node);
        $unionTypeDefinition->directives[] = Parser::constDirective(
            <<<GRAPHQL
        @_errorHandlerResolveTo(type: "$mapToType")
        GRAPHQL
        );

        $this->documentAST->setTypeDefinition($unionTypeDefinition);

        $node->type = Parser::typeReference(/** @lang GraphQL */ "{$unionName}!");
        $parentType->fields = ASTHelper::mergeUniqueNodeList($parentType->fields, [$node], true);
    }


    /**
     * @param FieldDefinitionNode $node
     * @return array<string|int,mixed>
     * @throws DefinitionException
     */
    protected function getUnderlyingTypeNames(FieldDefinitionNode $node): array
    {
        $nodeType = ASTHelper::underlyingType($node);

        if ($nodeType instanceof UnionTypeDefinitionNode) {
            return collect($nodeType->types)
                ->map(fn ( $type) => ASTHelper::getUnderlyingTypeName($type))->toArray();
        }

        return [$nodeType->name->value];
    }

    /**
     * @param FieldDefinitionNode $node
     * @return array<string|int, mixed>
     * @throws ReflectionException
     */
    protected function getErrorsFromClass(FieldDefinitionNode $node): array
    {

        /** @var array<string> $namespaces */
        $namespaces = array_merge(
            (array)$this->config->get('lighthouse.namespaces.mutations'),
            (array)$this->config->get('lighthouse.namespaces.queries')
        );

        /** @var string|null $className */
        $className = Utils::namespaceClassname(
            (string)Str::of($node->name->value)->camel()->ucfirst(),
            $namespaces,
            fn ($error): bool => is_subclass_of($error, Error::class),
        );

        if (! $className) {
            return [];
        }

        return collect($this->getDocumentedClassException($className))
            ->reject(fn (string $error) => ! class_exists($error))
            ->reject(fn (string $error) => ! is_subclass_of($error, Error::class))
            ->flatten()
            ->toArray();
    }

    /**
     * @param string $class
     * @return array<string|int, string>
     * @throws ReflectionException
     */
    protected function getDocumentedClassException(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $doc = (new ReflectionClass($class))->getMethod('__invoke')->getDocComment();

        $parser = new PhpdocParser(new TagSet([new MultiTag('throws', new TypeTag('throws', null))]));

        return $doc ? ($parser->parse($doc)['throws'] ?? []) : [];
    }

    /**
     * @param array<string|int,mixed> $errorsToMap
     * @return self
     */
    public function setErrorsToMap(array $errorsToMap): self
    {
        $this->errorsToMap = $errorsToMap;

        return $this;
    }

    /**
     * @param DocumentAST $documentAST
     */
    public function setDocumentAST(DocumentAST $documentAST): void
    {
        $this->documentAST = $documentAST;
    }
}
