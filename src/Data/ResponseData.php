<?php

namespace B4zz4r\Lagger\Data;

class ResponseData
{
    public function __construct(
        public string $summary,
        public int $statusCode,
    ) {
    }
}
