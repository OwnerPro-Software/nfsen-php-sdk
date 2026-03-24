<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

final readonly class NfseSubstituted
{
    public function __construct(
        public string $chave,
        public string $chaveSubstituta,
    ) {}
}
