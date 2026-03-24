<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface SignsXml
{
    public function sign(string $xml, string $tagname, string $rootname): string;
}
