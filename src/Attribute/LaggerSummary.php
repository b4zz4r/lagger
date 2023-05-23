<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\SummaryInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerSummary implements SummaryInterface
{
    public function __construct(public string $summary)
    {
        //
    }

    public function getSummary(): string
    {
        return $this->summary;
    }
}
