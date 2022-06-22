<?php

namespace B4zz4r\LaravelSwagger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \B4zz4r\LaravelSwagger\Swagger
 */
class Swagger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-swagger';
    }
}
