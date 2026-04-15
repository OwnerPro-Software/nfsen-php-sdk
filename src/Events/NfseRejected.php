<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

/**
 * @api
 */
final readonly class NfseRejected
{
    public function __construct(
        public string $operacao,
        public string $codigoErro,
    ) {}
}
