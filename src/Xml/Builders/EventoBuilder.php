<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;

class EventoBuilder
{
    private const VERSION = '1.01';
    private const XMLNS   = 'http://www.sped.fazenda.gov.br/nfse';

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
        $doc->formatOutput       = false;

        $root = $doc->createElement('pedRegEvento');
        $root->setAttribute('versao', self::VERSION);
        $root->setAttribute('xmlns', self::XMLNS);

        $infPedReg = $doc->createElement('infPedReg');
        $infPedReg->setAttribute('Id', $this->generateId($chNFSe, $motivo));

        $infPedReg->appendChild($doc->createElement('tpAmb', (string) $tpAmb));
        $infPedReg->appendChild($doc->createElement('verAplic', $verAplic));
        $infPedReg->appendChild($doc->createElement('dhEvento', $dhEvento));

        if ($cnpjAutor !== null) {
            $infPedReg->appendChild($doc->createElement('CNPJAutor', $cnpjAutor));
        } elseif ($cpfAutor !== null) {
            $infPedReg->appendChild($doc->createElement('CPFAutor', $cpfAutor));
        }

        $infPedReg->appendChild($doc->createElement('chNFSe', $chNFSe));

        $xDesc = match ($motivo) {
            MotivoCancelamento::ErroEmissao => 'Cancelamento de NFS-e',
            MotivoCancelamento::Outros      => 'Cancelamento de NFS-e por Substituicao',
        };

        $motivoEl = $doc->createElement($motivo->value);
        $motivoEl->appendChild($doc->createElement('xDesc', $xDesc));
        $motivoEl->appendChild($doc->createElement('cMotivo', $motivo->value));
        $motivoEl->appendChild($doc->createElement('xMotivo', $descricao));
        $infPedReg->appendChild($motivoEl);

        $root->appendChild($infPedReg);
        $doc->appendChild($root);

        return $doc->saveXML($doc->documentElement);
    }

    private function generateId(string $chNFSe, MotivoCancelamento $motivo): string
    {
        $codigo = $motivo === MotivoCancelamento::ErroEmissao ? '101101' : '105102';
        return 'PRE' . $chNFSe . $codigo;
    }
}
