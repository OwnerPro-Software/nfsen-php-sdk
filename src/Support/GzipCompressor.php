<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

class GzipCompressor
{
    public function __invoke(string $data): string|false
    {
        return gzencode($data);
    }
}
