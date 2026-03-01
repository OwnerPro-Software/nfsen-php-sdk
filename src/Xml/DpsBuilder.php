<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml;

use DOMDocument;
use Pulsar\NfseNacional\DTOs\DpsData;
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

        $d = $data->infDps;
        $infDps->appendChild($this->text($doc, 'tpAmb', (string) $d->tpamb));
        $infDps->appendChild($this->text($doc, 'dhEmi', $d->dhemi));
        $infDps->appendChild($this->text($doc, 'verAplic', $d->veraplic));
        $infDps->appendChild($this->text($doc, 'serie', $d->serie));
        $infDps->appendChild($this->text($doc, 'nDPS', (string) $d->ndps));
        $infDps->appendChild($this->text($doc, 'dCompet', $d->dcompet));
        $infDps->appendChild($this->text($doc, 'tpEmit', (string) $d->tpemit));
        if (isset($d->cmotivoemisti)) {
            $infDps->appendChild($this->text($doc, 'cMotivoEmisTI', $d->cmotivoemisti));
        }

        if (isset($d->chnfserej)) {
            $infDps->appendChild($this->text($doc, 'chNFSeRej', $d->chnfserej));
        }

        $infDps->appendChild($this->text($doc, 'cLocEmi', $d->clocemi));

        if ((array) $data->prestador !== []) {
            $infDps->appendChild((new PrestadorBuilder)->build($doc, $data->prestador));
        }

        // toma (optional)
        if ((array) $data->tomador !== []) {
            $infDps->appendChild((new TomadorBuilder)->build($doc, $data->tomador));
        }

        // serv (obrigatório)
        $infDps->appendChild((new ServicoBuilder)->build($doc, $data->servico));

        // valores (obrigatório quando houver dados)
        if ((array) $data->valores !== []) {
            $infDps->appendChild((new ValoresBuilder)->build($doc, $data->valores));
        }

        $dps->appendChild($infDps);
        $doc->appendChild($dps);

        return (string) $doc->saveXML($doc->documentElement);
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

        return $id.str_pad((string) $d->ndps, 15, '0', STR_PAD_LEFT);
    }
}
