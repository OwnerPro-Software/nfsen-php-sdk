<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driven;

interface GeneratesQrCode
{
    /** Retorna um data URI (SVG ou PNG) com o conteúdo codificado. */
    public function dataUri(string $payload): string;
}
