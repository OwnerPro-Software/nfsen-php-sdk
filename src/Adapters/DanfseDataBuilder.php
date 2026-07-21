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

    public function build(string $xmlNfse): NfseData
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

        return $this->fromInf($children->infNFSe);
    }

    private function fromInf(SimpleXMLElement $inf): NfseData
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
            situacao: SituacaoNfse::labelOf($this->str($inf->cStat)),
            finalidade: FinNFSe::labelOf($this->str($infDps->IBSCBS->finNFSe)),
            emitidaPor: TpEmit::labelOf($this->str($infDps->tpEmit)),
            ambienteGerador: AmbienteGerador::labelOf($this->str($inf->ambGer)),
            emitente: $this->participantes->prestador($emit, $inf, $prest),
            tomador: $this->participantes->tomador($toma),
            intermediario: $intermediario,
            destinatario: $destinatario,
            destinatarioEhTomador: $destinatarioEhTomador,
            servico: $this->buildServico($inf, $serv, $cServ),
            tribMun: $this->buildTribMun($inf, $tribMun, $valores, $regTrib),
            tribFed: $this->buildTribFed($tribFed),
            tribIbsCbs: $this->buildTribIbsCbs($inf, $infDps, $valores, $trib),
            totais: $this->buildTotais($tribMun, $tribFed, $valores, $valNfse, $inf),
            totaisTributos: $this->buildTotaisTributos($totTrib),
            informacoesComplementares: $this->str($serv->infoCompl->xInfComp),
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
            localPrestacao: $this->resolveMunicipio($locPrest?->cLocPrestacao, $inf->xLocPrestacao), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
            paisPrestacao: $this->str($locPrest?->cPaisPrestacao, '-'), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            descricao: $this->str($cServ->xDescServ, '-'),
            codigoNbs: $cNBS !== '' ? $cNBS : '-',
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

        $beneficio = TipoBeneficioMunicipal::labelOf($this->str($valNfse->tpBM));
        // Apurado pelo fisco em infNFSe/valores; o declarado na DPS é a redução de
        // base em tribMun/BM. A NT lista os dois como origem do mesmo campo.
        $calculoBM = $this->currencyOrDash($this->firstOf($valNfse->vCalcBM, $tribMun->BM->vRedBCBM));
        $deducoes = $this->currencyOrDash($this->firstOf($valores->vDedRed->vDR, $valNfse->vCalcDR));
        $descontoIncond = $this->currencyOrDash($this->str($valores->vDescCondIncond->vDescIncond));

        return new DanfseTributacaoMunicipal(
            tributacaoIssqn: TribISSQN::labelOf($this->str($tribMun->tribISSQN)),
            municipioIncidencia: $this->resolveMunicipio($inf->cLocIncid, $inf->xLocIncid),
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
            valorServico: $this->fmt->currency($this->str($valores->vServPrest->vServ)),
            bcIssqn: $vBC !== '' ? $this->fmt->currency($vBC) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            aliquota: $pAliq !== '' ? $pAliq.'%' : '-',
            retencaoIssqn: TpRetISSQN::labelOf($this->str($tribMun->tpRetISSQN)),
            issqnApurado: $vISSQN !== '' ? $this->fmt->currency($vISSQN) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
        );
    }

    private function buildTribFed(SimpleXMLElement $tribFed): DanfseTributacaoFederal
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
        );
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
            cstClassTrib: $this->joinWithSlash(
                $this->str($gIbsCbs?->CST),
                $this->str($gIbsCbs?->cClassTrib),
            ),
            // Concatena código da operação, código IBGE, município e UF da incidência.
            indicadorOperacao: $this->joinWithSlash(
                $this->str($infDps->IBSCBS?->cIndOp), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->str($ibsNfse?->cLocalidadeIncid), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->resolveMunicipio($ibsNfse?->cLocalidadeIncid, $ibsNfse?->xLocalidadeIncid), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
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
            reducaoAliquotas: $this->joinWithSlash(
                $this->percentOrEmpty($valIbs?->uf?->pRedAliqUF), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->percentOrEmpty($valIbs?->mun?->pRedAliqMun), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
                $this->percentOrEmpty($valIbs?->fed?->pRedAliqCBS), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement) neste nível; defesa dupla é intencional.
            ),
            aliquotaIbs: $this->joinWithSlash(
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

    /** Junta com " / " os pedaços presentes, ou '-' quando nenhum veio. */
    private function joinWithSlash(string ...$partes): string
    {
        $preenchidos = array_filter($partes, fn (string $p): bool => $p !== '' && $p !== '-');

        return $preenchidos !== [] ? implode(' / ', $preenchidos) : '-';
    }

    private function percentOrEmpty(?SimpleXMLElement $node): string
    {
        $valor = $this->str($node);

        return $valor !== '' ? $valor.'%' : '';
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
        $vISSQN = $this->str($valNfse->vISSQN);
        $tpRet = $this->str($tribMun->tpRetISSQN, '1');
        $issqnRetido = ($vISSQN !== '' && $tpRet !== '1') ? $this->fmt->currency($vISSQN) : '-'; // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.

        $pc = $tribFed->piscofins;
        // Descontos vivem em infDPS/valores/vDescCondIncond (TCVDescCondIncond, minOccurs=0).
        $desc = $valores->vDescCondIncond;
        $vDescCond = $this->str($desc?->vDescCond); // @pest-mutate-ignore RemoveNullSafeOperator — ?-> previne warning quando <vDescCondIncond> ausente; str(?SimpleXMLElement) já normaliza null.
        $vDescIncond = $this->str($desc?->vDescIncond); // @pest-mutate-ignore RemoveNullSafeOperator — idem.

        return new DanfseTotais(
            valorServico: $this->fmt->currency($this->str($valores->vServPrest->vServ)),
            descontoCondicionado: $vDescCond !== '' ? $this->fmt->currency($vDescCond) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            descontoIncondicionado: $vDescIncond !== '' ? $this->fmt->currency($vDescIncond) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            issqnRetido: $issqnRetido,
            // Soma local em vez de valores/vTotalRet: aquele campo é Σ(vRetCP + vRetIRRF
            // + vRetCSLL + ISSQN retido), e a DANFSE exibe o ISSQN retido em linha própria.
            retencoesFederais: $this->sumCurrency(
                $this->str($tribFed->vRetIRRF),
                $this->str($tribFed->vRetCP),
                $this->str($tribFed->vRetCSLL),
            ),
            pisCofins: $this->sumCurrency(
                $this->str($pc?->vPis), // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
                $this->str($pc?->vCofins), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
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

    private function buildTotaisTributos(SimpleXMLElement $totTrib): DanfseTotaisTributos
    {
        $p = $totTrib->pTotTrib;
        $fed = $this->str($p?->pTotTribFed); // @pest-mutate-ignore RemoveNullSafeOperator — ?-> redundante com str(?SimpleXMLElement); defesa dupla é intencional.
        $est = $this->str($p?->pTotTribEst); // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        $mun = $this->str($p?->pTotTribMun); // @pest-mutate-ignore RemoveNullSafeOperator — idem.

        return new DanfseTotaisTributos(
            federais: $fed !== '' ? $fed.'%' : '-',
            estaduais: $est !== '' ? $est.'%' : '-',
            municipais: $mun !== '' ? $mun.'%' : '-',
        );
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
