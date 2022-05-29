<?php

namespace JBernavaPrah\LighthouseErrorHandler\Tests\Stubs\Queries;

use JBernavaPrah\LighthouseErrorHandler\HasError;
use JBernavaPrah\LighthouseErrorHandler\Tests\Stubs\Errors\CustomError;

class FooThrowCustomError
{
    /**
     * @return string[]
     * @throws CustomError
     */

    #[HasError(CustomError::class)]
    public function __invoke()
    {

        throw new CustomError();

        return [
            "name" => "abc"
        ];
    }
}
