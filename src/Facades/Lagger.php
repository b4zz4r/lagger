<?php

namespace B4zz4r\Lagger\Facades;

use Illuminate\Support\Facades\Facade;

class Lagger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'lagger';
    }
}
