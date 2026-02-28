<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Support;

class TempFileFactory
{
    /** @return resource|false */
    public function __invoke(): mixed
    {
        return tmpfile();
    }
}
