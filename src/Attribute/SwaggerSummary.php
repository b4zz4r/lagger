<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class SwaggerSummary implements SummaryInterface
{
    public function __construct(public string $summary,) 
    {
        // $this->getSummary($summary);
    }

    public function getSummary($summary)
    {
        return $summary;
    }
}