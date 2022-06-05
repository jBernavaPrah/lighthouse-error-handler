<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\Summery;
use Jasny\PhpdocParser\TagSet;
use ReflectionClass;

class GenerateValidationCodeEnum
{
    /**
     * @return array
     */
    public function generate(): array
    {
        $codes = [];
        foreach ((new ReflectionClass(Validator::class))->getMethods() as $reflectionMethod) {
            if (
                $reflectionMethod->getShortName() === "validated" ||
                ! Str::of($reflectionMethod->getShortName())->startsWith('validate') ||
                !($code = $this->validationCodeString($reflectionMethod->getShortName()))
            ) {
                continue;
            }

            $description = (new PhpdocParser(new TagSet([new Summery()])))->parse($reflectionMethod->getDocComment())['description'];


            $codes[] = <<<GRAPHQL
"""
$description
"""
$code
GRAPHQL;
        }

        return $codes;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function validationCodeString(string $name): string
    {
        return (string)Str::of($name)->remove('validate')->snake()->upper();
    }
}
