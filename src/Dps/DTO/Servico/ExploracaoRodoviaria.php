<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Servico;

use Pulsar\NfseNacional\Enums\Dps\Servico\CategoriaVeiculo;
use Pulsar\NfseNacional\Enums\Dps\Servico\TipoRodagem;

/**
 * @phpstan-type ExploracaoRodoviariaArray array{categVeic: string, nEixos: string, rodagem: string, sentido: string, placa: string, codAcessoPed: string, codContrato: string}
 */
final readonly class ExploracaoRodoviaria
{
    public function __construct(
        public CategoriaVeiculo $categVeic,
        public string $nEixos,
        public TipoRodagem $rodagem,
        public string $sentido,
        public string $placa,
        public string $codAcessoPed,
        public string $codContrato,
    ) {}

    /** @phpstan-param ExploracaoRodoviariaArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            categVeic: CategoriaVeiculo::from($data['categVeic']),
            nEixos: $data['nEixos'],
            rodagem: TipoRodagem::from($data['rodagem']),
            sentido: $data['sentido'],
            placa: $data['placa'],
            codAcessoPed: $data['codAcessoPed'],
            codContrato: $data['codContrato'],
        );
    }
}
