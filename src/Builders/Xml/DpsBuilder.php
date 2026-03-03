<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Builders\Xml;

use DOMDocument;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Dps\DTO\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\SubstituicaoData;
use Pulsar\NfseNacional\Dps\DTO\Tomador\Tomador;
use Pulsar\NfseNacional\Dps\Enums\InfDPS\MotivoEmissaoTI;
use Pulsar\NfseNacional\Support\XsdValidator;
use Pulsar\NfseNacional\Builders\Xml\Parts\CreatesTextElements;
use Pulsar\NfseNacional\Builders\Xml\Parts\IBSCBSBuilder;
use Pulsar\NfseNacional\Builders\Xml\Parts\PrestadorBuilder;
use Pulsar\NfseNacional\Builders\Xml\Parts\ServicoBuilder;
use Pulsar\NfseNacional\Builders\Xml\Parts\TomadorBuilder;
use Pulsar\NfseNacional\Builders\Xml\Parts\ValoresBuilder;

final readonly class DpsBuilder
{
    use CreatesTextElements;

    private const VERSION = '1.01';

    private const XMLNS = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(
        private XsdValidator $xsdValidator,
    ) {}

    public function buildAndValidate(DpsData $data): string
    {
        $xml = $this->build($data);
        $this->xsdValidator->validate($xml, 'DPS_v1.01.xsd');

        return $xml;
    }

    public function build(DpsData $data): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $dps = $doc->createElement('DPS');
        $dps->setAttribute('versao', self::VERSION);
        $dps->setAttribute('xmlns', self::XMLNS);

        $infDps = $doc->createElement('infDPS');
        $infDps->setAttribute('Id', $this->generateId($data));

        $d = $data->infDPS;
        $infDps->appendChild($this->text($doc, 'tpAmb', $d->tpAmb->value));
        $infDps->appendChild($this->text($doc, 'dhEmi', $d->dhEmi));
        $infDps->appendChild($this->text($doc, 'verAplic', $d->verAplic));
        $infDps->appendChild($this->text($doc, 'serie', $d->serie));
        $infDps->appendChild($this->text($doc, 'nDPS', $d->nDPS));
        $infDps->appendChild($this->text($doc, 'dCompet', $d->dCompet));
        $infDps->appendChild($this->text($doc, 'tpEmit', $d->tpEmit->value));
        if ($d->cMotivoEmisTI instanceof MotivoEmissaoTI) {
            $infDps->appendChild($this->text($doc, 'cMotivoEmisTI', $d->cMotivoEmisTI->value));
        }

        if ($d->chNFSeRej !== null) {
            $infDps->appendChild($this->text($doc, 'chNFSeRej', $d->chNFSeRej));
        }

        $infDps->appendChild($this->text($doc, 'cLocEmi', $d->cLocEmi));

        // subst (optional)
        if ($data->subst instanceof SubstituicaoData) {
            $subst = $doc->createElement('subst');
            $subst->appendChild($this->text($doc, 'chSubstda', $data->subst->chSubstda));
            $subst->appendChild($this->text($doc, 'cMotivo', $data->subst->cMotivo->value));
            if ($data->subst->xMotivo !== null) {
                $subst->appendChild($this->text($doc, 'xMotivo', $data->subst->xMotivo));
            }

            $infDps->appendChild($subst);
        }

        $infDps->appendChild((new PrestadorBuilder)->build($doc, $data->prest));

        // toma (optional)
        if ($data->toma instanceof Tomador) {
            $infDps->appendChild((new TomadorBuilder)->build($doc, $data->toma));
        }

        // interm (optional)
        if ($data->interm instanceof Tomador) {
            $infDps->appendChild((new TomadorBuilder)->build($doc, $data->interm, 'interm'));
        }

        // serv (obrigatório)
        $infDps->appendChild((new ServicoBuilder)->build($doc, $data->serv));

        // valores (obrigatório)
        $infDps->appendChild((new ValoresBuilder)->build($doc, $data->valores));

        // IBSCBS (optional)
        if ($data->IBSCBS instanceof InfoIBSCBS) {
            $infDps->appendChild((new IBSCBSBuilder)->build($doc, $data->IBSCBS));
        }

        $dps->appendChild($infDps);
        $doc->appendChild($dps);

        return (string) $doc->saveXML($doc->documentElement);
    }

    private function generateId(DpsData $data): string
    {
        $d = $data->infDPS;
        $p = $data->prest;
        $id = 'DPS';
        $id .= substr($d->cLocEmi, 0, 7);
        $id .= $p->CNPJ !== null ? '2' : '1';
        $inscricao = $p->CNPJ ?? $p->CPF ?? '';
        $id .= str_pad($inscricao, 14, '0', STR_PAD_LEFT);
        $id .= str_pad($d->serie, 5, '0', STR_PAD_LEFT);

        return $id.str_pad($d->nDPS, 15, '0', STR_PAD_LEFT);
    }
}
