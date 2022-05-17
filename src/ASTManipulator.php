<?php

namespace JBernavaPrah\ErrorHandler;

use JBernavaPrah\ErrorHandler\Errors\AuthenticationError;
use JBernavaPrah\ErrorHandler\Errors\AuthorizationError;
use JBernavaPrah\ErrorHandler\Errors\ValidationError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Contracts\Config\Repository as Config;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;

class ASTManipulator
{
    /**
     * @var array|string[]
     */
    protected array $defaultErrorClasses = [AuthenticationError::class, AuthorizationError::class, ValidationError::class];
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(ManipulateAST $manipulateAST): void
    {
        $documentAST = $manipulateAST->documentAST;

        $this->addErrorInterface($documentAST);
        $this->addErrors($documentAST);
        $this->manipulateFieldsType($documentAST, $documentAST->types[RootType::QUERY]);
        $this->manipulateFieldsType($documentAST, $documentAST->types[RootType::MUTATION]);
    }

    protected function addErrorInterface(DocumentAST $documentAST): void
    {
        $documentAST->setTypeDefinition(Parser::interfaceTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
interface Error {
    message: String!
}
GRAPHQL
        ));
    }

    protected function addErrors(DocumentAST $documentAST): void
    {

        /** @var array<string|int, string|array<string|int,string>> $errorClasses */
        $errorClasses = $this->config->get('lighthouse-error-handler.namespaces.errors') ?: [];

        collect($errorClasses)
            ->flatten()
            ->map(fn(string $namespaces) => ClassFinder::getClassesInNamespace($namespaces, ClassFinder::RECURSIVE_MODE))
            ->flatten()
            ->reject(fn(string $class) => !is_subclass_of($class, Error::class))
            ->merge($this->defaultErrorClasses)
            ->map(fn(string $class) => Parser::parse($class::definition()))
            ->each(function (DocumentNode $documentNode) use ($documentAST) {
                foreach ($documentNode->definitions as $node) {

                    $documentAST->setTypeDefinition($node);
                }
            });
    }

    protected function manipulateFieldsType(DocumentAST $documentAST, ObjectTypeDefinitionNode $parentNode): void
    {

        /** @var ErrorHandlerManipulator $manipulator */
        $manipulator = app(ErrorHandlerManipulator::class);
        $manipulator->setDocumentAST($documentAST);

        collect($parentNode->fields)
            ->map(fn(FieldDefinitionNode $node, string $_) => $manipulator
                ->setErrorsToMap(collect($this->defaultErrorClasses)
                    ->map(fn(string $class) => $class::NAME)
                    ->toArray())
                ->manipulate($node, $parentNode));
    }
}
