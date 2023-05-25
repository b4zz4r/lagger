<?php
namespace B4zz4r\Lagger\Concerns;

use B4zz4r\Lagger\Data\ResponseData;

interface ResponseInterface
{
    /**
     * @return ResponseData[]
     */
    public function getResponses(): array;
}
