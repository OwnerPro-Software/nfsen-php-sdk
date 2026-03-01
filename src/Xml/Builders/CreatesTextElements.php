<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\DTOs\Dps\Shared\Endereco;
use Pulsar\NfseNacional\DTOs\Dps\Shared\EnderecoExterior;
use Pulsar\NfseNacional\DTOs\Dps\Shared\EnderecoNacional;

trait CreatesTextElements
{
    private function text(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }

    private function buildEnd(DOMDocument $doc, Endereco $end): DOMElement
    {
        $el = $doc->createElement('end');
        if ($end->endNac instanceof EnderecoNacional) {
            $endNac = $doc->createElement('endNac');
            $endNac->appendChild($this->text($doc, 'cMun', $end->endNac->cMun));
            $endNac->appendChild($this->text($doc, 'CEP', $end->endNac->CEP));
            $el->appendChild($endNac);
        } elseif ($end->endExt instanceof EnderecoExterior) {
            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($this->text($doc, 'cPais', $end->endExt->cPais));
            $endExt->appendChild($this->text($doc, 'cEndPost', $end->endExt->cEndPost));
            $endExt->appendChild($this->text($doc, 'xCidade', $end->endExt->xCidade));
            $endExt->appendChild($this->text($doc, 'xEstProvReg', $end->endExt->xEstProvReg));
            $el->appendChild($endExt);
        }

        $el->appendChild($this->text($doc, 'xLgr', $end->xLgr));
        $el->appendChild($this->text($doc, 'nro', $end->nro));
        if ($end->xCpl !== null) {
            $el->appendChild($this->text($doc, 'xCpl', $end->xCpl));
        }

        $el->appendChild($this->text($doc, 'xBairro', $end->xBairro));

        return $el;
    }
}
