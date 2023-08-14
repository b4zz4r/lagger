<?php

namespace B4zz4r\Lagger\Data;

use B4zz4r\Lagger\Concerns\DescriptionInterface;
use B4zz4r\Lagger\Concerns\RequestInterface;
use B4zz4r\Lagger\Concerns\ResourceInterface;
use B4zz4r\Lagger\Concerns\SummaryInterface;
use B4zz4r\Lagger\Concerns\TagInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpecificationData extends Data
{
    /**
     * @param string $name
     * @param string $route
     * @param string $method
     * @param RequestInterface $request
     * @param ResourceInterface|Response|JsonResponse $response
     * @param DescriptionInterface|null $description
     * @param SummaryInterface|null $summary
     * @param TagInterface|null $tag
     * @param array<ResponseData> $responses
     */
    public function __construct(
        public string $name,
        public string $route,
        public string $method,
        public RequestInterface $request,
        public ResourceInterface|Response|JsonResponse|BinaryFileResponse|StreamedResponse $response,
        public ?DescriptionInterface $description,
        public ?SummaryInterface $summary,
        public ?TagInterface $tag,
        public array $responses = [],
    ) {
    }
}
