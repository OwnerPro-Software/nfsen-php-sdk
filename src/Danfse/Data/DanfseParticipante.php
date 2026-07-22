<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * DTO permissivo para emitente, tomador ou intermediário do DANFSE.
 * Todos os campos são strings já formatadas (ou '-' para ausentes).
 *
 * @api
 */
final readonly class DanfseParticipante
{
    public function __construct(
        public string $nome,
        public string $cnpjCpf,
        public string $im,
        public string $telefone,
        public string $email,
        public string $endereco,
        public string $municipio,
        public string $cep,
        /**
         * Código IBGE do município. A NT 008 imprime "CÓDIGO IBGE / CEP" num campo
         * só; `cep` segue separado para quem consome o SDK fora da impressão.
         */
        public string $codigoIbge = '-',
        public string $simplesNacional = '-',
        public string $regimeSN = '-',
    ) {}

    /**
     * Campo "CÓDIGO IBGE / CEP" do item 2.4.5, que a NT preenche com `cMun + CEP`
     * no endereço nacional e com `cEndPost` no endereço no exterior — daí o campo
     * sair com um lado só quando o participante está fora do país, e não com o
     * traço de campo ausente ao lado do código postal.
     */
    public function codigoIbgeCep(): string
    {
        $partes = array_filter(
            [$this->codigoIbge, $this->cep],
            static fn (string $parte): bool => $parte !== '' && $parte !== '-',
        );

        return $partes === [] ? '-' : implode(' / ', $partes);
    }
}
