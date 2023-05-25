<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\ResponseInterface;
use B4zz4r\Lagger\Data\ResponseData;
use http\Env\Response;
use Illuminate\Support\Arr;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerResponse implements ResponseInterface
{
    /**
     * @param array<int, string> $responses
     */
    public function __construct(
        public array $responses = [],
    ) {
        //
    }

    /**
     * @return ResponseData[]
     */
    public function getResponses(): array
    {
        return Arr::map($this->responses, fn ($summary, $statusCode) => new ResponseData($summary, $statusCode));
    }
}
