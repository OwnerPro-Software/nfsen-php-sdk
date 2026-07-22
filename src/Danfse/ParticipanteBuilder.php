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
            nome: $this->limitaNome($this->firstOf($prest->xNome, $emit->xNome)),
            cnpjCpf: $identificacao,
            im: $this->firstOf($prest->IM, $emit->IM) ?: '-',
            telefone: $this->fmt->phone($this->firstOf($prest->fone, $emit->fone)),
            email: $this->firstOf($prest->email, $emit->email) ?: '-',
            endereco: $this->limitaEndereco($endereco),
            municipio: $this->municipioDoPrestador($endPrest, $enderEmit, $inf),
            // O endereço declarado na DPS vem antes do cadastro do fisco porque os dois
            // descrevem o mesmo prestador: `emit/enderNac` é obrigatório em TCEmitente e
            // traria um CEP brasileiro para quem a DPS declarou fora do país.
            cep: $this->codigoPostal($endPrest?->endNac, $endPrest?->endExt, $enderEmit->CEP), // @pest-mutate-ignore RemoveNullSafeOperator — end/endNac/endExt são opcionais no XSD.
            codigoIbge: $this->codigoIbgeDe($endPrest?->endNac, $endPrest?->endExt, $enderEmit->cMun), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            // A NT 008 corta estas descrições nos 37 e 77 caracteres da tabela do item
            // 2.4.5; as do leiaute chegam a 59 e 136.
            simplesNacional: $this->fmt->limit(OpSimpNac::labelOf($this->str($regTrib->opSimpNac)), 37), // @pest-mutate-ignore IncrementInteger,DecrementInteger — 37 vem da NT 008; 36/38 não representa regressão.
            regimeSN: $this->fmt->limit(RegApTribSN::labelOf($this->str($regTrib->regApTribSN)), 77), // @pest-mutate-ignore IncrementInteger,DecrementInteger — 77 vem da NT 008; 76/78 não representa regressão.
        );
    }

    /**
     * Bloco "TOMADOR / ADQUIRENTE"; `null` quando o XML não traz `toma`.
     *
     * A nota 2 do item 2.4.5 distingue os dois casos: tomador informado rende o bloco
     * de campos, tomador ausente rende só a frase de não identificado. Um participante
     * de traços não serve — diria que os campos existem e vieram vazios.
     */
    public function tomador(SimpleXMLElement $toma): ?DanfseParticipante
    {
        if ($toma->count() === 0) {
            return null;
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
        $endNac = $end?->endNac; // @pest-mutate-ignore RemoveNullSafeOperator — end é null quando o próprio participante falta no XML; sem o ?-> o acesso emite warning.
        $endExt = $end?->endExt; // @pest-mutate-ignore RemoveNullSafeOperator — idem; `endExt` é o outro ramo do <xs:choice> de TCEndereco.

        $identificacao = ($this->identificacao)(
            $this->str($pessoa->CNPJ),
            $this->str($pessoa->CPF),
            $this->str($pessoa->NIF),
            $this->str($pessoa->cNaoNIF),
        );

        $endereco = $this->joinAddress($end?->xLgr, $end?->nro, $end?->xCpl, $end?->xBairro); // @pest-mutate-ignore RemoveNullSafeOperator — end é minOccurs=0 no XSD; ?-> previne crash quando <end> ausente.

        return new DanfseParticipante(
            nome: $this->limitaNome($this->str($pessoa->xNome)),
            cnpjCpf: $identificacao,
            im: $im,
            telefone: $this->fmt->phone($this->str($pessoa->fone)),
            email: $this->str($pessoa->email, '-'),
            endereco: $this->limitaEndereco($endereco),
            municipio: $this->municipioDaPessoa($endNac, $endExt),
            cep: $this->codigoPostal($endNac, $endExt),
            codigoIbge: $this->codigoIbgeDe($endNac, $endExt),
        );
    }

    /**
     * Lado esquerdo do campo "CÓDIGO IBGE / CEP" (item 2.4.5).
     *
     * Participante no exterior não tem código do IBGE, e o cadastro nacional que
     * serve de fallback contradiria o endereço que a DPS declarou fora do país.
     */
    private function codigoIbgeDe(?SimpleXMLElement $endNac, ?SimpleXMLElement $endExt, ?SimpleXMLElement $fallbackNacional = null): string
    {
        if ($this->str($endExt?->xCidade) !== '') { // @pest-mutate-ignore RemoveNullSafeOperator — endExt null quando <end> ausente.
            return '-';
        }

        return $this->firstOf($endNac?->cMun, $fallbackNacional) ?: '-'; // @pest-mutate-ignore RemoveNullSafeOperator — idem.
    }

    /**
     * Campo "MUNICÍPIO / SIGLA UF" (item 2.4.5), que a tabela alimenta por dois
     * caminhos: `end/endNac/cMun`, resolvido pela tabela do IBGE, ou `end/endExt`
     * para quem está fora do país. No exterior não há UF — `xEstProvReg` é o que a
     * identifica, e sem ele a cidade sai sozinha.
     */
    private function municipioDaPessoa(?SimpleXMLElement $endNac, ?SimpleXMLElement $endExt): string
    {
        $nacional = Municipios::lookup($this->str($endNac?->cMun)); // @pest-mutate-ignore RemoveNullSafeOperator — endNac null quando <end> ausente.
        if ($nacional !== '-') {
            return $nacional;
        }

        $partes = array_filter([
            $this->str($endExt?->xCidade), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            $this->str($endExt?->xEstProvReg), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        ], static fn (string $parte): bool => $parte !== '');

        return $partes === [] ? '-' : implode(' / ', $partes);
    }

    /**
     * Lado direito do campo "CÓDIGO IBGE / CEP" (item 2.4.5): `endNac/CEP` ou, para
     * quem está fora do país, `endExt/cEndPost`.
     *
     * O código postal do exterior sai sem máscara: `TSCodigoEndPostal` é alfanumérico
     * e o formato de CEP brasileiro descartaria letras.
     *
     * O "(ext)" do exemplo da tabela — "Ex.: nnnnnnn / nn.nnn-nnn ou nnnnnnn / nnnnnnnnnnn
     * (ext)" — é anotação da própria tabela, não literal a imprimir: a linha declara 21
     * como tamanho do campo, e `nnnnnnn / nnnnnnnnnnn` já ocupa exatamente 21. Imprimir
     * o sufixo levaria o campo a 27 e estouraria a largura que a mesma linha fixa.
     */
    private function codigoPostal(?SimpleXMLElement $endNac, ?SimpleXMLElement $endExt, ?SimpleXMLElement $fallbackNacional = null): string
    {
        $cep = $this->str($endNac?->CEP); // @pest-mutate-ignore RemoveNullSafeOperator — endNac null quando <end> ausente.
        if ($cep !== '') {
            return $this->fmt->cep($cep);
        }

        $exterior = $this->str($endExt?->cEndPost); // @pest-mutate-ignore RemoveNullSafeOperator — idem.

        return $exterior !== '' ? $exterior : $this->fmt->cep($this->str($fallbackNacional));
    }

    /**
     * Nome e endereço saem de campos de 80 caracteres que a tabela do item 2.4.5 manda
     * cortar com reticências acima de 77; o leiaute admite 255 em ambos, e um deles
     * inteiro empurraria o DANFSe para a segunda página (item 2.2).
     */
    private function limitaNome(string $nome): string
    {
        return $nome !== '' ? $this->fmt->limit($nome, 77) : '-'; // @pest-mutate-ignore IncrementInteger,DecrementInteger — 77 vem da NT 008; 76/78 não representa regressão.
    }

    private function limitaEndereco(string $endereco): string
    {
        return $endereco !== '' ? $this->fmt->limit($endereco, 77) : '-'; // @pest-mutate-ignore IncrementInteger,DecrementInteger — idem.
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
        $municipio = $this->municipioDaPessoa($endPrest?->endNac, $endPrest?->endExt); // @pest-mutate-ignore RemoveNullSafeOperator — end/endNac/endExt são opcionais no XSD.
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
            return $xLocEmi.' / '.$uf;
        }

        // Sem xLocEmi não dá para compor "Município / UF": devolver '-' em vez de " / RJ".
        return $xLocEmi !== '' ? $xLocEmi : '-';
    }
}
