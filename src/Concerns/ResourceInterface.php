<?php
namespace B4zz4r\Lagger\Concerns;

use B4zz4r\Lagger\Concerns\SpecificationInterface;

interface ResourceInterface
{
    public function specification(): SpecificationInterface;
}
