<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

/**
 * @api
 */
final readonly class NfseEmitted
{
    public function __construct(
        public string $chave,
    ) {}
}
