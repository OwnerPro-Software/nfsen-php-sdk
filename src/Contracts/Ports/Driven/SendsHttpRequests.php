<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Ports\Driven;

interface SendsHttpRequests
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload): array;

    /** @return array<string, mixed> */
    public function get(string $url): array;

    public function head(string $url): int;
}
