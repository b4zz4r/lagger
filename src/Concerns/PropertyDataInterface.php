<?php

namespace B4zz4r\Lagger\Concerns;

interface PropertyDataInterface
{
    public function toArray(): array;

    public function getSpecification(): array;
}
