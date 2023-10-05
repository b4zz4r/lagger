<?php

namespace B4zz4r\Lagger\Data;

use B4zz4r\Lagger\Concerns\DescriptionInterface;
use B4zz4r\Lagger\Concerns\ParametersInterface;
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
    public function __construct(
        public string $name,
        public string $route,
        public string $method,
        public RequestInterface $request,
        public ResourceInterface|Response|JsonResponse|BinaryFileResponse|StreamedResponse $response,
        public ?DescriptionInterface $description,
        public ?SummaryInterface $summary,
        public ?TagInterface $tag,
        public ?ParametersInterface $parameters,
        public array $responses = [],
    ) {
    }
}
