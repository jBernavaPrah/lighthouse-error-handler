<?php

namespace JBernavaPrah\ErrorHandler\Errors;

use JBernavaPrah\ErrorHandler\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jasny\PhpdocParser\PhpdocParser;
use Jasny\PhpdocParser\Tag\Summery;
use Jasny\PhpdocParser\TagSet;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use ReflectionClass;
use ReflectionMethod;

class ValidationError extends Error
{
    public const NAME = 'ValidationError';

    private Validator $validator;

    /**
     * @param string $message
     * @param Validator $validator
     */
    #[Pure] public function __construct(string $message, Validator $validator)
    {
        parent::__construct($message);
        $this->validator = $validator;
    }

    /**
     * @param ValidationException $exception
     * @return ValidationError
     */
    #[Pure] public static function fromLaravel(ValidationException $exception): self
    {
        return new self($exception->getMessage(), $exception->validator);
    }

    /**
     * @param \Nuwave\Lighthouse\Exceptions\ValidationException $exception
     * @return ValidationError
     */
    public static function fromLighthouse(\Nuwave\Lighthouse\Exceptions\ValidationException $exception): self
    {
        $reflection = new ReflectionClass($exception);
        $validator = $reflection->getProperty('validator')->getValue($exception);

        assert($validator instanceof Validator, 'Validator is not an instance of: ' . Validator::class);

        return new self($exception->getMessage(), $validator);
    }

    public static function definition(): string
    {
        $enums = self::generateEnumCodes();

        $parent = parent::definition();

        return /** @lang GraphQL */ <<<GRAPHQL

type ValidationCode {
    code: ValidationCodeType!
    variables: [String!]!
}

type ValidationField  {
    field: String!
    codes: [ValidationCode!]!
}

"""
Options for the `code` of `ValidationCode`.
"""
enum ValidationCodeType {
$enums

}

$parent
GRAPHQL;
    }

    /**
     * @return string
     */
    protected static function generateEnumCodes(): string
    {


        return collect((new ReflectionClass(\Illuminate\Validation\Validator::class))->getMethods())

            ->reject(fn (ReflectionMethod $method, int $_): bool => ! Str::of($method->getShortName())->startsWith('validate'))
            ->filter()

            ->mapWithKeys(fn (ReflectionMethod $method): array => [
                self::validationCodeString($method->getShortName()) => $method->getDocComment(),
            ])

            ->reject(fn (string $doc, $key): bool => ! $key)
            ->map(fn (string $docs, string $_): string => (new PhpdocParser(new TagSet([new Summery()])))->parse($docs)['description'])
            ->map(
                fn (string $description, string $key): string => <<<GRAPHQL
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

    /**
     * @param mixed $root
     * @param array<string, mixed>  $args
     * @param GraphQLContext $context
     * @param ResolveInfo $info
     * @return array<string, mixed>
     */
    #[ArrayShape(['fields' => '[ValidationField!]!'])] protected function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [
            'fields' => Collection::wrap($this->validator->failed())
                ->map(function (array $value, string $field) {
                    return [
                        'field' => $field,
                        'codes' => Collection::wrap($value)
                            ->map(function (?array $value, string $key) {
                                return ['code' => (string)Str::of($key)->snake()->upper(), 'variables' => Arr::wrap($value)];
                            })->values()->toArray(),
                    ];
                })->values()->toArray(),
        ];
    }
}
