<?php

namespace JBernavaPrah\LighthouseErrorHandler\Tests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthenticationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\AuthorizationError;
use JBernavaPrah\LighthouseErrorHandler\Errors\ValidationError;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;

class DirectiveTest extends TestCase
{
    use UsesTestSchema;
    use MocksResolvers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestSchema();
    }

    function test_work_with_union_types()
    {

        $this->mockResolver(['__typename' => "Foo"]);

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
            name: Int
        }

        type Foo2 {
            int: Int
        }

        union UF2 = Foo | Foo2

        type Query {
            foo: UF2 @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo {
                __typename
            }
}")
            ->assertJsonFragment([
                'data' => [
                    'foo' => [
                        "__typename" => "Foo"
                    ],
                ],
            ]);
    }

    function test_thrown_custom_error()
    {

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
            name: Int
        }

        type Query {
            fooThrowCustomError: Foo
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            fooThrowCustomError {
            __typename

            }
}")
            ->assertJsonFragment([
                'data' => [
                    'fooThrowCustomError' => [
                        "__typename" => "CustomError"
                    ],
                ],
            ]);


    }

    function test_union_can_return_null()
    {

        $this->mockResolver(null, "first");

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
            name: Int
        }

        type Query {
            foo: Foo @mock(key:"first")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo {
                ... on Foo {
                    name
                }
            }
}")
            ->assertJsonFragment([
                'data' => [
                    'foo' => null,
                ],
            ]);

    }

    function test_throw_exception_when_list_type_are_used()
    {
        $this->schema = /** @lang GraphQL */
            '

        scalar String

        type Query {
            foo: [String]
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo
}")->assertGraphQLError(new DefinitionException("Field \"foo\" of type \"Query\" cannot be a list."));

    }

    function test_throw_exception_when_scalar_is_used()
    {


        $this->schema = /** @lang GraphQL */
            '

        scalar String

        type Query {
            foo: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo
}")->assertGraphQLError(new DefinitionException("Field foo cannot by a scalar type. Please convert it to Type."));

    }

    function test_nuwave_validation_error()
    {


        $this->mockResolver(fn() => throw new \Nuwave\Lighthouse\Exceptions\ValidationException("validation", Validator::make([
            "name" => "abc"
        ], [
            "name" => "min:5"
        ])));

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
        name: String
        }

        type Query {
            foo: Foo @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo {
                ...on ValidationError {
                    fields {
                        field
                        codes {
                            code
                            variables
                        }
                    }
                }
            }
}")
            ->assertJsonFragment([
                'data' => [
                    'foo' => [
                        "fields" => [
                            ["field" => "name", "codes" => [
                                ["code" => "MIN", "variables" => ["5"]]
                            ]]
                        ]
                    ],
                ],
            ]);
    }

    function test_validation_error_with_fields()
    {
        $this->mockResolver(fn() => throw new ValidationException(Validator::make([
            "name" => "abc"
        ], [
            "name" => "min:5"
        ])));

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
        name: String
        }

        type Query {
            foo: Foo @mock
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo {
                ...on ValidationError {
                    fields {
                        field
                        codes {
                            code
                            variables
                        }
                    }
                }
            }
}")
            ->assertJsonFragment([
                'data' => [
                    'foo' => [
                        "fields" => [
                            ["field" => "name", "codes" => [
                                ["code" => "MIN", "variables" => ["5"]]
                            ]]
                        ]
                    ],
                ],
            ]);


    }

    function test_throw_exceptions()
    {

        $this->mockResolver(fn() => throw new AuthorizationException(), "AuthorizationException");
        $this->mockResolver(fn() => throw new AuthorizationError(), "AuthorizationError");
        $this->mockResolver(fn() => throw new AuthenticationError(), "AuthenticationError");
        $this->mockResolver(fn() => throw new AuthenticationException(), "AuthenticationException");
        $this->mockResolver(fn() => throw new ValidationError("error", Validator::make([], [])), "ValidationError");
        $this->mockResolver(fn() => throw new ValidationException(Validator::make([], [])), "ValidationException");
        $this->mockResolver(fn() => ["name" => 1], "ok");

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
            name: Int
        }

        type Query {
            foo1: Foo @mock(key:"AuthorizationException")
            foo2: Foo @mock(key:"AuthorizationError")
            foo3: Foo @mock(key:"AuthenticationError")
            foo4: Foo @mock(key:"AuthenticationException")

            foo5: Foo @mock(key:"ValidationError")
            foo6: Foo @mock(key:"ValidationException")
            foo7: Foo @mock(key:"ok")
        }
        ';


        $this->graphQL(/** @lang GraphQL */ "query  {
            foo1 {
                __typename
            }
            foo2 {
                __typename
            }
            foo3 {
                __typename
            }
            foo4 {
                __typename
            }
            foo5 {
                __typename
            }
            foo6 {
                __typename
            }
            foo7 {
                __typename
            }
}")
            ->assertJsonFragment([
                'data' => [
                    'foo1' => [
                        "__typename" => "AuthorizationError"
                    ],
                    'foo2' => [
                        "__typename" => "AuthorizationError"
                    ],
                    'foo3' => [
                        "__typename" => "AuthenticationError"
                    ],
                    'foo4' => [
                        "__typename" => "AuthenticationError"
                    ],
                    'foo5' => [
                        "__typename" => "ValidationError"
                    ],
                    'foo6' => [
                        "__typename" => "ValidationError"
                    ],
                    'foo7' => [
                        "__typename" => "Foo"
                    ],

                ],
            ]);
    }

    function test_resolve_array()
    {

        $this->mockResolver([
            "name" => 1,
        ], "first");

        $this->schema = /** @lang GraphQL */
            '

        type Foo {
            name: Int
        }

        type Query {
            foo: Foo @mock(key:"first")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ "query  {
            foo {
                ... on Foo {
                    name
                }
            }
}")
            ->assertJsonFragment([
                'data' => [
                    'foo' => [
                        "name" => 1
                    ],
                ],
            ]);
    }
}
