<?php

namespace B4zz4r\Lagger\Data;

class IntegerPropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        return [
            'type' => 'integer',
            // 'nullable' => $this->reflectionProperty->getType()?->allowsNull() === true,
            'example' => $this->reflectionProperty->getDefaultValue(),
        ];
    }
}
