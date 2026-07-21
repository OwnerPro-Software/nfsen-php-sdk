<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse;

use OwnerPro\Nfsen\Danfse\Concerns\ReadsXmlNodes;
use OwnerPro\Nfsen\Danfse\Data\DanfseParticipante;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;
use SimpleXMLElement;

/**
 * Monta os blocos de participante do DANFSe a partir do XML da NFS-e.
 *
 * Separado de `DanfseDataBuilder` porque os três blocos — prestador, tomador e
 * intermediário — compartilham forma (identificação, endereço, município) mas
 * saem de nós distintos, com regras próprias de fallback. Manter isso junto com
 * tributação e totais levou a classe além do limite de complexidade cognitiva.
 *
 * @internal
 */
final readonly class ParticipanteBuilder
{
    use ReadsXmlNodes;

    public function __construct(
        private Formatter $fmt = new Formatter,
        private Identificacao $identificacao = new Identificacao,
    ) {}

    /**
     * Bloco "PRESTADOR / FORNECEDOR".
     *
     * A NT 008, item 2.4.5, amarra todo este bloco a `infDPS/prest/` — não a
     * `infNFSe/emit/`, que é de onde o builder lia. Os dois nós existem e quase
     * sempre carregam a mesma empresa, então a leitura errada não falhava nunca:
     * mesma forma do defeito 3.0.0.
     *
     * Trocar de nó sem mais nada, porém, perderia dado. Em `TCInfoPrestador` os
     * campos xNome, end, fone, email e IM são minOccurs=0, enquanto em `TCEmitente`
     * xNome e enderNac são obrigatórios — a DPS costuma declarar só a inscrição do
     * prestador e é o fisco quem preenche o cadastro em `emit`. Daí o fallback por
     * campo: a fonte é a que a NT manda, e `emit` cobre o que ela omitir.
     */
    public function prestador(SimpleXMLElement $emit, SimpleXMLElement $inf, SimpleXMLElement $prest): DanfseParticipante
    {
        $regTrib = $prest->regTrib;
        $endPrest = $prest->end;
        $enderEmit = $emit->enderNac;

        // TCEmitente abre com <xs:choice>CNPJ|CPF</xs:choice> obrigatório, sem NIF.
        // O prestador estrangeiro só existe em TCInfoPrestador, que aceita
        // CNPJ|CPF|NIF|cNaoNIF — por isso NIF e cNaoNIF não têm fallback.
        $identificacao = ($this->identificacao)(
            $this->firstOf($prest->CNPJ, $emit->CNPJ),
            $this->firstOf($prest->CPF, $emit->CPF),
            $this->str($prest->NIF),
            $this->str($prest->cNaoNIF),
        );

        $endereco = $this->joinAddress($endPrest?->xLgr, $endPrest?->nro, $endPrest?->xCpl, $endPrest?->xBairro); // @pest-mutate-ignore RemoveNullSafeOperator — end é minOccurs=0 em TCInfoPrestador; ?-> previne warning quando ausente.
        if ($endereco === '') {
            $endereco = $this->joinAddress($enderEmit->xLgr, $enderEmit->nro, $enderEmit->xCpl, $enderEmit->xBairro);
        }

        return new DanfseParticipante(
            nome: $this->firstOf($prest->xNome, $emit->xNome) ?: '-',
            cnpjCpf: $identificacao,
            im: $this->firstOf($prest->IM, $emit->IM) ?: '-',
            telefone: $this->fmt->phone($this->firstOf($prest->fone, $emit->fone)),
            email: $this->firstOf($prest->email, $emit->email),
            endereco: $endereco !== '' ? $endereco : '-',
            municipio: $this->municipioDoPrestador($endPrest, $enderEmit, $inf),
            cep: $this->fmt->cep($this->firstOf($endPrest?->endNac?->CEP, $enderEmit->CEP)), // @pest-mutate-ignore RemoveNullSafeOperator — end/endNac são opcionais no XSD.
            codigoIbge: $this->firstOf($endPrest?->endNac?->cMun, $enderEmit->cMun) ?: '-', // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            simplesNacional: OpSimpNac::labelOf($this->str($regTrib->opSimpNac)),
            regimeSN: RegApTribSN::labelOf($this->str($regTrib->regApTribSN)),
        );
    }

    /** Bloco "TOMADOR / ADQUIRENTE". Ausente, vira o bloco de "não identificado". */
    public function tomador(SimpleXMLElement $toma): DanfseParticipante
    {
        if ($toma->count() === 0) {
            return $this->naoIdentificado();
        }

        return $this->deInfoPessoa($toma);
    }

    /** Bloco "INTERMEDIÁRIO DA OPERAÇÃO". */
    public function intermediario(SimpleXMLElement $interm): DanfseParticipante
    {
        return $this->deInfoPessoa($interm);
    }

    /**
     * Bloco "DESTINATÁRIO DA OPERAÇÃO" (`infDPS/IBSCBS/dest`).
     *
     * Sem inscrição municipal: `TCRTCInfoDest` não declara `IM`, e a NT 008, item
     * 2.1.5, também não lista o campo para o destinatário — ao contrário do que faz
     * para prestador, tomador e intermediário.
     */
    public function destinatario(SimpleXMLElement $dest): DanfseParticipante
    {
        return $this->participanteDe($dest, '-');
    }

    public function naoIdentificado(): DanfseParticipante
    {
        return new DanfseParticipante('-', '-', '-', '-', '-', '-', '-', '-');
    }

    /**
     * Tomador e intermediário são ambos `TCInfoPessoa`: mesma forma, mesmos nomes de
     * tag, mesma regra de endereço. A única diferença é o nó de onde saem.
     */
    private function deInfoPessoa(SimpleXMLElement $pessoa): DanfseParticipante
    {
        return $this->participanteDe($pessoa, $this->str($pessoa->IM, '-'));
    }

    /**
     * Parte comum a todos os blocos de participante que não o prestador.
     *
     * A inscrição municipal entra pronta porque é o único campo que o destinatário
     * não tem — ler `$pessoa->IM` aqui resolveria para um caminho que o XSD não
     * declara sob `IBSCBS/dest`.
     */
    private function participanteDe(SimpleXMLElement $pessoa, string $im): DanfseParticipante
    {
        $end = $pessoa->end;
        $endNac = $end->endNac;

        $identificacao = ($this->identificacao)(
            $this->str($pessoa->CNPJ),
            $this->str($pessoa->CPF),
            $this->str($pessoa->NIF),
            $this->str($pessoa->cNaoNIF),
        );

        $endereco = $this->joinAddress($end?->xLgr, $end?->nro, $end?->xCpl, $end?->xBairro); // @pest-mutate-ignore RemoveNullSafeOperator — end é minOccurs=0 no XSD; ?-> previne crash quando <end> ausente.

        return new DanfseParticipante(
            nome: $this->str($pessoa->xNome, '-'),
            cnpjCpf: $identificacao,
            im: $im,
            telefone: $this->fmt->phone($this->str($pessoa->fone)),
            email: $this->str($pessoa->email),
            endereco: $endereco !== '' ? $endereco : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — guard defensivo; joinAddress() já normaliza para '' quando vazio.
            municipio: Municipios::lookup($this->str($endNac?->cMun)), // @pest-mutate-ignore RemoveNullSafeOperator — endNac null quando <end> ausente.
            cep: $this->fmt->cep($this->str($endNac?->CEP)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            codigoIbge: $this->str($endNac?->cMun, '-'), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        );
    }

    /**
     * Município do prestador: código IBGE do endereço declarado, com o texto do
     * portal como último recurso.
     *
     * A NT manda usar `prest/end/endNac/cMun` e imprimir "Município / UF". O
     * `xLocEmi` do `infNFSe` descreve o local de emissão e é a fonte do campo
     * MUNICÍPIO do cabeçalho, não deste — serve aqui só como fallback.
     */
    private function municipioDoPrestador(?SimpleXMLElement $endPrest, SimpleXMLElement $enderEmit, SimpleXMLElement $inf): string
    {
        $municipio = $this->resolveMunicipio($endPrest?->endNac?->cMun, null); // @pest-mutate-ignore RemoveNullSafeOperator — end/endNac são opcionais no XSD.
        if ($municipio !== '-') {
            return $municipio;
        }

        $municipio = $this->resolveMunicipio($enderEmit->cMun, null);
        if ($municipio !== '-') {
            return $municipio;
        }

        $xLocEmi = $this->str($inf->xLocEmi);
        $uf = $this->str($enderEmit->UF);

        if ($xLocEmi !== '' && $uf !== '') {
            return $xLocEmi.' - '.$uf;
        }

        // Sem xLocEmi não dá para compor "Cidade - UF": devolver '-' em vez de " - RJ".
        return $xLocEmi !== '' ? $xLocEmi : '-';
    }
}
