<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\IBSCBS;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use Pulsar\NfseNacional\Dps\Enums\IBSCBS\TpReeRepRes;

/**
 * @phpstan-import-type ListaDocDFeArray from ListaDocDFe
 * @phpstan-import-type ListaDocFiscalOutroArray from ListaDocFiscalOutro
 * @phpstan-import-type ListaDocOutroArray from ListaDocOutro
 * @phpstan-import-type ListaDocFornecArray from ListaDocFornec
 *
 * @phpstan-type ListaDocReeRepResArray array{dtEmiDoc: string, dtCompDoc: string, tpReeRepRes: string, vlrReeRepRes: string, dFeNacional?: ListaDocDFeArray, docFiscalOutro?: ListaDocFiscalOutroArray, docOutro?: ListaDocOutroArray, fornec?: ListaDocFornecArray, xTpReeRepRes?: string}
 */
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

    /** @phpstan-param ListaDocReeRepResArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            dtEmiDoc: $data['dtEmiDoc'],
            dtCompDoc: $data['dtCompDoc'],
            tpReeRepRes: TpReeRepRes::from($data['tpReeRepRes']),
            vlrReeRepRes: $data['vlrReeRepRes'],
            dFeNacional: isset($data['dFeNacional']) ? ListaDocDFe::fromArray($data['dFeNacional']) : null,
            docFiscalOutro: isset($data['docFiscalOutro']) ? ListaDocFiscalOutro::fromArray($data['docFiscalOutro']) : null,
            docOutro: isset($data['docOutro']) ? ListaDocOutro::fromArray($data['docOutro']) : null,
            fornec: isset($data['fornec']) ? ListaDocFornec::fromArray($data['fornec']) : null,
            xTpReeRepRes: $data['xTpReeRepRes'] ?? null,
        );
    }
}
