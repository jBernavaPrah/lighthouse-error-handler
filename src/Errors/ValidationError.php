<?php

namespace JBernavaPrah\LighthouseErrorHandler\Errors;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JBernavaPrah\LighthouseErrorHandler\Error;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use ReflectionClass;

class ValidationError extends Error
{

    protected Validator $validator;

    public static function definition(): string
    {

        return /** @lang GraphQL */ <<<GRAPHQL

type ValidationCode {
    code: String!
    variables: [String!]!
}

type ValidationField  {
    field: String!
    codes: [ValidationCode!]!
}

type ValidationError implements Error {
    message: String!
    fields: [ValidationField!]!
}

GRAPHQL;
    }

    public function resolver(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        return [
            "message" => $this->getMessage(),
            "fields" => Collection::wrap($this->validator->failed())
                ->map(fn(array $codes, string $field) => $this->extractFieldAndCodes($field, $codes))
                ->values()
                ->toArray()
        ];
    }

    #[ArrayShape(['field' => "string", 'codes' => "mixed"])]
    protected function extractFieldAndCodes(string $field, array $codes): array
    {
        return [
            'field' => $field,
            'codes' => Collection::wrap($codes)
                ->map(fn(?array $value, string $key) => $this->extractCodeAndVariables($key, $value))
                ->values()
                ->toArray(),
        ];
    }

    #[ArrayShape(['code' => "string", 'variables' => "array"])]
    protected function extractCodeAndVariables(string $code, array $variables): array
    {
        return ['code' => (string)Str::of($code)->snake()->upper(), 'variables' => Arr::wrap($variables)];
    }


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
        // execute to reinitialize the exception.
        $validator->fails();

        return new self($exception->getMessage(), $validator);
    }


}
