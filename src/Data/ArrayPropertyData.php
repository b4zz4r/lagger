<?php

namespace B4zz4r\Lagger\Data;

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
