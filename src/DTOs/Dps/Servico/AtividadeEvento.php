<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EnderecoSimplesArray from EnderecoSimples
 *
 * @phpstan-type AtividadeEventoArray array{xNome: string, dtIni: string, dtFim: string, idAtvEvt?: string, end?: EnderecoSimplesArray}
 */
final readonly class AtividadeEvento
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public string $dtIni,
        public string $dtFim,
        public ?string $idAtvEvt = null,
        public ?EnderecoSimples $end = null,
    ) {
        self::validateChoice(
            ['idAtvEvt' => $idAtvEvt, 'end' => $end],
            expected: 1,
            message: 'AtividadeEvento requer exatamente um entre idAtvEvt ou end.',
        );
    }

    /** @phpstan-param AtividadeEventoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xNome: $data['xNome'],
            dtIni: $data['dtIni'],
            dtFim: $data['dtFim'],
            idAtvEvt: $data['idAtvEvt'] ?? null,
            end: isset($data['end']) ? EnderecoSimples::fromArray($data['end']) : null,
        );
    }
}
