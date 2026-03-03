<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Servico;

use Pulsar\NfseNacional\Enums\Dps\Servico\CategoriaServico;
use Pulsar\NfseNacional\Enums\Dps\Servico\ObjetoLocacao;

/**
 * @phpstan-type LocacaoSublocacaoArray array{categ: string, objeto: string, extensao: string, nPostes: string}
 */
final readonly class LocacaoSublocacao
{
    public function __construct(
        public CategoriaServico $categ,
        public ObjetoLocacao $objeto,
        public string $extensao,
        public string $nPostes,
    ) {}

    /** @phpstan-param LocacaoSublocacaoArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            categ: CategoriaServico::from($data['categ']),
            objeto: ObjetoLocacao::from($data['objeto']),
            extensao: $data['extensao'],
            nPostes: $data['nPostes'],
        );
    }
}
