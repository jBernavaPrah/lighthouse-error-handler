<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\TypeRegistry;

class UnionResolveType
{
    protected string $resolveType;

    public function __construct(protected TypeRegistry $typeRegistry)
    {
    }

    /**
     * @param string $resolveType
     */
    public function setResolveType(string $resolveType): void
    {
        $this->resolveType = $resolveType;
    }

    /**
     * @param mixed $root
     * @return Type
     * @throws DefinitionException
     */
    public function resolveType(mixed $root): Type
    {
        if (is_array($root) && isset($root['__typename'])) {
            return $this->typeRegistry->get($root['__typename']);
        }

        if (is_object($root) && $this->typeRegistry->has(class_basename($root))) {
            return $this->typeRegistry->get(class_basename($root));
        }

        return $this->typeRegistry->get($this->resolveType);
    }
}
