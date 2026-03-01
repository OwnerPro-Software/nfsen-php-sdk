<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Support\XsdValidator;

final readonly class CancelamentoBuilder
{
    private const VERSION = '1.01';

    private const XMLNS = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(
        private XsdValidator $xsdValidator,
    ) {}

    public function buildAndValidate(
        int $tpAmb,
        string $verAplic,
        string $dhEvento,
        ?string $cnpjAutor,
        ?string $cpfAutor,
        string $chNFSe,
        CodigoJustificativaCancelamento $codigoMotivo,
        string $descricao,
        int $nPedRegEvento = 1,
    ): string {
        $xml = $this->build(
            $tpAmb, $verAplic, $dhEvento, $cnpjAutor, $cpfAutor,
            $chNFSe, $codigoMotivo, $descricao, $nPedRegEvento,
        );
        $this->xsdValidator->validate($xml, 'pedRegEvento_v1.01.xsd');

        return $xml;
    }

    public function build(
        int $tpAmb,
        string $verAplic,
        string $dhEvento,
        ?string $cnpjAutor,
        ?string $cpfAutor,
        string $chNFSe,
        CodigoJustificativaCancelamento $codigoMotivo,
        string $descricao,
        int $nPedRegEvento = 1,
    ): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $root = $doc->createElement('pedRegEvento');
        $root->setAttribute('versao', self::VERSION);
        $root->setAttribute('xmlns', self::XMLNS);

        $infPedReg = $doc->createElement('infPedReg');
        $infPedReg->setAttribute('Id', $this->generateId($chNFSe, $nPedRegEvento));

        $infPedReg->appendChild($this->text($doc, 'tpAmb', (string) $tpAmb));
        $infPedReg->appendChild($this->text($doc, 'verAplic', $verAplic));
        $infPedReg->appendChild($this->text($doc, 'dhEvento', $dhEvento));

        if ($cnpjAutor !== null) {
            $infPedReg->appendChild($this->text($doc, 'CNPJAutor', $cnpjAutor));
        } elseif ($cpfAutor !== null) {
            $infPedReg->appendChild($this->text($doc, 'CPFAutor', $cpfAutor));
        }

        $infPedReg->appendChild($this->text($doc, 'chNFSe', $chNFSe));
        $infPedReg->appendChild($this->text($doc, 'nPedRegEvento', (string) $nPedRegEvento));

        $evento = $doc->createElement('e101101');
        $evento->appendChild($this->text($doc, 'xDesc', 'Cancelamento de NFS-e'));
        $evento->appendChild($this->text($doc, 'cMotivo', $codigoMotivo->value));
        $evento->appendChild($this->text($doc, 'xMotivo', $descricao));

        $infPedReg->appendChild($evento);

        $root->appendChild($infPedReg);
        $doc->appendChild($root);

        return (string) $doc->saveXML($doc->documentElement);
    }

    private function generateId(string $chNFSe, int $nPedRegEvento): string
    {
        return 'PRE'.$chNFSe.'101101'.str_pad((string) $nPedRegEvento, 3, '0', STR_PAD_LEFT);
    }

    private function text(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
