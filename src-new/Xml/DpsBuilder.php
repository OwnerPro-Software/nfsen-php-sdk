<?php

namespace Pulsar\NfseNacional\Xml;

use DOMDocument;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\Builders\PrestadorBuilder;

class DpsBuilder
{
    private const VERSION = '1.01';
    private const XMLNS   = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(private readonly string $schemesPath)
    {
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

        if (!empty((array) $data->prestador)) {
            $infDps->appendChild((new PrestadorBuilder())->build($doc, $data->prestador));
        }

        $dps->appendChild($infDps);
        $doc->appendChild($dps);

        return $doc->saveXML($doc->documentElement);
    }

    private function generateId(DpsData $data): string
    {
        $d = $data->infDps;
        $p = $data->prestador;
        $id = 'DPS';
        $id .= substr($d->clocemi, 0, 7);
        $id .= isset($p->cnpj) ? '2' : '1';
        $inscricao = $p->cnpj ?? $p->cpf ?? '';
        $id .= str_pad($inscricao, 14, '0', STR_PAD_LEFT);
        $id .= str_pad((string) $d->serie, 5, '0', STR_PAD_LEFT);
        $id .= str_pad((string) $d->ndps, 15, '0', STR_PAD_LEFT);
        return $id;
    }
}
