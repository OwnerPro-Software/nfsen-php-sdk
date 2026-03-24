<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Support;

class FileReader
{
    public function __invoke(string $path): string|false
    {
        return file_get_contents($path);
    }
}
