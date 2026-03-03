<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use InvalidArgumentException;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Support\XsdValidator;

final readonly class SubstituicaoBuilder
{
    use CreatesTextElements;

    private const VERSION = '1.01';

    private const XMLNS = 'http://www.sped.fazenda.gov.br/nfse';

    public function __construct(
        private XsdValidator $xsdValidator,
    ) {}

    public function buildAndValidate(
        string $tpAmb,
        string $verAplic,
        string $dhEvento,
        ?string $cnpjAutor,
        ?string $cpfAutor,
        string $chNFSe,
        CodigoJustificativaSubstituicao $codigoMotivo,
        string $chSubstituta,
        string $descricao = '',
        int $nPedRegEvento = 1,
    ): string {
        if ($descricao !== '') {
            $this->validateDescricao($descricao);
        }

        $xml = $this->build(
            $tpAmb, $verAplic, $dhEvento, $cnpjAutor, $cpfAutor,
            $chNFSe, $codigoMotivo, $chSubstituta, $descricao, $nPedRegEvento,
        );
        $this->xsdValidator->validate($xml, 'pedRegEvento_v1.01.xsd');

        return $xml;
    }

    public function build(
        string $tpAmb,
        string $verAplic,
        string $dhEvento,
        ?string $cnpjAutor,
        ?string $cpfAutor,
        string $chNFSe,
        CodigoJustificativaSubstituicao $codigoMotivo,
        string $chSubstituta,
        string $descricao = '',
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

        $infPedReg->appendChild($this->text($doc, 'tpAmb', $tpAmb));
        $infPedReg->appendChild($this->text($doc, 'verAplic', $verAplic));
        $infPedReg->appendChild($this->text($doc, 'dhEvento', $dhEvento));

        if ($cnpjAutor !== null && $cpfAutor !== null) {
            throw new InvalidArgumentException('Apenas CNPJAutor ou CPFAutor deve ser informado, não ambos.');
        }

        if ($cnpjAutor !== null) {
            $infPedReg->appendChild($this->text($doc, 'CNPJAutor', $cnpjAutor));
        } elseif ($cpfAutor !== null) {
            $infPedReg->appendChild($this->text($doc, 'CPFAutor', $cpfAutor));
        } else {
            throw new InvalidArgumentException('CNPJAutor ou CPFAutor é obrigatório para o pedido de registro de evento.');
        }

        $infPedReg->appendChild($this->text($doc, 'chNFSe', $chNFSe));
        $infPedReg->appendChild($this->text($doc, 'nPedRegEvento', (string) $nPedRegEvento));

        $evento = $doc->createElement('e105102');
        $evento->appendChild($this->text($doc, 'xDesc', 'Cancelamento de NFS-e por Substituicao'));
        $evento->appendChild($this->text($doc, 'cMotivo', $codigoMotivo->value));

        if ($descricao !== '') {
            $evento->appendChild($this->text($doc, 'xMotivo', $descricao));
        }

        $evento->appendChild($this->text($doc, 'chSubstituta', $chSubstituta));

        $infPedReg->appendChild($evento);

        $root->appendChild($infPedReg);
        $doc->appendChild($root);

        return (string) $doc->saveXML($doc->documentElement);
    }

    private function validateDescricao(string $descricao): void
    {
        $length = mb_strlen($descricao);

        if ($length < 15 || $length > 255) {
            throw new NfseException('O campo descricao deve ter entre 15 e 255 caracteres.');
        }
    }

    private function generateId(string $chNFSe, int $nPedRegEvento): string
    {
        return 'PRE'.$chNFSe.'105102'.str_pad((string) $nPedRegEvento, 3, '0', STR_PAD_LEFT);
    }
}
