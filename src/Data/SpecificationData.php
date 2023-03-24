<?php

namespace B4zz4r\LaravelSwagger\Data;

use B4zz4r\LaravelSwagger\Concerns\DescriptionInterface;
use B4zz4r\LaravelSwagger\Concerns\RequestInterface;
use B4zz4r\LaravelSwagger\Concerns\ResourceInterface;
use B4zz4r\LaravelSwagger\Concerns\SummaryInterface;
use B4zz4r\LaravelSwagger\Concerns\TagInterface;
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
