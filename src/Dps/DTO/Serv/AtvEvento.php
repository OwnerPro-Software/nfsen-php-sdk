<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Dps\DTO\Serv;

use OwnerPro\Nfsen\Dps\DTO\Concerns\ValidatesExclusiveChoice;

/**
 * @phpstan-import-type EndSimplesArray from EndSimples
 *
 * @phpstan-type AtvEventoArray array{xNome: string, dtIni: string, dtFim: string, idAtvEvt?: string, end?: EndSimplesArray}
 */
final readonly class AtvEvento
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public string $dtIni,
        public string $dtFim,
        public ?string $idAtvEvt = null,
        public ?EndSimples $end = null,
    ) {
        self::validateChoice(
            ['identificação da atividade/evento (idAtvEvt)' => $idAtvEvt, 'endereço (end)' => $end],
            expected: 1,
            path: 'infDPS/serv/atvEvento',
        );
    }

    /** @phpstan-param AtvEventoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            xNome: $data['xNome'],
            dtIni: $data['dtIni'],
            dtFim: $data['dtFim'],
            idAtvEvt: $data['idAtvEvt'] ?? null,
            end: isset($data['end']) ? EndSimples::fromArray($data['end']) : null,
        );
    }
}
