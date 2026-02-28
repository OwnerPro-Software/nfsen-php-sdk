<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

class FileReader
{
    public function __invoke(string $path): string|false
    {
        return file_get_contents($path);
    }
}
