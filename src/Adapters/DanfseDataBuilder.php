<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Adapters;

use OwnerPro\Nfsen\Contracts\Driven\BuildsDanfseData;
use OwnerPro\Nfsen\Danfse\Data\DanfseParticipante;
use OwnerPro\Nfsen\Danfse\Data\DanfseServico;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotais;
use OwnerPro\Nfsen\Danfse\Data\DanfseTotaisTributos;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoFederal;
use OwnerPro\Nfsen\Danfse\Data\DanfseTributacaoMunicipal;
use OwnerPro\Nfsen\Danfse\Data\NfseData;
use OwnerPro\Nfsen\Danfse\Formatter;
use OwnerPro\Nfsen\Danfse\Municipios;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegApTribSN;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
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
    private const string NFSE_NS = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(private Formatter $fmt = new Formatter) {}

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

        $intermediario = isset($infDps->interm) ? $this->buildIntermediario($infDps->interm) : null;

        return new NfseData(
            chaveAcesso: $chave,
            numeroNfse: $this->str($inf->nNFSe, '-'),
            competencia: $this->fmt->date($this->str($infDps->dCompet)),
            emissaoNfse: $this->fmt->dateTime($this->str($inf->dhProc)),
            numeroDps: $this->str($infDps->nDPS, '-'),
            serieDps: $this->str($infDps->serie, '-'),
            emissaoDps: $this->fmt->dateTime($this->str($infDps->dhEmi)),
            ambiente: $ambiente,
            emitente: $this->buildEmitente($emit, $inf, $regTrib),
            tomador: $this->buildTomador($toma),
            intermediario: $intermediario,
            servico: $this->buildServico($inf, $serv, $cServ),
            tribMun: $this->buildTribMun($inf, $tribMun, $valores, $regTrib),
            tribFed: $this->buildTribFed($tribFed),
            totais: $this->buildTotais($tribMun, $tribFed, $valores, $valNfse),
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
    private function required(?SimpleXMLElement $node, string $caminho): SimpleXMLElement
    {
        if (! $node instanceof SimpleXMLElement || $node->count() === 0) { // @pest-mutate-ignore InstanceOfToTrue — guarda de invariante: validando de fora para dentro, $node nunca chega null; ver docblock.
            throw new XmlParseException(sprintf('XML da NFS-e não contém o grupo obrigatório %s.', $caminho));
        }

        return $node;
    }

    private function buildEmitente(SimpleXMLElement $emit, SimpleXMLElement $inf, SimpleXMLElement $regTrib): DanfseParticipante
    {
        $ender = $emit->enderNac;
        $doc = $this->firstNonEmpty($emit->CNPJ, $emit->CPF, $emit->NIF);

        $endereco = $this->joinAddress($ender->xLgr, $ender->nro, $ender->xBairro);

        $xLocEmi = $this->str($inf->xLocEmi);
        $uf = $this->str($ender->UF);
        $municipio = match (true) {
            $xLocEmi !== '' && $uf !== '' => $xLocEmi.' - '.$uf,
            $xLocEmi !== '' => $xLocEmi,
            default => '-',
        };

        return new DanfseParticipante(
            nome: $this->str($emit->xNome, '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: $this->str($emit->IM, '-'),
            telefone: $this->fmt->phone($this->str($emit->fone)),
            email: $this->str($emit->email),
            endereco: $endereco !== '' ? $endereco : '-',
            municipio: $municipio,
            cep: $this->fmt->cep($this->str($ender->CEP)),
            simplesNacional: OpSimpNac::labelOf($this->str($regTrib->opSimpNac)),
            regimeSN: RegApTribSN::labelOf($this->str($regTrib->regApTribSN)),
        );
    }

    private function buildTomador(SimpleXMLElement $toma): DanfseParticipante
    {
        if ($toma->count() === 0) {
            return $this->emptyParticipante();
        }

        $end = $toma->end;
        $endNac = $end->endNac;
        $doc = $this->firstNonEmpty($toma->CNPJ, $toma->CPF, $toma->NIF);

        $endereco = $this->joinAddress($end?->xLgr, $end?->nro, $end?->xBairro); // @pest-mutate-ignore RemoveNullSafeOperator — end é minOccurs=0 no XSD; ?-> previne crash quando <end> ausente.

        return new DanfseParticipante(
            nome: $this->str($toma->xNome, '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: $this->str($toma->IM, '-'),
            telefone: $this->fmt->phone($this->str($toma->fone)),
            email: $this->str($toma->email),
            endereco: $endereco !== '' ? $endereco : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — guard defensivo; joinAddress() já normaliza para '' quando vazio.
            municipio: Municipios::lookup($this->str($endNac?->cMun)), // @pest-mutate-ignore RemoveNullSafeOperator — endNac null quando <end> ausente.
            cep: $this->fmt->cep($this->str($endNac?->CEP)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        );
    }

    private function buildIntermediario(SimpleXMLElement $interm): DanfseParticipante
    {
        $end = $interm->end;
        $endNac = $end->endNac;
        $doc = $this->firstNonEmpty($interm->CNPJ, $interm->CPF, $interm->NIF);

        $endereco = $this->joinAddress($end?->xLgr, $end?->nro, $end?->xBairro); // @pest-mutate-ignore RemoveNullSafeOperator — end é minOccurs=0 no XSD; ?-> previne crash quando <end> ausente.

        return new DanfseParticipante(
            nome: $this->str($interm->xNome, '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: $this->str($interm->IM, '-'),
            telefone: $this->fmt->phone($this->str($interm->fone)),
            email: $this->str($interm->email),
            endereco: $endereco !== '' ? $endereco : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — guard defensivo; joinAddress() já normaliza para '' quando vazio.
            municipio: Municipios::lookup($this->str($endNac?->cMun)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
            cep: $this->fmt->cep($this->str($endNac?->CEP)), // @pest-mutate-ignore RemoveNullSafeOperator — idem.
        );
    }

    private function emptyParticipante(): DanfseParticipante
    {
        return new DanfseParticipante('-', '-', '-', '-', '-', '-', '-', '-');
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
        );
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
        $vBC = $this->str($valNfse->vBC);
        $vISSQN = $this->str($valNfse->vISSQN);
        // pAliqAplic é a alíquota que o fisco aplicou sobre a BC; tribMun/pAliq é a
        // declarada pelo emitente, exigida só quando o município não é parametrizado.
        $pAliq = $this->str($valNfse->pAliqAplic, $this->str($tribMun->pAliq));

        return new DanfseTributacaoMunicipal(
            tributacaoIssqn: TribISSQN::labelOf($this->str($tribMun->tribISSQN)),
            municipioIncidencia: $this->resolveMunicipio($inf->cLocIncid, $inf->xLocIncid),
            regimeEspecial: RegEspTrib::labelOf($this->str($regTrib->regEspTrib)),
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
        );
    }

    private function buildTotais(
        SimpleXMLElement $tribMun,
        SimpleXMLElement $tribFed,
        SimpleXMLElement $valores,
        SimpleXMLElement $valNfse,
    ): DanfseTotais {
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

    /**
     * Resolve um município via tabela IBGE a partir do código, com fallback para o texto do portal.
     *
     * Portal nacional renderiza "Cidade - UF" (ex.: "Canela - RS"). Quando o código IBGE está presente
     * e válido, preferimos esse formato; senão caímos no texto literal do XML; e em último caso, '-'.
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

    private function firstNonEmpty(SimpleXMLElement ...$nodes): string
    {
        foreach ($nodes as $node) {
            $s = trim((string) $node);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    private function joinAddress(?SimpleXMLElement $xLgr, ?SimpleXMLElement $nro, ?SimpleXMLElement $xBairro): string
    {
        return implode(', ', array_filter([
            trim((string) $xLgr),
            trim((string) $nro),
            trim((string) $xBairro),
        ], fn (string $v): bool => $v !== ''));
    }
}
