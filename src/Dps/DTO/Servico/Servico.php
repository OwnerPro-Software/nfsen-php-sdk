<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Servico;

use Pulsar\NfseNacional\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type CodigoServicoArray from CodigoServico
 * @phpstan-import-type ComercioExteriorArray from ComercioExterior
 * @phpstan-import-type ObraArray from Obra
 * @phpstan-import-type AtividadeEventoArray from AtividadeEvento
 * @phpstan-import-type InfoComplementarArray from InfoComplementar
 *
 * @phpstan-type ServicoArray array{cServ: CodigoServicoArray, cLocPrestacao?: string, cPaisPrestacao?: string, comExt?: ComercioExteriorArray, obra?: ObraArray, atvEvento?: AtividadeEventoArray, infoCompl?: InfoComplementarArray}
 */
final readonly class Servico
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public CodigoServico $cServ,
        public ?string $cLocPrestacao = null,
        public ?string $cPaisPrestacao = null,
        public ?ComercioExterior $comExt = null,
        public ?Obra $obra = null,
        public ?AtividadeEvento $atvEvento = null,
        public ?InfoComplementar $infoCompl = null,
    ) {
        self::validateChoice(
            ['cLocPrestacao' => $cLocPrestacao, 'cPaisPrestacao' => $cPaisPrestacao],
            expected: 1,
            message: 'Serviço requer exatamente um entre cLocPrestacao ou cPaisPrestacao.',
        );
    }

    /** @phpstan-param ServicoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            cServ: CodigoServico::fromArray($data['cServ']),
            cLocPrestacao: $data['cLocPrestacao'] ?? null,
            cPaisPrestacao: $data['cPaisPrestacao'] ?? null,
            comExt: isset($data['comExt']) ? ComercioExterior::fromArray($data['comExt']) : null,
            obra: isset($data['obra']) ? Obra::fromArray($data['obra']) : null,
            atvEvento: isset($data['atvEvento']) ? AtividadeEvento::fromArray($data['atvEvento']) : null,
            infoCompl: isset($data['infoCompl']) ? InfoComplementar::fromArray($data['infoCompl']) : null,
        );
    }
}
