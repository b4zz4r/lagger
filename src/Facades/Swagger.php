<?php

namespace B4zz4r\Swagger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \B4zz4r\Swagger\Swagger
 */
class Swagger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-swagger';
    }
}
