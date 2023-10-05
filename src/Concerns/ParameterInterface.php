<?php

namespace B4zz4r\Lagger\Concerns;

use B4zz4r\Lagger\Enums\InEnum;

interface ParameterInterface
{
    public function isRequired(): bool;

    public function schema(): array;

    public function description(): ?string;

    public function in(): InEnum;
}
