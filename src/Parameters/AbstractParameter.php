<?php

namespace B4zz4r\Lagger\Parameters;

use B4zz4r\Lagger\Concerns\ParameterInterface;
use B4zz4r\Lagger\Enums\InEnum;

class AbstractParameter implements ParameterInterface
{
    public function isRequired(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return [];
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * @return string path or query
     */
    public function in(): InEnum
    {
        return InEnum::PATH;
    }

    public function toArray(string $name, array $withExtraDefinition = []): array
    {
        return array_merge(
            [
                'in' => $this->in()->value,
                'name' => $name,
                'schema' => $this->schema(),
                'description' => $this->description(),
                'required' => $this->isRequired(),
            ],
            $withExtraDefinition
        );
    }
}
