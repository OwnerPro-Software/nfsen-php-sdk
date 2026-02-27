<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

use stdClass;

readonly class DpsData
{
    public function __construct(
        public stdClass $infDps,
        public stdClass $prestador,
        public stdClass $tomador,
        public stdClass $servico,
        public stdClass $valores,
    ) {}
}
