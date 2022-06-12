<?php

namespace B4zz4r\Lagger\Data;

use B4zz4r\Lagger\Concerns\DescriptionInterface;
use B4zz4r\Lagger\Concerns\RequestInterface;
use B4zz4r\Lagger\Concerns\ResourceInterface;
use B4zz4r\Lagger\Concerns\SummaryInterface;
use B4zz4r\Lagger\Concerns\TagInterface;
use Spatie\LaravelData\Data;

class SpecificationData extends Data
{
    public function __construct(
        public string $name,
        public string $route,
        public string $method,
        public RequestInterface $request,
        public ResourceInterface $response,
        public ?DescriptionInterface $description,
        public ?SummaryInterface $summary,
        public ?TagInterface $tag,
    ) {
    }
}
