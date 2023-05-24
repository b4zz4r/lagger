<?php
namespace B4zz4r\Lagger\Concerns;

interface ResponseInterface
{
    /**
     * @return array<ResponseData>
     */
    public function getResponses(): array;
}
