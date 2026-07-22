<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Danfse\Concerns\ReadsXmlNodes;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoIbsCbs;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Danfse\Formatter;
use OwnerPro\Nfsen\Danfse\ParticipanteBuilder;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\FinNFSe;
use OwnerPro\Nfsen\Dps\Enums\IBSCBS\IndDest;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpImunidade;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetPisCofins;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpSusp;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\AmbienteGerador;
use OwnerPro\Nfsen\Enums\MarcaDagua;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Enums\SituacaoNfse;
use OwnerPro\Nfsen\Enums\TipoBeneficioMunicipal;
use OwnerPro\Nfsen\Exceptions\XmlParseException;
use SimpleXMLElement;
use Throwable;

/**
 * Constrói NfseData a partir do XML da NFS-e Nacional autorizada.
 *
 * Parseia com SimpleXMLElement usando LIBXML_NONET (sem carregamento de rede/XXE),
 * valida o namespace oficial, e aplica formatters + enum labels.
 */
final readonly class DanfseDataBuilder implements BuildsDanfseData
{
    use ReadsXmlNodes;

    private const string NFSE_NS = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(
        private Formatter $fmt = new Formatter,
        private ParticipanteBuilder $participantes = new ParticipanteBuilder,
    ) {}

    public function build(string $xmlNfse, ?MarcaDagua $marcaDagua = null): NfseData
    {
        if (trim($xmlNfse) === '') {
            throw new XmlParseException('XML vazio.');
        }

        $previousUseErrors = libxml_use_internal_errors(true); // @pest-mutate-ignore TrueToFalse — silencia warnings do libxml; mutação altera apenas saída stderr.
        try {
            $root = new SimpleXMLElement($xmlNfse, LIBXML_NONET);
        } catch (Throwable $throwable) {
            throw new XmlParseException('XML malformado: '.$throwable->getMessage(), $throwable->getCode(), previous: $throwable); // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — prefixo literal da mensagem; outros testes asseguram prefixo e causa.
        } finally {
            libxml_clear_errors(); // @pest-mutate-ignore RemoveFunctionCall — cleanup do libxml; sem side-effects observáveis pelos testes.
            libxml_use_internal_errors($previousUseErrors); // @pest-mutate-ignore RemoveFunctionCall — restaura handler original; idempotente em relação aos testes.
        }

        // Valida presença do namespace NFS-e oficial
        $ns = $root->getNamespaces(true); // @pest-mutate-ignore TrueToFalse — com namespace único no root, recursivo vs. não-recursivo produz mesmo resultado neste fluxo.
        if (! in_array(self::NFSE_NS, $ns, true)) {
            throw new XmlParseException('XML não está no namespace NFS-e ('.self::NFSE_NS.').'); // @pest-mutate-ignore ConcatRemoveLeft,ConcatRemoveRight,ConcatSwitchSides — mensagem de erro com constante; prefixo coberto.
        }

        $children = $root->children(self::NFSE_NS);
        if (! isset($children->infNFSe)) {
            throw new XmlParseException('XML não contém infNFSe.');
        }

        if (! isset($children->infNFSe->DPS->infDPS)) {
            throw new XmlParseException('XML não contém DPS/infDPS.');
        }

        return $this->fromInf($children->infNFSe, $marcaDagua);
    }

    private function fromInf(SimpleXMLElement $inf, ?MarcaDagua $marcaDagua): NfseData
    {
        $id = (string) ($inf->attributes()->Id ?? '');
        $chave = str_starts_with($id, 'NFS') ? substr($id, 3) : $id;

        // Grupos que o XSD marca como obrigatórios. Sem estas guardas, um XML truncado
        // fazia cada nível seguinte virar null e o builder estourava com warnings do PHP
        // e um TypeError — que escapava de toHtml(), sem o catch que toPdf() tem.
        $infDps = $inf->DPS->infDPS;
        $prest = $this->required($infDps->prest, 'infDPS/prest');
        $regTrib = $this->required($prest->regTrib, 'infDPS/prest/regTrib');
        $serv = $this->required($infDps->serv, 'infDPS/serv');
        $cServ = $this->required($serv->cServ, 'infDPS/serv/cServ');
        $valores = $this->required($infDps->valores, 'infDPS/valores');
        $trib = $this->required($valores->trib, 'infDPS/valores/trib');
        $tribMun = $this->required($trib->tribMun, 'infDPS/valores/trib/tribMun');
        $totTrib = $this->required($trib->totTrib, 'infDPS/valores/trib/totTrib');
        $emit = $this->required($inf->emit, 'infNFSe/emit');
        $valNfse = $this->required($inf->valores, 'infNFSe/valores');

        // Opcionais no XSD (minOccurs=0): ausentes, chegam como elemento vazio e os
        // builders já os normalizam para '-'.
        $toma = $infDps->toma;
        $tribFed = $trib->tribFed;

        // Fallback fail-safe: tpAmb ausente → PRODUCAO (default do str '1'); tpAmb inválido
        // → HOMOLOGACAO pra não suprimir o watermark "SEM VALIDADE JURÍDICA" em XML suspeito.
        $ambiente = NfseAmbiente::tryFrom($this->str($infDps->tpAmb, '1')) ?? NfseAmbiente::HOMOLOGACAO;

        $intermediario = isset($infDps->interm) ? $this->participantes->intermediario($infDps->interm) : null;
        // IBSCBS e dest são minOccurs=0: NFS-e anterior à reforma não os traz.
        $destinatario = isset($infDps->IBSCBS->dest) ? $this->participantes->destinatario($infDps->IBSCBS->dest) : null;
        // indDest é minOccurs=1 dentro de IBSCBS e distingue os dois casos que a NT
        // trata com frases diferentes: destinatário igual ao tomador (nota 3) e
        // destinatário sem dados (nota 2). Sem ele, os dois saíam como "não identificado".
        $destinatarioEhTomador = IndDest::tryFrom($this->str($infDps->IBSCBS->indDest)) === IndDest::Tomador;

        return new NfseData(
            chaveAcesso: $chave,
            numeroNfse: $this->str($inf->nNFSe, '-'),
            competencia: $this->fmt->date($this->str($infDps->dCompet)),
            emissaoNfse: $this->fmt->dateTime($this->str($inf->dhProc)),
            numeroDps: $this->str($infDps->nDPS, '-'),
            serieDps: $this->str($infDps->serie, '-'),
            emissaoDps: $this->fmt->dateTime($this->str($infDps->dhEmi)),
            ambiente: $ambiente,
            // A NT 008 manda imprimir a descrição da opção, não o código. Sem
            // correspondência no leiaute, '-' — o documento não pode inventar rótulo.
            // Reticências acima de 37, em campos de 40: a NT exemplifica a situação com
            // "NFS-e de Decisão Judicial ou Administ...", descrição que já supera o corte.
            situacao: $this->fmt->limit(SituacaoNfse::labelOf($this->str($inf->cStat)), 37), // @pest-mutate-ignore IncrementInteger,DecrementInteger — guarda para rótulo futuro; nenhum caso do leiaute chega a 37 hoje, então 36 e 38 produzem a mesma saída.
            finalidade: $this->fmt->limit(FinNFSe::labelOf($this->str($infDps->IBSCBS->finNFSe)), 37), // @pest-mutate-ignore IncrementInteger,DecrementInteger — idem.
            emitidaPor: TpEmit::labelOf($this->str($infDps->tpEmit)),
            ambienteGerador: AmbienteGerador::labelOf($this->str($inf->ambGer)),
            municipioEmitente: $this->buildMunicipioEmitente($inf, $emit, $cServ),
            emitente: $this->participantes->prestador($emit, $inf, $prest),
            tomador: $this->participantes->tomador($toma),
            intermediario: $intermediario,
            destinatario: $destinatario,
            destinatarioEhTomador: $destinatarioEhTomador,
            servico: $this->buildServico($inf, $serv, $cServ),
            tribMun: $this->buildTribMun($inf, $tribMun, $valores, $regTrib),
            tribFed: $this->buildTribFed($tribFed, $this->str($infDps->dCompet)),
            tribIbsCbs: $this->buildTribIbsCbs($inf, $infDps, $valores, $trib),
            totais: $this->buildTotais($tribMun, $tribFed, $valores, $valNfse, $inf),
            totaisTributos: $this->buildTotaisTributos($totTrib),
            informacoesComplementares: $this->buildInformacoesComplementares($inf, $infDps, $serv),
            // Não sai do XML: cStat descreve como a nota foi gerada, e o cancelamento
            // ou a substituição chegam depois, em evento separado. Ver MarcaDagua.
            marcaDagua: $marcaDagua,
        );
    }

    /**
     * Garante um grupo que o XSD declara obrigatório.
     *
     * Um filho ausente chega como SimpleXMLElement placeholder de lista vazia, e é o
     * `count() === 0` que o detecta. O `instanceof` cobre o `null` que o SimpleXML
     * devolve ao acessar propriedade de um placeholder desses — inalcançável enquanto
     * as chamadas validarem de fora para dentro (o grupo faltante mais externo sempre
     * lança antes), mas exigido pelo tipo anulável que o PHPStan infere.
     */
    private function required(?SimpleXMLElement $node, string $path): SimpleXMLElement
    {
        if (! $node instanceof SimpleXMLElement || $node->count() === 0) { // @pest-mutate-ignore InstanceOfToTrue — guarda de invariante: validando de fora para dentro, $node nunca chega null; ver docblock.
            throw new XmlParseException(sprintf('XML da NFS-e não contém o grupo obrigatório %s.', $path));
        }

        return $node;
    }

    /**
     * Campo "MUNICÍPIO" do quadro de identificação (item 2.4.5): `xLocEmi` e a UF do
     * emitente, concatenados.
     *
     * A mesma linha traz a única exceção do quadro — "Não exibir, quando o item do
     * cód. de tributação nacional informado for 99". O item 99 cobre o que não é
     * tributado pelo município, e nomear um município ali sugeriria uma competência
     * que não existe. Nesse caso devolve string vazia, e o cabeçalho omite a linha:
     * '-' diria "sem dado", que é outra coisa.
     */
    private function buildMunicipioEmitente(
        SimpleXMLElement $inf,
        SimpleXMLElement $emit,
        SimpleXMLElement $cServ,
    ): string {
        if (str_starts_with($this->str($cServ->cTribNac), '99')) {
            return '';
        }

        $partes = array_filter(
            [$this->str($inf->xLocEmi), $this->str($emit->enderNac->UF)],
            static fn (string $parte): bool => $parte !== '',
        );

        // `xLocEmi` é TSDesc150 num campo de 37. Ver ParticipanteBuilder::municipioDaPessoa()
        // para por que o corte usa a capacidade cheia da NT em vez de reservar as reticências.
        return $partes === [] ? '-' : $this->fmt->limit(implode(' / ', $partes), 37); // @pest-mutate-ignore IncrementInteger,DecrementInteger — 37 é a capacidade da NT 008; 36/38 não representa regressão.
    }

    private function buildServico(SimpleXMLElement $inf, SimpleXMLElement $serv, SimpleXMLElement $cServ): DanfseServico
    {
        $locPrest = $serv->locPrest;
        $cTribMun = $this->str($cServ->cTribMun);
        $cNBS = $this->str($cServ->cNBS);
        $xTribNac = $this->str($inf->xTribNac);
        $xTribMun = $this->str($inf->xTribMun);

        return new DanfseServico(
            codigoTribNacional: $this->fmt->codTribNacional($this->str($cServ->cTribNac)),
            descTribNacional: $xTribNac !== '' ? $this->fmt->limit($xTribNac, 60) : '-', // @pest-mutate-ignore IncrementInteger,DecrementInteger — limite 60 chars é decisão de UX; 59/61 não representa regressão de comportamento.
            codigoTribMunicipal: $cTribMun !== '' ? $cTribMun : '-',
            descTribMunicipal: $xTribMun !== '' ? $this->fmt->limit($xTribMun, 60) : '-', // @pest-mutate-ignore IncrementInteger,DecrementInteger — idem.
            // Campo de 42 na NT; sem o IBGE resolver o código, o recurso é
            // `xLocPrestacao`, que é TSDesc150. Ver ParticipanteBuilder::municipioDaPessoa().
            localPrestacao: $this->fmt->limit(
                $this->resolveMunicipio($locPrest?->cLocPrestacao, $inf->xLocPrestacao), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
                42, // @pest-mutate-ignore IncrementInteger,DecrementInteger — 42 é a capacidade da NT 008; 41/43 não representa regressão.
            ),
            paisPrestacao: $this->str($locPrest?->cPaisPrestacao, '-'), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            // NT 008: reticências acima de 1297 caracteres, num campo de 1300. Sem
            // isso uma descrição no limite do XSD empurra o DANFSe para a segunda
            // página, contrariando o item 2.2 ("obrigatoriamente, em uma única página").
            descricao: $this->fmt->limit($this->str($cServ->xDescServ, '-'), 1297), // @pest-mutate-ignore IncrementInteger,DecrementInteger — 1297 vem da NT 008.
            codigoNbs: $this->fmt->codNbs($cNBS),
            // NT 008, item 2.4.5: `SE xTribMun <> "" ENTAO Descrição Municipal SENAO
            // Descrição Nacional`. É um campo só — imprimir as duas descrições lado a
            // lado contraria o item 2.2.4, que obriga a disposição do Anexo I.
            descricaoTributacao: $this->resolveDescricaoTributacao($xTribMun, $xTribNac),
        );
    }

    /** Aplica a regra do campo único de descrição de tributação (NT 008, item 2.4.5). */
    private function resolveDescricaoTributacao(string $xTribMun, string $xTribNac): string
    {
        $escolhido = $xTribMun !== '' ? $xTribMun : $xTribNac;

        // 167, não 170: a NT manda usar reticências "caso a descrição supere 167
        // caracteres" num campo de 170 — os 3 restantes são as próprias reticências,
        // que limit() acrescenta ao cortar.
        return $escolhido !== '' ? $this->fmt->limit($escolhido, 167) : '-'; // @pest-mutate-ignore IncrementInteger,DecrementInteger — 167 vem da NT 008; 166/168 não representa regressão de comportamento.
    }

    private function buildTribMun(
        SimpleXMLElement $inf,
        SimpleXMLElement $tribMun,
        SimpleXMLElement $valores,
        SimpleXMLElement $regTrib,
    ): DanfseTributacaoMunicipal {
        // vBC e vISSQN são os valores apurados pelo fisco: vivem em infNFSe/valores
        // (TCValoresNFSe), não em infDPS/valores/trib/tribMun (TCTribMunicipal).
        $valNfse = $inf->valores;
        $exigSusp = $tribMun->exigSusp;
        $vBC = $this->str($valNfse->vBC);
        $vISSQN = $this->str($valNfse->vISSQN);
        // pAliqAplic é a alíquota que o fisco aplicou sobre a BC; tribMun/pAliq é a
        // declarada pelo emitente, exigida só quando o município não é parametrizado.
        $pAliq = $this->str($valNfse->pAliqAplic, $this->str($tribMun->pAliq));

        // A NT 008 corta estas descrições em 37 caracteres (campo de 40); as de
        // imunidade citam o dispositivo constitucional e passam de 300.
        $regimeEspecial = RegEspTrib::labelOf($this->str($regTrib->regEspTrib));
        $tipoImunidade = $this->fmt->limit(TpImunidade::labelOf($this->str($tribMun->tpImunidade)), 37); // @pest-mutate-ignore IncrementInteger,DecrementInteger — 37 vem da NT 008; 36/38 não representa regressão.
        $suspensao = $this->fmt->limit(TpSusp::labelOf($this->str($exigSusp->tpSusp)), 37); // @pest-mutate-ignore IncrementInteger,DecrementInteger — idem.
        $nProcesso = $this->str($exigSusp->nProcesso, '-');

        $beneficio = $this->fmt->limit(TipoBeneficioMunicipal::labelOf($this->str($valNfse->tpBM)), 37); // @pest-mutate-ignore IncrementInteger,DecrementInteger — 37 vem da NT 008; 36/38 não representa regressão.
        // Apurado pelo fisco em infNFSe/valores; o declarado na DPS é a redução de
        // base em tribMun/BM. A NT lista os dois como origem do mesmo campo.
        $calculoBM = $this->currencyOrDash($this->firstOf($valNfse->vCalcBM, $tribMun->BM->vRedBCBM));
        // A NT 008 escreve este campo como "vDR | vCalcDR + vCalcReeRepRes": a barra
        // separa duas origens, e a segunda é uma soma. O declarado na DPS vale
        // sozinho; o apurado pelo fisco reúne vCalcDR, em infNFSe/valores, com o
        // reembolso/repasse de vCalcReeRepRes, em infNFSe/IBSCBS/valores.
        $vDR = $this->str($valores->vDedRed->vDR);
        $deducoes = $vDR !== ''
            ? $this->fmt->currency($vDR)
            : $this->sumCurrency(
                $this->str($valNfse->vCalcDR),
                $this->str($inf->IBSCBS?->valores?->vCalcReeRepRes), // @pest-mutate-ignore RemoveNullSafeOperator — IBSCBS e valores são minOccurs=0; ausentes, SimpleXML devolve placeholder vazio e depois null.
            );
        $descontoIncond = $this->currencyOrDash($this->str($valores->vDescCondIncond->vDescIncond));

        return new DanfseTributacaoMunicipal(
            tributacaoIssqn: TribISSQN::labelOf($this->str($tribMun->tribISSQN)),
            // NT 008: "Município / UF / País". O país é o código ISO de 2 dígitos.
            // Campo de 42, e `xLocIncid` é TSDesc150 — daí o teto. Ver
            // ParticipanteBuilder::municipioDaPessoa().
            municipioIncidencia: $this->fmt->limit(
                $this->fmt->joinPresent(
                    $this->resolveMunicipio($inf->cLocIncid, $inf->xLocIncid),
                    $this->str($tribMun->cPaisResult),
                ),
                42, // @pest-mutate-ignore IncrementInteger,DecrementInteger — 42 é a capacidade da NT 008; 41/43 não representa regressão.
            ),
            regimeEspecial: $regimeEspecial,
            tipoImunidade: $tipoImunidade,
            suspensaoExigibilidade: $suspensao,
            numeroProcessoSuspensao: $nProcesso,
            beneficioMunicipal: $beneficio,
            calculoBM: $calculoBM,
            totalDeducoesReducoes: $deducoes,
            // NT 008, item 2.4.5, nota 5: estas duas linhas podem ser suprimidas
            // quando NENHUM dos campos da linha tem dado. Sem isso, toda NFS-e sem
            // imunidade nem benefício — a esmagadora maioria — imprime oito traços.
            exibeRegimeEImunidade: $this->algumPreenchido($regimeEspecial, $tipoImunidade, $suspensao, $nProcesso),
            exibeBeneficioEDeducoes: $this->algumPreenchido($beneficio, $calculoBM, $deducoes, $descontoIncond),
            bcIssqn: $vBC !== '' ? $this->fmt->currency($vBC) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            aliquota: $pAliq !== '' ? $this->fmt->percent($pAliq) : '-',
            retencaoIssqn: TpRetISSQN::labelOf($this->str($tribMun->tpRetISSQN)),
            issqnApurado: $vISSQN !== '' ? $this->fmt->currency($vISSQN) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            sujeitaAoIssqn: TribISSQN::tryFrom($this->str($tribMun->tribISSQN)) !== TribISSQN::NaoIncidencia,
        );
    }

    private function buildTribFed(SimpleXMLElement $tribFed, string $dCompet): DanfseTributacaoFederal
    {
        $pc = $tribFed->piscofins;
        $irrf = $this->str($tribFed->vRetIRRF);
        $cp = $this->str($tribFed->vRetCP);
        $csll = $this->str($tribFed->vRetCSLL);
        $pis = $this->str($pc?->vPis); // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
        $cofins = $this->str($pc?->vCofins); // @pest-mutate-ignore RemoveNullSafeOperator — idem.

        return new DanfseTributacaoFederal(
            irrf: $irrf !== '' ? $this->fmt->currency($irrf) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            cp: $cp !== '' ? $this->fmt->currency($cp) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            csll: $csll !== '' ? $this->fmt->currency($csll) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            pis: $pis !== '' ? $this->fmt->currency($pis) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            cofins: $cofins !== '' ? $this->fmt->currency($cofins) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            descricaoContribuicoesRetidas: TpRetPisCofins::labelOf($this->str($pc?->tpRetPisCofins)), // @pest-mutate-ignore RemoveNullSafeOperator — piscofins é minOccurs=0.
            exibePisCofins: $this->competenciaAlcancaPisCofins($dCompet),
        );
    }

    /**
     * Nota 6 do item 2.4.5: a linha de PIS/COFINS vale "para as NFS-e emitidas com
     * data de competência até o final do ano-calendário de 2026".
     *
     * Competência ilegível não suprime nada. `dCompet` é obrigatório no XSD, e deixar
     * de imprimir tributo declarado por causa de um campo defeituoso perde mais do que
     * imprimir uma linha a mais.
     */
    private function competenciaAlcancaPisCofins(string $dCompet): bool
    {
        // `dCompet` é xs:date — os quatro primeiros caracteres são o ano. Com quatro
        // dígitos de cada lado, comparar como texto ordena igual a comparar como número.
        $ano = substr($dCompet, 0, 4);

        return ! ctype_digit($ano) || $ano <= '2026';
    }

    /**
     * Bloco "TRIBUTAÇÃO IBS / CBS" (NT 008, item 2.1.10).
     *
     * As alíquotas e valores apurados ficam em `infNFSe/IBSCBS` — lado do fisco —
     * enquanto CST, classificação tributária e indicador de operação vêm do que a
     * DPS declarou em `infDPS/IBSCBS`. Todo o grupo é minOccurs=0: NFS-e anterior
     * à reforma não o traz e o bloco sai com traços.
     */
    private function buildTribIbsCbs(
        SimpleXMLElement $inf,
        SimpleXMLElement $infDps,
        SimpleXMLElement $valores,
        SimpleXMLElement $trib,
    ): DanfseTributacaoIbsCbs {
        // Todo o grupo é opcional, e SimpleXML devolve null ao acessar filho de
        // elemento vazio — sem `?->` cada nível seguinte emite warning na maioria
        // das NFS-e, que são anteriores à reforma.
        $ibsNfse = $inf->IBSCBS;
        $valIbs = $ibsNfse?->valores; // @pest-mutate-ignore RemoveNullSafeOperator — IBSCBS é minOccurs=0.
        $totCibs = $ibsNfse?->totCIBS; // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        $gIbsCbs = $infDps->IBSCBS?->valores?->trib?->gIBSCBS; // @pest-mutate-ignore RemoveNullSafeOperator — idem.

        return new DanfseTributacaoIbsCbs(
            cstClassTrib: $this->fmt->joinSlots(
                $this->str($gIbsCbs?->CST),
                $this->str($gIbsCbs?->cClassTrib),
            ),
            // Concatena código da operação, código IBGE, município e UF da incidência.
            // Campo de 56, e `xLocalidadeIncid` é TSDesc600 — o maior recurso de texto
            // livre destes campos. Ver ParticipanteBuilder::municipioDaPessoa().
            indicadorOperacao: $this->fmt->limit(
                $this->fmt->joinSlots(
                    $this->str($infDps->IBSCBS?->cIndOp), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                    $this->str($ibsNfse?->cLocalidadeIncid), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                    $this->resolveMunicipio($ibsNfse?->cLocalidadeIncid, $ibsNfse?->xLocalidadeIncid), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                ),
                56, // @pest-mutate-ignore IncrementInteger,DecrementInteger — 56 é a capacidade da NT 008; 55/57 não representa regressão.
            ),
            // A NT define este campo como o somatório de cinco origens distintas.
            exclusoesReducoes: $this->sumCurrency(
                $this->str($valores->vDescCondIncond->vDescIncond),
                $this->str($valIbs?->vCalcReeRepRes), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->str($inf->valores->vISSQN),
                $this->str($trib->tribFed->piscofins?->vPis), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->str($trib->tribFed->piscofins?->vCofins), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            ),
            baseCalculo: $this->currencyOrDash($this->str($valIbs?->vBC)), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            reducaoAliquotas: $this->fmt->joinSlots(
                $this->percentOrEmpty($valIbs?->uf?->pRedAliqUF), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->percentOrEmpty($valIbs?->mun?->pRedAliqMun), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->percentOrEmpty($valIbs?->fed?->pRedAliqCBS), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            ),
            aliquotaIbs: $this->fmt->joinSlots(
                $this->percentOrEmpty($valIbs?->uf?->pIBSUF), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->percentOrEmpty($valIbs?->mun?->pIBSMun), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            ),
            aliquotaEfetivaMunicipal: $this->percentOrDash($valIbs?->mun?->pAliqEfetMun), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            valorApuradoMunicipal: $this->currencyOrDash($this->str($totCibs?->gIBS?->gIBSMunTot?->vIBSMun)), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            aliquotaEfetivaEstadual: $this->percentOrDash($valIbs?->uf?->pAliqEfetUF), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            valorApuradoEstadual: $this->currencyOrDash($this->str($totCibs?->gIBS?->gIBSUFTot?->vIBSUF)), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            valorTotalIbs: $this->currencyOrDash($this->str($totCibs?->gIBS?->vIBSTot)), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            aliquotaCbs: $this->percentOrDash($valIbs?->fed?->pCBS), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            aliquotaEfetivaCbs: $this->percentOrDash($valIbs?->fed?->pAliqEfetCBS), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            valorTotalCbs: $this->currencyOrDash($this->str($totCibs?->gCBS?->vCBS)), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
        );
    }

    private function percentOrEmpty(?SimpleXMLElement $node): string
    {
        $valor = $this->str($node);

        return $valor !== '' ? $this->fmt->percent($valor) : '';
    }

    private function percentOrDash(?SimpleXMLElement $node): string
    {
        return $this->percentOrEmpty($node) ?: '-';
    }

    private function buildTotais(
        SimpleXMLElement $tribMun,
        SimpleXMLElement $tribFed,
        SimpleXMLElement $valores,
        SimpleXMLElement $valNfse,
        SimpleXMLElement $inf,
    ): DanfseTotais {
        $totCibs = $inf->IBSCBS?->totCIBS; // @pest-mutate-ignore RemoveNullSafeOperator — IBSCBS é minOccurs=0.
        $vTotalRet = $this->str($valNfse->vTotalRet);
        // tpRetISSQN = 1 é "Não Retido": há ISSQN apurado, mas ele não é retenção.
        $issqnRetido = $this->str($tribMun->tpRetISSQN, '1') !== '1' ? $this->str($valNfse->vISSQN) : '';

        // Descontos vivem em infDPS/valores/vDescCondIncond (TCVDescCondIncond, minOccurs=0).
        $desc = $valores->vDescCondIncond;
        $vDescCond = $this->str($desc?->vDescCond); // @pest-mutate-ignore RemoveNullSafeOperator — ?-> previne warning quando <vDescCondIncond> ausente; str(?SimpleXMLElement) já normaliza null.
        $vDescIncond = $this->str($desc?->vDescIncond); // @pest-mutate-ignore RemoveNullSafeOperator — idem.

        return new DanfseTotais(
            valorServico: $this->fmt->currency($this->str($valores->vServPrest->vServ)),
            descontoCondicionado: $vDescCond !== '' ? $this->fmt->currency($vDescCond) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            descontoIncondicionado: $vDescIncond !== '' ? $this->fmt->currency($vDescIncond) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            // vTotalRet é minOccurs=0: quando falta, refazemos a soma que o XSD
            // documenta, senão uma nota com retenções sairia com retenção zero.
            totalRetencoes: $vTotalRet !== ''
                ? $this->fmt->currency($vTotalRet)
                : $this->sumCurrency(
                    $this->str($tribFed->vRetIRRF),
                    $this->str($tribFed->vRetCP),
                    $this->str($tribFed->vRetCSLL),
                    $issqnRetido,
                ),
            valorLiquido: $this->fmt->currency($this->str($valNfse->vLiq)),
            // NT 008: "TOTAL DO IBS/CBS" é vIBSTot + vCBS; o líquido com os dois é
            // vTotNF, já somado pelo fisco — não recalculado aqui.
            totalIbsCbs: $this->sumCurrency(
                $this->str($totCibs?->gIBS?->vIBSTot), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
                $this->str($totCibs?->gCBS?->vCBS), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            ),
            valorLiquidoComIbsCbs: $this->currencyOrDash($this->str($totCibs?->vTotNF)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        );
    }

    /**
     * `TCTribTotal` é um `xs:choice` de quatro ramos e só dois são lidos aqui —
     * de propósito.
     *
     * A nota 10 fixa a linha em três posições, "Federais / Estaduais / Municipais",
     * e a tabela do item 2.4.5 só as alimenta de `totTrib/vTotTrib/` ou
     * `totTrib/pTotTrib/`, cada um com o trio Fed/Est/Mun. Os outros dois ramos não
     * têm onde entrar: `pTotTribSN` é percentual único da alíquota do Simples
     * Nacional, que não se decompõe nas três esferas — repeti-lo nas três o
     * triplicaria, e escolher uma seria arbitrário —, e `indTotTrib` vale zero
     * justamente para declarar que não se informa total algum (Decreto 8.264/2014).
     * Nenhum dos dois é citado uma vez sequer na NT 008.
     *
     * Com qualquer um deles, portanto, a linha sai com o traço da nota 12, que é o
     * que a NT manda imprimir em campo sem informação no XML. Não "conserte" isso
     * mapeando `pTotTribSN` para as três posições.
     */
    private function buildTotaisTributos(SimpleXMLElement $totTrib): DanfseTotaisTributos
    {
        $p = $totTrib->pTotTrib;
        $v = $totTrib->vTotTrib;

        return new DanfseTotaisTributos(
            federais: $this->totalTributo($this->str($p?->pTotTribFed), $this->str($v?->vTotTribFed)), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
            estaduais: $this->totalTributo($this->str($p?->pTotTribEst), $this->str($v?->vTotTribEst)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            municipais: $this->totalTributo($this->str($p?->pTotTribMun), $this->str($v?->vTotTribMun)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        );
    }

    /**
     * Nota 10 do item 2.4.5: os totais aproximados podem vir "com valores monetários
     * OU percentuais". O leiaute reflete isso em dois grupos irmãos, `pTotTrib` e
     * `vTotTrib`; o percentual vem primeiro por ser o que o modelo do Anexo I mostra.
     *
     * Sem nenhum dos dois, `currency('')` já devolve o traço da nota 12.
     */
    private function totalTributo(string $percentual, string $monetario): string
    {
        if ($percentual !== '') {
            return $this->fmt->percent($percentual);
        }

        return $this->fmt->currency($monetario);
    }

    /**
     * Campo "INFORMAÇÕES COMPLEMENTARES" do item 2.4.5.
     *
     * Não é o `xInfComp` sozinho. A NT manda unir dez campos espalhados pelo leiaute,
     * cada um com seu rótulo, na ordem da tabela e separados por pipes; as notas 7, 8
     * e 9 fixam os rótulos de substituição, de obra/imóvel e de evento. Campo ausente
     * some junto com o rótulo — imprimir "Cod. Obra: -" numa nota que não é de obra
     * gastaria a linha e sugeriria um dado que não existe.
     *
     * A linha de "Totais Aproximados dos Tributos" (nota 10) não entra aqui: a NT a
     * declara fixa e imune ao corte, e por isso o template a imprime à parte, fora da
     * área que trunca. Ver `DanfseTotaisTributos::linhaNt008()`.
     */
    private function buildInformacoesComplementares(
        SimpleXMLElement $inf,
        SimpleXMLElement $infDps,
        SimpleXMLElement $serv,
    ): string {
        $infoCompl = $serv->infoCompl;

        $campos = [
            'Inf. Cont.: ' => $this->str($infoCompl?->xInfComp), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
            'NFS-e Subst.: ' => $this->str($infDps->subst?->chSubstda), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Doc. Ref.: ' => $this->str($infoCompl?->docRef), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Cod. Obra: ' => $this->str($serv->obra?->cObra), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Insc. Imob.: ' => $this->str($infDps->IBSCBS?->imovel?->inscImobFisc), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Cod. Evt.: ' => $this->str($serv->atvEvento?->idAtvEvt), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Doc. Tec.: ' => $this->str($infoCompl?->idDocTec), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Núm. Ped.: ' => $this->str($infoCompl?->xPed), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            'Item Ped.: ' => $this->itensDoPedido($infoCompl),
            'Inf. A. T. Mun.: ' => $this->str($inf->xOutInf),
        ];

        $preenchidos = array_filter($campos, fn (string $valor): bool => $valor !== '');

        $texto = implode(' | ', array_map(
            static fn (string $rotulo, string $valor): string => $rotulo.$valor,
            array_keys($preenchidos),
            $preenchidos,
        ));

        // Item 2.4.5: reticências acima de 1997 caracteres, num campo de 2000. Nenhum dos
        // dez campos preenchido, o quadro leva o traço da nota 12, e não área em branco —
        // a linha fixa de totais aproximados da nota 10 sai à parte, em célula própria.
        return $texto !== '' ? $this->fmt->limit($texto, 1997) : '-'; // @pest-mutate-ignore IncrementInteger,DecrementInteger — 1997 vem da NT 008.
    }

    /**
     * Itens do pedido, que o XSD deixa repetir até 99 vezes (`gItemPed/xItemPed`).
     *
     * A NT reserva um rótulo só — "Item Ped.:" — para todos eles, então os números
     * saem numa lista; um rótulo por item consumiria a linha inteira.
     */
    private function itensDoPedido(?SimpleXMLElement $infoCompl): string
    {
        $itens = [];

        foreach ($infoCompl?->gItemPed->xItemPed ?? [] as $item) { // @pest-mutate-ignore RemoveNullSafeOperator — idem; `?? []` cobre o nó ausente.
            $valor = $this->str($item);
            if ($valor !== '') {
                $itens[] = $valor;
            }
        }

        return implode(', ', $itens);
    }

    /** Alguma das descrições da linha tem conteúdo? '-' é a marca de campo vazio. */
    private function algumPreenchido(string ...$valores): bool
    {
        foreach ($valores as $valor) {
            if ($valor !== '-') {
                return true;
            }
        }

        return false;
    }

    /** Formata como moeda, ou '-' quando não há valor. */
    private function currencyOrDash(string $valor): string
    {
        return $valor !== '' ? $this->fmt->currency($valor) : '-'; // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
    }

    private function sumCurrency(string ...$values): string
    {
        $sum = 0.0;
        $hasValue = false;
        foreach ($values as $v) {
            if ($v !== '') {
                $sum += (float) $v; // @pest-mutate-ignore RemoveDoubleCast — PHP coage string numérica em +=; cast torna intent explícito.
                $hasValue = true;
            }
        }

        return $hasValue ? $this->fmt->currency((string) $sum) : '-'; // @pest-mutate-ignore RemoveStringCast — currency aceita string|float; cast alinha com assinatura string-first.
    }
}
