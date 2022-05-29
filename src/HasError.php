<?php

namespace JBernavaPrah\LighthouseErrorHandler;

use Attribute;

#[Attribute]
class HasError
{

    public function __construct(public string $error)
    {
    }
}
