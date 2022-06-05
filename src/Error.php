<?php

namespace JBernavaPrah\LighthouseErrorHandler;

abstract class Error extends \Exception
{
    abstract public static function definition(): string;

    abstract public function resolver(): array;

    public function resolve(): array
    {
        return array_merge([
            '__typename' => (new \ReflectionClass(static::class))->getShortName(),
        ], $this->resolver());
    }
}
