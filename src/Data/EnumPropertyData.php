<?php

namespace B4zz4r\LaravelSwagger\Data;

use Illuminate\Support\Arr;
use UnitEnum;

class EnumPropertyData extends AbstractPropertyData
{
    public function toArray(): array
    {
        $items = Arr::map(
            $this->reflectionProperty->getDefaultValue()?->cases(),
            fn (UnitEnum $item) => $item->value
        );

        $type = match (gettype($this->reflectionProperty->getDefaultValue()?->value)) {
            'double' => 'number',
            'integer' => 'integer',
            'boolean' => 'boolean',
            'resource' => 'object',
            default => 'string'
        };

        return [
            'type' => $type,
            'enum' => $items,
            'example' => $this->reflectionProperty->getDefaultValue()?->value,
        ];
    }
}
