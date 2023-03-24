<?php

namespace B4zz4r\LaravelSwagger\Data;

class StringPropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        return [
            'type' => 'string',
            'example' => $this->reflectionProperty->getDefaultValue(),
        ];
    }
}
