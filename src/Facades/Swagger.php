<?php

namespace B4zz4r\Lagger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \B4zz4r\Lagger\Swagger
 */
class Swagger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-swagger';
    }
}
