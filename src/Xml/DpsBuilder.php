<?php

namespace Pulsar\NfseNacional\Xml;

use DOMDocument;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;
use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;
use Pulsar\NfseNacional\Xml\Builders\ServicoBuilder;
use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

class DpsBuilder
{
    private const VERSION = '1.01';
    private const XMLNS   = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(private readonly string $schemesPath)
    {
    }

    public function buildAndValidate(DpsData $data): string
    {
        $xml = $this->build($data);
        $this->validateXsd($xml);
        return $xml;
    }

    public function build(DpsData $data): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = false;

        $dps = $doc->createElement('DPS');
        $dps->setAttribute('versao', self::VERSION);
        $dps->setAttribute('xmlns', self::XMLNS);

        $infDps = $doc->createElement('infDPS');
        $infDps->setAttribute('Id', $this->generateId($data));

        $d = $data->infDps;
        $infDps->appendChild($doc->createElement('tpAmb',    (string) $d->tpamb));
        $infDps->appendChild($doc->createElement('dhEmi',    $d->dhemi));
        $infDps->appendChild($doc->createElement('verAplic', $d->veraplic));
        $infDps->appendChild($doc->createElement('serie',    $d->serie));
        $infDps->appendChild($doc->createElement('nDPS',     (string) $d->ndps));
        $infDps->appendChild($doc->createElement('dCompet',  $d->dcompet));
        $infDps->appendChild($doc->createElement('tpEmit',   (string) $d->tpemit));
        if (isset($d->cmotivoemisti)) {
            $infDps->appendChild($doc->createElement('cMotivoEmisTI', $d->cmotivoemisti));
        }
        if (isset($d->chnfserej)) {
            $infDps->appendChild($doc->createElement('chNFSeRej', $d->chnfserej));
        }
        $infDps->appendChild($doc->createElement('cLocEmi', $d->clocemi));

        if ((array) $data->prestador !== []) {
            $infDps->appendChild((new PrestadorBuilder())->build($doc, $data->prestador));
        }

        // toma (optional)
        if ((array) $data->tomador !== []) {
            $infDps->appendChild((new TomadorBuilder())->build($doc, $data->tomador));
        }

        // serv (obrigatório)
        $infDps->appendChild((new ServicoBuilder())->build($doc, $data->servico));

        // valores (obrigatório quando houver dados)
        if ((array) $data->valores !== []) {
            $infDps->appendChild((new ValoresBuilder())->build($doc, $data->valores));
        }

        $dps->appendChild($infDps);
        $doc->appendChild($dps);

        return $doc->saveXML($doc->documentElement);
    }

    private function validateXsd(string $xmlFragment): void
    {
        $xsdPath = $this->schemesPath . '/DPS_v1.01.xsd';
        if (!file_exists($xsdPath)) {
            return;
        }
        $xmlWithDecl = '<?xml version="1.0" encoding="UTF-8"?>' . $xmlFragment;
        $doc = new DOMDocument();
        $doc->loadXML($xmlWithDecl);
        libxml_use_internal_errors(true);
        $valid  = $doc->schemaValidate($xsdPath);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        if (!$valid) {
            $messages = array_map(fn (\LibXMLError $e) => trim($e->message), $errors);
            throw new \Pulsar\NfseNacional\Exceptions\NfseException(
                'XML inválido: ' . implode('; ', $messages)
            );
        }
    }

    private function generateId(DpsData $data): string
    {
        $d = $data->infDps;
        $p = $data->prestador;
        $id = 'DPS';
        $id .= substr((string) $d->clocemi, 0, 7);
        $id .= isset($p->cnpj) ? '2' : '1';
        $inscricao = $p->cnpj ?? $p->cpf ?? '';
        $id .= str_pad($inscricao, 14, '0', STR_PAD_LEFT);
        $id .= str_pad((string) $d->serie, 5, '0', STR_PAD_LEFT);
        return $id . str_pad((string) $d->ndps, 15, '0', STR_PAD_LEFT);
    }
}
