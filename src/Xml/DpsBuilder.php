<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml;

use DOMDocument;
use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\Dps\Prestador\Prestador;
use Pulsar\NfseNacional\DTOs\Dps\Tomador\Tomador;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Valores;
use Pulsar\NfseNacional\Enums\Dps\InfDPS\MotivoEmissaoTI;
use Pulsar\NfseNacional\Support\XsdValidator;
use Pulsar\NfseNacional\Xml\Builders\CreatesTextElements;
use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;
use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;
use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;
use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

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
        $infDps->appendChild($this->text($doc, 'nDPS', (string) $d->nDPS));
        $infDps->appendChild($this->text($doc, 'dCompet', $d->dCompet));
        $infDps->appendChild($this->text($doc, 'tpEmit', $d->tpEmit->value));
        if ($d->cMotivoEmisTI instanceof MotivoEmissaoTI) {
            $infDps->appendChild($this->text($doc, 'cMotivoEmisTI', $d->cMotivoEmisTI->value));
        }

        if ($d->chNFSeRej !== null) {
            $infDps->appendChild($this->text($doc, 'chNFSeRej', $d->chNFSeRej));
        }

        $infDps->appendChild($this->text($doc, 'cLocEmi', $d->cLocEmi));

        if ($data->prest instanceof Prestador) {
            $infDps->appendChild((new PrestadorBuilder)->build($doc, $data->prest));
        }

        // toma (optional)
        if ($data->toma instanceof Tomador) {
            $infDps->appendChild((new TomadorBuilder)->build($doc, $data->toma));
        }

        // serv (obrigatório)
        $infDps->appendChild((new ServicoBuilder)->build($doc, $data->serv));

        // valores (obrigatório quando houver dados)
        if ($data->valores instanceof Valores) {
            $infDps->appendChild((new ValoresBuilder)->build($doc, $data->valores));
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
        $id .= $p instanceof Prestador && $p->CNPJ !== null ? '2' : '1';
        $inscricao = $p instanceof Prestador ? ($p->CNPJ ?? $p->CPF ?? '') : '';
        $id .= str_pad($inscricao, 14, '0', STR_PAD_LEFT);
        $id .= str_pad($d->serie, 5, '0', STR_PAD_LEFT);

        return $id.str_pad((string) $d->nDPS, 15, '0', STR_PAD_LEFT);
    }
}
