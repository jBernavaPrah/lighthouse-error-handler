<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use Nuwave\Lighthouse\Schema\TypeRegistry;

class UnionResolveType
{

    protected ?string $resolveType = null;

    public function __construct(protected TypeRegistry $typeRegistry)
    {

    }

    /**
     * @param string|null $resolveType
     */
    public function setResolveType(?string $resolveType): void
    {
        $this->resolveType = $resolveType;
    }

    /**
     * @return string|null
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function resolveType($root)
    {

        if(is_array($root) && isset($root['__typename'])){
            return $this->typeRegistry->get($root['__typename']);
        }

        try{
            return $this->typeRegistry->get(class_basename($root));
        }catch (\Throwable){}

        return $this->typeRegistry->get($this->resolveType);
    }

}
