<?php

namespace B4zz4r\LaravelSwagger\DTOs;

use B4zz4r\LaravelSwagger\Concerns\DescriptionInterface;
use B4zz4r\LaravelSwagger\Concerns\RequestInterface;
use B4zz4r\LaravelSwagger\Concerns\ResourceInterface;
use B4zz4r\LaravelSwagger\Concerns\SummaryInterface;
use B4zz4r\LaravelSwagger\Concerns\TagInterface;
use Spatie\DataTransferObject\DataTransferObject;

class SpecificationDTO extends DataTransferObject
{
    public string $route;
    public string $method;
    public DescriptionInterface|null $description;
    public SummaryInterface|null $summary;
    public string $name;
    public ResourceInterface $response;
    public RequestInterface|null $request;

    /** @var array<TagInterface>|null */
    public array|null $tags;
}
