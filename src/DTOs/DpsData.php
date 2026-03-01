<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs;

use stdClass;

final readonly class DpsData
{
    public function __construct(
        public stdClass $infDPS,
        public stdClass $prest,
        public stdClass $toma,
        public stdClass $serv,
        public stdClass $valores,
    ) {}
}
