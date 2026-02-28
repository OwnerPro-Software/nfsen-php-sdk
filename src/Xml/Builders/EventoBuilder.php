<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;

class EventoBuilder
{
    private const VERSION = '1.01';

    private const XMLNS = 'http://www.sped.fazenda.gov.br/nfse';

    public function build(
        int $tpAmb,
        string $verAplic,
        string $dhEvento,
        ?string $cnpjAutor,
        ?string $cpfAutor,
        string $chNFSe,
        MotivoCancelamento $motivo,
        string $descricao,
    ): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $root = $doc->createElement('pedRegEvento');
        $root->setAttribute('versao', self::VERSION);
        $root->setAttribute('xmlns', self::XMLNS);

        $infPedReg = $doc->createElement('infPedReg');
        $infPedReg->setAttribute('Id', $this->generateId($chNFSe, $motivo));

        $infPedReg->appendChild($this->text($doc, 'tpAmb', (string) $tpAmb));
        $infPedReg->appendChild($this->text($doc, 'verAplic', $verAplic));
        $infPedReg->appendChild($this->text($doc, 'dhEvento', $dhEvento));

        if ($cnpjAutor !== null) {
            $infPedReg->appendChild($this->text($doc, 'CNPJAutor', $cnpjAutor));
        } elseif ($cpfAutor !== null) {
            $infPedReg->appendChild($this->text($doc, 'CPFAutor', $cpfAutor));
        }

        $infPedReg->appendChild($this->text($doc, 'chNFSe', $chNFSe));

        $xDesc = match ($motivo) {
            MotivoCancelamento::ErroEmissao => 'Cancelamento de NFS-e',
            MotivoCancelamento::Outros => 'Cancelamento de NFS-e por Substituicao',
        };

        $motivoEl = $doc->createElement($motivo->value);
        $motivoEl->appendChild($this->text($doc, 'xDesc', $xDesc));
        $motivoEl->appendChild($this->text($doc, 'cMotivo', $motivo->value));
        $motivoEl->appendChild($this->text($doc, 'xMotivo', $descricao));

        $infPedReg->appendChild($motivoEl);

        $root->appendChild($infPedReg);
        $doc->appendChild($root);

        return (string) $doc->saveXML($doc->documentElement);
    }

    private function generateId(string $chNFSe, MotivoCancelamento $motivo): string
    {
        $codigo = $motivo === MotivoCancelamento::ErroEmissao ? '101101' : '105102';

        return 'PRE'.$chNFSe.$codigo;
    }

    private function text(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
