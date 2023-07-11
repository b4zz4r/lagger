<?php

namespace B4zz4r\Lagger\Data;

class DateTimePropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        return [
            'type' => 'string',
            'format' => 'date-time',
            'example' => $this->reflectionProperty->getDefaultValue(),
        ];
    }
}
