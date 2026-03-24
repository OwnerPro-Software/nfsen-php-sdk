<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\IBSCBS;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\TpReeRepRes;

/**
 * @phpstan-import-type DFeNacionalArray from DFeNacional
 * @phpstan-import-type DocFiscalOutroArray from DocFiscalOutro
 * @phpstan-import-type DocOutroArray from DocOutro
 * @phpstan-import-type FornecArray from Fornec
 *
 * @phpstan-type DocumentosArray array{dtEmiDoc: string, dtCompDoc: string, tpReeRepRes: string, vlrReeRepRes: string, dFeNacional?: DFeNacionalArray, docFiscalOutro?: DocFiscalOutroArray, docOutro?: DocOutroArray, fornec?: FornecArray, xTpReeRepRes?: string}
 */
final readonly class Documentos
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $dtEmiDoc,
        public string $dtCompDoc,
        public TpReeRepRes $tpReeRepRes,
        public string $vlrReeRepRes,
        public ?DFeNacional $dFeNacional = null,
        public ?DocFiscalOutro $docFiscalOutro = null,
        public ?DocOutro $docOutro = null,
        public ?Fornec $fornec = null,
        public ?string $xTpReeRepRes = null,
    ) {
        self::validateChoice(
            ['DFe nacional (dFeNacional)' => $dFeNacional, 'documento fiscal outro (docFiscalOutro)' => $docFiscalOutro, 'outro documento (docOutro)' => $docOutro],
            expected: 1,
            path: 'infDPS/IBSCBS/infReeRepRes/documentos',
        );
    }

    /** @phpstan-param DocumentosArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            dtEmiDoc: $data['dtEmiDoc'],
            dtCompDoc: $data['dtCompDoc'],
            tpReeRepRes: TpReeRepRes::from($data['tpReeRepRes']),
            vlrReeRepRes: $data['vlrReeRepRes'],
            dFeNacional: isset($data['dFeNacional']) ? DFeNacional::fromArray($data['dFeNacional']) : null,
            docFiscalOutro: isset($data['docFiscalOutro']) ? DocFiscalOutro::fromArray($data['docFiscalOutro']) : null,
            docOutro: isset($data['docOutro']) ? DocOutro::fromArray($data['docOutro']) : null,
            fornec: isset($data['fornec']) ? Fornec::fromArray($data['fornec']) : null,
            xTpReeRepRes: $data['xTpReeRepRes'] ?? null,
        );
    }
}
