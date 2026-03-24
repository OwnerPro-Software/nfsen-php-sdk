<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Support;

class TempFileFactory
{
    /** @return resource|false */
    public function __invoke(): mixed
    {
        return tmpfile();
    }
}
