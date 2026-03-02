<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\Enums\Dps\Servico\CategoriaServico;
use Pulsar\NfseNacional\Enums\Dps\Servico\ObjetoLocacao;

final readonly class LocacaoSublocacao
{
    public function __construct(
        public CategoriaServico $categ,
        public ObjetoLocacao $objeto,
        public string $extensao,
        public string $nPostes,
    ) {}
}
