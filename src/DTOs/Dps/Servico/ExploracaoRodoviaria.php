<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\Enums\Dps\Servico\CategoriaVeiculo;
use Pulsar\NfseNacional\Enums\Dps\Servico\TipoRodagem;

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
}
