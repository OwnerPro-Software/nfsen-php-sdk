<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Responses;

final readonly class DanfseResponse
{
    /** @param list<ProcessingMessage> $erros */
    public function __construct(
        public bool $sucesso,
        public ?string $pdf = null,
        public array $erros = [],
    ) {}
}
