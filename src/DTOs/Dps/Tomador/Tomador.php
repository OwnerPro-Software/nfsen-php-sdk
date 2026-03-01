<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Tomador;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\DTOs\Dps\Shared\Endereco;
use Pulsar\NfseNacional\Enums\Dps\Shared\CodNaoNIF;

final readonly class Tomador
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CodNaoNIF $cNaoNIF = null,
        public ?string $CAEPF = null,
        public ?string $IM = null,
        public ?Endereco $end = null,
        public ?string $fone = null,
        public ?string $email = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'cNaoNIF' => $cNaoNIF],
            expected: 1,
            message: 'Tomador requer exatamente um entre CNPJ, CPF, NIF ou cNaoNIF.',
        );
    }
}
