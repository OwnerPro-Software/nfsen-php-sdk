<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\DTOs\Dps\Shared\Endereco;
use Pulsar\NfseNacional\DTOs\Dps\Tomador\Tomador;
use Pulsar\NfseNacional\Enums\Dps\Shared\CodNaoNIF;

final class TomadorBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, Tomador $toma): DOMElement
    {
        $el = $doc->createElement('toma');

        if ($toma->CNPJ !== null) {
            $el->appendChild($this->text($doc, 'CNPJ', $toma->CNPJ));
        } elseif ($toma->CPF !== null) {
            $el->appendChild($this->text($doc, 'CPF', $toma->CPF));
        } elseif ($toma->NIF !== null) {
            $el->appendChild($this->text($doc, 'NIF', $toma->NIF));
        } elseif ($toma->cNaoNIF instanceof CodNaoNIF) {
            $el->appendChild($this->text($doc, 'cNaoNIF', $toma->cNaoNIF->value));
        }

        if ($toma->CAEPF !== null) {
            $el->appendChild($this->text($doc, 'CAEPF', $toma->CAEPF));
        }

        if ($toma->IM !== null) {
            $el->appendChild($this->text($doc, 'IM', $toma->IM));
        }

        $el->appendChild($this->text($doc, 'xNome', $toma->xNome));

        if ($toma->end instanceof Endereco) {
            $el->appendChild($this->buildEnd($doc, $toma->end));
        }

        if ($toma->fone !== null) {
            $el->appendChild($this->text($doc, 'fone', $toma->fone));
        }

        if ($toma->email !== null) {
            $el->appendChild($this->text($doc, 'email', $toma->email));
        }

        return $el;
    }
}
