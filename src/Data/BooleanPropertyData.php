<?php

namespace B4zz4r\LaravelSwagger\Data;

class BooleanPropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        return [
            'type' => 'boolean',
            'example' => $this->reflectionProperty->getDefaultValue(),
        ];
    }
}
