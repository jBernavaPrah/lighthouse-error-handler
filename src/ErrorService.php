<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\MultiTag;
use Jasny\PhpdocParser\Tag\PhpDocumentor\TypeTag;
use Jasny\PhpdocParser\TagSet;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthenticationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthorizationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\ValidationError;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Utils;
use ReflectionClass;
use ReflectionException;

class ErrorService
{

    /**
     * @var array|string[]
     */
    protected array $defaultErrorClasses = [AuthenticationError::class, AuthorizationError::class, ValidationError::class];


    public function __construct(protected Repository $config)
    {
    }

    /**
     * @throws ReflectionException
     * @throws DefinitionException
     * @throws SyntaxError
     */
    public function searchThrowableErrors(): array
    {
        /** @var array<string|int, string|array<string|int,string>> $errorNamespaces */
        $errorNamespaces = $this->config->get('lighthouse.namespaces.errors') ?? [];

        return collect($errorNamespaces)
            ->merge(["JBernavaPrah\\LighthouseErrorHandler\\Errors"])
            ->flatten()
            ->map(fn(string $namespace) => ClassFinder::getClassesInNamespace($namespace, ClassFinder::RECURSIVE_MODE))
            ->flatten()
            ->reject(fn(string $class) => !is_subclass_of($class, Error::class))
            ->mapWithKeys(fn(string $class) => [$class => Parser::parse($class::definition())])
            ->each(fn(DocumentNode $documentNode, string $class) => $this->validateErrorDocumentNode($documentNode, $class))
            ->map(fn(DocumentNode $documentNode, string $_) => $this->extractTypeDefinitionFromDocumentNode($documentNode))
            ->flatten()
            ->toArray();

    }

    protected function extractTypeDefinitionFromDocumentNode(DocumentNode $documentNode): array
    {

        $nodes = [];
        foreach ($documentNode->definitions as $node) {
            $nodes[] = $node;
        }

        return $nodes;

    }

    /**
     * @throws ReflectionException
     * @throws DefinitionException
     */
    protected function validateErrorDocumentNode(DocumentNode $documentNode, string $class)
    {

        $className = (new ReflectionClass($class))->getShortName();

        foreach ($documentNode->definitions as $definition) {
            assert($definition instanceof TypeDefinitionNode);
            if ($definition->name->value === $className) return true;
        }

        throw new DefinitionException("Impossible to find the root definition on class {$class}. ");

    }

    /**
     * @param FieldDefinitionNode $node
     * @return array<string|int, mixed>
     * @throws ReflectionException
     */
    public function getThrowableErrorsFromField(FieldDefinitionNode $node): array
    {


        // TODO: add compatibility with @field(resolver)

        return collect($this->getRelatedClassException($node))
            ->merge($this->defaultErrorClasses)
            ->flatten()
            ->reject(fn(string $error) => !class_exists($error) || !is_subclass_of($error, Error::class))
            ->flatten()
            ->map(fn(string $class) => (new ReflectionClass($class))->getShortName())
            ->toArray();
    }

    /**
     * @throws ReflectionException
     */
    protected function getRelatedClassException(FieldDefinitionNode $node): array
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
            fn($error): bool => class_exists($error),
        );

        if (!$className) {
            return [];
        }

        return $this->getDocumentedClassException($className, '__invoke');

    }

    /**
     * @param string $class
     * @param string $method
     * @return array<string|int, string>
     * @throws ReflectionException
     */
    protected function getDocumentedClassException(string $class, string $method): array
    {

        $attributes = (new ReflectionClass($class))->getMethod($method)->getAttributes(HasError::class);

        $throws = [];
        foreach ($attributes as $attribute) {
            $throws[] = $attribute->newInstance()->error;
        }
        return $throws;
    }
}
