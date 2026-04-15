<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface ConvertsHtmlToPdf
{
    public function convert(string $html): string;
}
