<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Concerns;

use OwnerPro\Nfsen\Danfse\Municipios;
use SimpleXMLElement;

/**
 * Leitura tolerante de nós do XML da NFS-e.
 *
 * Compartilhado por quem monta `NfseData` a partir do XML. Os blocos opcionais do
 * XSD (`minOccurs=0`) chegam como null ou elemento vazio, e é isso que estes
 * métodos normalizam — sem eles, cada acesso precisaria do próprio guard.
 *
 * @internal
 */
trait ReadsXmlNodes
{
    /**
     * Converte um nó SimpleXMLElement para string, devolvendo default quando vazio ou null.
     *
     * Nota: SimpleXML retorna null ao acessar child de elemento vazio (ex.: `<tribFed/>`).
     * Aceitar null simplifica o fluxo para blocos XSD opcionais (tribFed, piscofins, pTotTrib, BM, etc.).
     */
    private function str(?SimpleXMLElement $node, string $default = ''): string
    {
        if (! $node instanceof SimpleXMLElement) { // @pest-mutate-ignore InstanceOfToTrue — (string) null = ''; mutar o guard dá o mesmo resultado observável (retorna default via branch $s === '').
            return $default; // @pest-mutate-ignore RemoveEarlyReturn — idem; early return é redundância defensiva.
        }

        $s = trim((string) $node);

        return $s !== '' ? $s : $default;
    }

    /** Primeiro nó não vazio da lista, ou string vazia. */
    private function firstOf(?SimpleXMLElement ...$nodes): string
    {
        foreach ($nodes as $node) {
            $valor = $this->str($node);
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    /**
     * Resolve um município via tabela IBGE a partir do código, com fallback para o texto do portal.
     *
     * O item 2.4.5 manda concatenar município e UF como "Município / UF". Com o código do IBGE
     * presente e válido, é dele que sai o par; senão cai no texto literal do XML, que vem sem UF;
     * e em último caso, '-'.
     */
    private function resolveMunicipio(?SimpleXMLElement $cMun, ?SimpleXMLElement $xFallback): string
    {
        $code = $this->str($cMun);
        if ($code !== '') { // @pest-mutate-ignore EmptyStringToNotEmpty — guard short-circuit; Municipios::lookup('') retorna '-' e a lógica cai para xFallback com mesmo efeito observável.
            $lookup = Municipios::lookup($code);
            if ($lookup !== '-') {
                return $lookup;
            }
        }

        return $this->str($xFallback, '-');
    }

    /**
     * Monta o endereço na ordem que a NT 008 exige: logradouro, número, complemento
     * e bairro (seções 2.1.3, 2.1.4 e 2.1.6).
     *
     * O complemento é opcional no XSD (`TSComplementoEndereco`) e some do resultado
     * quando ausente — mas quando existe tem de sair impresso, senão o endereço do
     * documento fiscal fica incompleto.
     */
    private function joinAddress(
        ?SimpleXMLElement $xLgr,
        ?SimpleXMLElement $nro,
        ?SimpleXMLElement $xCpl,
        ?SimpleXMLElement $xBairro,
    ): string {
        return implode(', ', array_filter([
            trim((string) $xLgr),
            trim((string) $nro),
            trim((string) $xCpl),
            trim((string) $xBairro),
        ], fn (string $v): bool => $v !== ''));
    }
}
