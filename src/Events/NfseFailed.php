<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Events;

/**
 * @api
 */
final readonly class NfseFailed
{
    public function __construct(
        public string $operacao,
        public string $mensagem,
    ) {}
}
