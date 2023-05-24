<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\ResponseInterface;
use B4zz4r\Lagger\Data\ResponseData;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerResponse implements ResponseInterface
{
    /**
     * @param array<ResponseData> $responses
     */
    public function __construct(
        public array $responses,
    ) {
        //
    }

    public function getResponses(): array
    {
        return $this->responses;
    }
}
