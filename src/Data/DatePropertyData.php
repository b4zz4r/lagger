<?php

namespace B4zz4r\Lagger\Data;

class DatePropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        return [
            'type' => 'string',
            'format' => 'date',
            'example' => $this->reflectionProperty->getDefaultValue(),
        ];
    }
}
