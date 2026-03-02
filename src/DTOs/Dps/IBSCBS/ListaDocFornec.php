<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Enums\Dps\Shared\CodNaoNIF;

final readonly class ListaDocFornec
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public ?string $CNPJ = null,
        public ?string $CPF = null,
        public ?string $NIF = null,
        public ?CodNaoNIF $cNaoNIF = null,
    ) {
        self::validateChoice(
            ['CNPJ' => $CNPJ, 'CPF' => $CPF, 'NIF' => $NIF, 'cNaoNIF' => $cNaoNIF],
            expected: 1,
            message: 'ListaDocFornec requer exatamente um entre CNPJ, CPF, NIF ou cNaoNIF.',
        );
    }
}
