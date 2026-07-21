<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseServico
{
    public function __construct(
        public string $codigoTribNacional,
        public string $descTribNacional,
        public string $codigoTribMunicipal,
        public string $descTribMunicipal,
        public string $localPrestacao,
        public string $paisPrestacao,
        public string $descricao,
        public string $codigoNbs,
        /**
         * Campo único do DANFSe para a descrição do código de tributação.
         *
         * A NT 008, item 2.4.5, define o conteúdo como `xTribNac + xTribMun` com a
         * regra `SE xTribMun <> "" ENTAO Descrição Municipal SENAO Descrição Nacional`
         * — nunca as duas. `descTribNacional` e `descTribMunicipal` seguem expostos
         * para quem precisa das partes, mas o documento imprime este.
         *
         * Resolvido no builder, não aqui: esta pasta está fora da cobertura em
         * `phpunit.xml` por conter só DTOs sem lógica, e uma regra da NT escondida
         * num ponto cego de cobertura e mutação é como os defeitos anteriores nasceram.
         */
        public string $descricaoTributacao = '-',
    ) {}
}
