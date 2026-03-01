<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Prestador;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\DTOs\Dps\Shared\Endereco;
use Pulsar\NfseNacional\DTOs\Dps\Shared\RegTrib;
use Pulsar\NfseNacional\Enums\Dps\Shared\CodNaoNIF;

final readonly class Prestador
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public RegTrib $regTrib,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CodNaoNIF $cNaoNIF = null,
        public ?string $CAEPF = null,
        public ?string $IM = null,
        public ?string $xNome = null,
        public ?Endereco $end = null,
        public ?string $fone = null,
        public ?string $email = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'cNaoNIF' => $cNaoNIF],
            expected: 1,
            message: 'Prestador requer exatamente um entre CNPJ, CPF, NIF ou cNaoNIF.',
        );
    }
}
