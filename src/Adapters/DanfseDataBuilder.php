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

        $infDps = $inf->DPS->infDPS;
        $prest = $infDps->prest;
        $regTrib = $prest->regTrib;
        $toma = $infDps->toma;
        $serv = $infDps->serv;
        $cServ = $serv->cServ;
        $valores = $infDps->valores;
        $vServPrest = $valores->vServPrest;
        $trib = $valores->trib;
        $tribMun = $trib->tribMun;
        $tribFed = $trib->tribFed;
        $totTrib = $trib->totTrib;
        $valNfse = $inf->valores;

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
            emitente: $this->buildEmitente($inf->emit, $inf, $regTrib),
            tomador: $this->buildTomador($toma),
            intermediario: $intermediario,
            servico: $this->buildServico($inf, $serv, $cServ),
            tribMun: $this->buildTribMun($inf, $tribMun, $vServPrest, $regTrib),
            tribFed: $this->buildTribFed($tribFed),
            totais: $this->buildTotais($vServPrest, $tribMun, $tribFed, $valNfse),
            totaisTributos: $this->buildTotaisTributos($totTrib),
            informacoesComplementares: $this->str($serv->infoCompl->xInfComp),
        );
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
            im: '-',
            telefone: $this->fmt->phone($this->str($emit->fone)),
            email: strtolower($this->str($emit->email)),
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

        $endereco = $this->joinAddress($end->xLgr, $end->nro, $end->xBairro);

        $im = $this->str($toma->IM);

        return new DanfseParticipante(
            nome: $this->str($toma->xNome, '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: $im !== '' ? $im : '-',
            telefone: $this->fmt->phone($this->str($toma->fone)),
            email: strtolower($this->str($toma->email)),
            endereco: $endereco !== '' ? $endereco : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — guard defensivo; joinAddress() já normaliza para '' quando vazio.
            municipio: Municipios::lookup($this->str($endNac->cMun)),
            cep: $this->fmt->cep($this->str($endNac->CEP)),
        );
    }

    private function buildIntermediario(SimpleXMLElement $interm): DanfseParticipante
    {
        $end = $interm->end;
        $endNac = $end->endNac;
        $doc = $this->firstNonEmpty($interm->CNPJ, $interm->CPF, $interm->NIF);

        $endereco = $this->joinAddress($end->xLgr, $end->nro, $end->xBairro);

        $im = $this->str($interm->IMPrestMun);

        return new DanfseParticipante(
            nome: $this->str($interm->xNome, '-'),
            cnpjCpf: $this->fmt->cnpjCpf($doc),
            im: $im !== '' ? $im : '-',
            telefone: $this->fmt->phone($this->str($interm->fone)),
            email: strtolower($this->str($interm->email)),
            endereco: $endereco !== '' ? $endereco : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — guard defensivo; joinAddress() já normaliza para '' quando vazio.
            municipio: Municipios::lookup($this->str($endNac->cMun)),
            cep: $this->fmt->cep($this->str($endNac->CEP)),
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

        return new DanfseServico(
            codigoTribNacional: $this->fmt->codTribNacional($this->str($cServ->cTribNac)),
            descTribNacional: $this->fmt->limit($this->str($inf->xTribNac), 60),
            codigoTribMunicipal: $cTribMun !== '' ? $cTribMun : '-',
            descTribMunicipal: $this->fmt->limit($this->str($inf->xTribMun), 60),
            localPrestacao: $this->str($inf->xLocPrestacao, '-'),
            paisPrestacao: $this->str($locPrest->cPaisPrestacao, '-'),
            descricao: $this->str($cServ->xDescServ, '-'),
        );
    }

    private function buildTribMun(
        SimpleXMLElement $inf,
        SimpleXMLElement $tribMun,
        SimpleXMLElement $vServPrest,
        SimpleXMLElement $regTrib,
    ): DanfseTributacaoMunicipal {
        $vBC = $this->str($tribMun->vBC);
        $pAliq = $this->str($tribMun->pAliq);
        $vISSQN = $this->str($tribMun->vISSQN);

        return new DanfseTributacaoMunicipal(
            tributacaoIssqn: TribISSQN::labelOf($this->str($tribMun->tribISSQN)),
            municipioIncidencia: $this->str($inf->xLocIncid, '-'),
            regimeEspecial: RegEspTrib::labelOf($this->str($regTrib->regEspTrib)),
            valorServico: $this->fmt->currency($this->str($vServPrest->vServ)),
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
        $pis = $this->str($pc->vPis);
        $cofins = $this->str($pc->vCofins);

        return new DanfseTributacaoFederal(
            irrf: $irrf !== '' ? $this->fmt->currency($irrf) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            cp: $cp !== '' ? $this->fmt->currency($cp) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            csll: $csll !== '' ? $this->fmt->currency($csll) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            pis: $pis !== '' ? $this->fmt->currency($pis) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            cofins: $cofins !== '' ? $this->fmt->currency($cofins) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
        );
    }

    private function buildTotais(
        SimpleXMLElement $vServPrest,
        SimpleXMLElement $tribMun,
        SimpleXMLElement $tribFed,
        SimpleXMLElement $valNfse,
    ): DanfseTotais {
        $vISSQN = $this->str($tribMun->vISSQN);
        $tpRet = $this->str($tribMun->tpRetISSQN, '1');
        $issqnRetido = ($vISSQN !== '' && $tpRet !== '1') ? $this->fmt->currency($vISSQN) : '-'; // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.

        $pc = $tribFed->piscofins;
        $vDescCond = $this->str($tribMun->vDescCond);
        $vDescIncond = $this->str($tribMun->vDescIncond);

        return new DanfseTotais(
            valorServico: $this->fmt->currency($this->str($vServPrest->vServ)),
            descontoCondicionado: $vDescCond !== '' ? $this->fmt->currency($vDescCond) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — currency() já retorna '-' para ''; guard é defensivo.
            descontoIncondicionado: $vDescIncond !== '' ? $this->fmt->currency($vDescIncond) : '-', // @pest-mutate-ignore EmptyStringToNotEmpty — idem.
            issqnRetido: $issqnRetido,
            retencoesFederais: $this->sumCurrency(
                $this->str($tribFed->vRetIRRF),
                $this->str($tribFed->vRetCP),
                $this->str($tribFed->vRetCSLL),
            ),
            pisCofins: $this->sumCurrency(
                $this->str($pc->vPis),
                $this->str($pc->vCofins),
            ),
            valorLiquido: $this->fmt->currency($this->str($valNfse->vLiq)),
        );
    }

    private function buildTotaisTributos(SimpleXMLElement $totTrib): DanfseTotaisTributos
    {
        $p = $totTrib->pTotTrib;
        $fed = $this->str($p->pTotTribFed);
        $est = $this->str($p->pTotTribEst);
        $mun = $this->str($p->pTotTribMun);

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
     * Converte um nó SimpleXMLElement para string, devolvendo default quando vazio.
     */
    private function str(SimpleXMLElement $node, string $default = ''): string
    {
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

    private function joinAddress(SimpleXMLElement $xLgr, SimpleXMLElement $nro, SimpleXMLElement $xBairro): string
    {
        return implode(', ', array_filter([
            trim((string) $xLgr),
            trim((string) $nro),
            trim((string) $xBairro),
        ], fn (string $v): bool => $v !== ''));
    }
}
