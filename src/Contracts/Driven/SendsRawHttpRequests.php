<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

use OwnerPro\Nfsen\Responses\HttpResponse;

interface SendsRawHttpRequests
{
    public function getResponse(string $url): HttpResponse;
}
