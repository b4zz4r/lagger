<?php

namespace B4zz4r\LaravelSwagger\Data;

class ArrayPropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        return [
            'type' => 'array',
            'example' => $this->reflectionProperty->getDefaultValue(),
        ];
    }
}
