<?php
namespace B4zz4r\LaravelSwagger\Interfaces;

use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;

interface ResourceInterface
{
    public function specification(): SpecificationInterface;
}
