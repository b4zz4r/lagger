<?php
namespace B4zz4r\LaravelSwagger\Concerns;

use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;

interface ResourceInterface
{
    public function specification(): SpecificationInterface;
}
