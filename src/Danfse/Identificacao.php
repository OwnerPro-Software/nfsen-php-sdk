<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;

/**
 * Resolve o campo "CNPJ / CPF / NIF" da DANFSe a partir dos quatro nós que o XSD oferece.
 *
 * A escolha do formato vem da **procedência**, não da forma do texto: o schema já declara
 * o que cada nó carrega (`TSCNPJ`, `TSCPF`, `TSNIF`), então não há o que adivinhar. Só
 * CNPJ e CPF passam pelo formatter; NIF é identificador estrangeiro de texto livre — até
 * 40 caracteres, com prefixo de país e letras — e sai como veio. Mascará-lo apagava
 * caracteres em silêncio, e 'ES-B12345678' virava '12345678' num documento fiscal.
 *
 * `cNaoNIF` não identifica ninguém: é o código que justifica a ausência do NIF
 * (`TSCodNaoNIF`). Sem ele, um participante dispensado do NIF saía '-', sem dizer por quê.
 *
 * @internal
 */
final readonly class Identificacao
{
    public function __construct(private Formatter $fmt = new Formatter) {}

    public function __invoke(string $cnpj, string $cpf, string $nif, string $cNaoNIF): string
    {
        $brasileiro = $cnpj !== '' ? $cnpj : $cpf;

        if ($brasileiro !== '') {
            return $this->fmt->cnpjCpf($brasileiro);
        }

        if ($nif !== '') {
            return $nif;
        }

        return CNaoNIF::labelOf($cNaoNIF);
    }
}
