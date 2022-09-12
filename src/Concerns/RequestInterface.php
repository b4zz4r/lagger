<?php
namespace B4zz4r\LaravelSwagger\Concerns;

use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;

interface RequestInterface
{
    public function rules(): array;
}
