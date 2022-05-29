<?php

namespace JBernavaPrah\LighthouseErrorHandler\Commands;

use Illuminate\Support\Str;
use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\Summery;
use Jasny\PhpdocParser\TagSet;

class GenerateValidationCodeEnum
{
    /**
     * @return string
     */
    protected static function generateEnumCodes(): string
    {
        return collect((new ReflectionClass(\Illuminate\Validation\Validator::class))->getMethods())
            ->reject(fn(ReflectionMethod $method, int $_): bool => !Str::of($method->getShortName())->startsWith('validate'))
            ->filter()
            ->mapWithKeys(fn(ReflectionMethod $method): array => [
                self::validationCodeString($method->getShortName()) => $method->getDocComment(),
            ])
            ->reject(fn(string $doc, $key): bool => !$key)
            ->map(fn(string $docs, string $_): string => (new PhpdocParser(new TagSet([new Summery()])))->parse($docs)['description'])
            ->map(
                fn(string $description, string $key): string => <<<GRAPHQL
"""
$description
"""
$key
GRAPHQL
            )
            ->implode("\n\n");
    }

    /**
     * @param string $name
     * @return string
     */
    protected static function validationCodeString(string $name): string
    {
        return (string)Str::of($name)->remove('validate')->snake()->upper();
    }
}
