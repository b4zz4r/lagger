<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\RulesInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerRulesDescription implements RulesInterface
{
    public function __construct(public array $rules)
    {
        //
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}
