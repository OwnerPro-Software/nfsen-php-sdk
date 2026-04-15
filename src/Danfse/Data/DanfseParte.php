<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * DTO permissivo para emitente, tomador ou intermediário do DANFSE.
 * Todos os campos são strings já formatadas (ou '-' para ausentes).
 *
 * @api
 */
final readonly class DanfseParte
{
    public function __construct(
        public string $nome,
        public string $cnpjCpf,
        public string $im,
        public string $telefone,
        public string $email,
        public string $endereco,
        public string $municipio,
        public string $cep,
        public string $simplesNacional = '-',
        public string $regimeSN = '-',
    ) {}
}
