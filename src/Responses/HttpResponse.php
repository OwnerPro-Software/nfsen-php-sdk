<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

final readonly class HttpResponse
{
    /** @param array<string, mixed> $json */
    public function __construct(
        public int $statusCode,
        public array $json,
        public string $body,
    ) {}
}
