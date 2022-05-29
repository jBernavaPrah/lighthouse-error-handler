<?php

namespace JBernavaPrah\LighthouseErrorHandler;


use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Contracts\Config\Repository as Config;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use ReflectionException;

class ASTManipulator
{

    public function __construct(protected Config           $config,
                                protected FieldManipulator $fieldManipulator,
                                protected ErrorService     $errorService)
    {
    }

    /**
     * @throws \GraphQL\Error\SyntaxError
     * @throws ReflectionException
     * @throws DefinitionException
     */
    public function handle(ManipulateAST $manipulateAST): void
    {
        $documentAST = $manipulateAST->documentAST;

        $documentAST->setTypeDefinition(self::errorInterface());

        $this->searchAndAddErrorsToDocument($documentAST);

        $this->manipulateFieldsType($documentAST, RootType::QUERY);
        $this->manipulateFieldsType($documentAST, RootType::MUTATION);
    }

    /**
     * @throws DefinitionException
     * @throws ReflectionException
     * @throws \GraphQL\Error\SyntaxError
     */
    protected function searchAndAddErrorsToDocument(DocumentAST $documentAST): void
    {

        $errors = $this->errorService->searchThrowableErrors();

        collect($errors)->each(fn(TypeDefinitionNode $node) => $documentAST->setTypeDefinition($node));

    }

    protected function manipulateFieldsType(DocumentAST $documentAST, string $parentNodeType): void
    {

        /** @var ObjectTypeDefinitionNode|null $parentNode */
        $parentNode = $documentAST->types[$parentNodeType] ?? null;
        if (!$parentNode) return;

        $this->fieldManipulator->manipulate($documentAST, $parentNode);

    }


    protected static function errorInterface(): InterfaceTypeDefinitionNode
    {
        return Parser::interfaceTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
interface Error {
    message: String!
}
GRAPHQL
        );
    }


}
