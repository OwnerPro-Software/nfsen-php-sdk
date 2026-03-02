<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\IBSCBS;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Enums\Dps\IBSCBS\TpReeRepRes;

final readonly class ListaDocReeRepRes
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $dtEmiDoc,
        public string $dtCompDoc,
        public TpReeRepRes $tpReeRepRes,
        public string $vlrReeRepRes,
        public ?ListaDocDFe $dFeNacional = null,
        public ?ListaDocFiscalOutro $docFiscalOutro = null,
        public ?ListaDocOutro $docOutro = null,
        public ?ListaDocFornec $fornec = null,
        public ?string $xTpReeRepRes = null,
    ) {
        self::validateChoice(
            ['dFeNacional' => $dFeNacional, 'docFiscalOutro' => $docFiscalOutro, 'docOutro' => $docOutro],
            expected: 1,
            message: 'ListaDocReeRepRes requer exatamente um entre dFeNacional, docFiscalOutro ou docOutro.',
        );
    }
}
