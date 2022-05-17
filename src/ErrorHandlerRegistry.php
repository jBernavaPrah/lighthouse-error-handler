<?php

namespace JBernavaPrah\ErrorHandler;

use GraphQL\Error\Error;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use ReflectionClass;
use ReflectionException;

class ErrorHandlerRegistry
{
    /**
     * @var TypeRegistry
     */
    protected TypeRegistry $typeRegistry;

    /**
     * The stashed current type.
     *
     * Since PHP resolves the fields synchronously and one after another,
     * we can safely stash just this one value. Should the need arise, this
     * can probably be a map from the unique field path to the type.
     *
     * @var string|null
     */
    protected ?string $resolveType = null;

    /**
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @param string|null $resolveType
     * @return $this
     */
    public function setResolveType(?string $resolveType): static
    {
        $this->resolveType = $resolveType;
        return $this;
    }

    /**
     * Get the appropriate resolver for the node and call it with the decoded id.
     *
     * @param callable $previousResolver
     * @param mixed $root
     * @param array<string, mixed> $args
     * @param GraphQLContext $context
     * @param ResolveInfo $resolveInfo
     * @return mixed the result of calling the resolver
     *
     * @throws Error
     * @throws DefinitionException
     * @throws ReflectionException
     */
    public function resolve(callable $previousResolver, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        if (!$this->resolveType || is_null($resolveInfo->fieldDefinition->astNode)) {
            return $previousResolver($root, $args, $context, $resolveInfo);
        }

        $resolveInfo->fieldDefinition->astNode->type = Parser::typeReference("{$this->resolveType}!");

        $result = $previousResolver($root, $args, $context, $resolveInfo);

        // extract the real class from $result if resolveType is Union..

        // In case of union type, try to map it on the result of the reflection.
        if ($this->typeRegistry->get($this->resolveType) instanceof UnionType) {
            $this->resolveType = $this->guessResolveType($result);
        }


        // This check forces Lighthouse to eagerly load the type, which might not have
        // happened if the client only references it indirectly through an interface.
        // Loading the type in turn causes the TypeMiddleware to run and thus register
        // the type in the NodeRegistry.
        if (!$this->typeRegistry->has($this->resolveType)) {
            throw new Error("[{$this->resolveType}] is not a type and cannot be resolved.");
        }

        return $result;
    }

    /**
     * @param mixed $result
     * @return string
     * @throws Error
     * @throws ReflectionException
     */
    protected function guessResolveType(mixed $result): string
    {
        if (is_array($result) && isset($result['__typename'])) {
            return $result['__typename'];
        }
        if ((is_string($result) && class_exists($result)) || is_object($result)) {
            return (new ReflectionClass($result))->getShortName();
        }

        throw new Error("Impossible to guess the result type from the [{$this->resolveType}] Union.");
    }

    /**
     * Get the Type for the stashed type.
     * @throws DefinitionException
     */
    public function resolveType(): Type
    {
        assert(is_string($this->resolveType), 'Impossile to found the correct Resolver Type.');

        return $this->typeRegistry->get($this->resolveType);
    }
}
