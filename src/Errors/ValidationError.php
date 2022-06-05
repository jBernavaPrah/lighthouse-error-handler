<?php

namespace JBernavaPrah\LighthouseErrorHandler\Errors;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JBernavaPrah\LighthouseErrorHandler\Error;
use JBernavaPrah\LighthouseErrorHandler\GenerateValidationCodeEnum;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;

class ValidationError extends Error
{
    protected Validator $validator;

    public static function definition(): string
    {
       $codes = implode("\n\n", app(GenerateValidationCodeEnum::class)->generate());

        return /** @lang GraphQL */ <<<GRAPHQL

enum ValidationCodes {
    $codes
}

type ValidationCode {
    code: ValidationCodes!
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

    /**
     * @return array<string,mixed>
     */
    public function resolver(): array
    {
        $fields = [];

        foreach ($this->validator->failed() as $field => $codes) {
            $fields[] = $this->extractFieldAndCodes($field, $codes);
        }

        return [
            'message' => $this->getMessage(),
            'fields' => $fields,
        ];
    }

    /**
     * @param string $field
     * @param array<string,null|array<int,string>> $codes
     * @return array<string,mixed>
     */
    protected function extractFieldAndCodes(string $field, array $codes): array
    {
        $c = [];
        foreach ($codes as $code => $variables) {
            $c[] = $this->extractCodeAndVariables($code, $variables);
        }

        return [
            'field' => $field,
            'codes' => $c,
        ];
    }

    /**
     * @param string $code
     * @param array<int,string>|null $variables
     * @return array
     */
    #[ArrayShape(['code' => 'string', 'variables' => 'array'])]
    protected function extractCodeAndVariables(string $code, ?array $variables): array
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
