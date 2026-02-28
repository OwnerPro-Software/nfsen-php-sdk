<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

final class TomadorBuilder
{
    public function build(DOMDocument $doc, stdClass $toma): DOMElement
    {
        $el = $doc->createElement('toma');

        if (isset($toma->cnpj)) {
            $el->appendChild($this->text($doc, 'CNPJ', $toma->cnpj));
        }

        if (isset($toma->cpf)) {
            $el->appendChild($this->text($doc, 'CPF', $toma->cpf));
        }

        if (isset($toma->nif)) {
            $el->appendChild($this->text($doc, 'NIF', $toma->nif));
        }

        if (isset($toma->cnaonif)) {
            $el->appendChild($this->text($doc, 'cNaoNIF', $toma->cnaonif));
        }

        if (isset($toma->caepf)) {
            $el->appendChild($this->text($doc, 'CAEPF', $toma->caepf));
        }

        if (isset($toma->im)) {
            $el->appendChild($this->text($doc, 'IM', $toma->im));
        }

        $el->appendChild($this->text($doc, 'xNome', $toma->xnome));

        if (isset($toma->end)) {
            $endEl = $doc->createElement('end');
            if (isset($toma->end->endnac)) {
                $endNac = $doc->createElement('endNac');
                $endNac->appendChild($this->text($doc, 'cMun', $toma->end->endnac->cmun));
                $endNac->appendChild($this->text($doc, 'CEP', $toma->end->endnac->cep));
                $endEl->appendChild($endNac);
            } elseif (isset($toma->end->endext)) {
                $endExt = $doc->createElement('endExt');
                $endExt->appendChild($this->text($doc, 'cPais', $toma->end->endext->cpais));
                $endExt->appendChild($this->text($doc, 'cEndPost', $toma->end->endext->cendpost));
                $endExt->appendChild($this->text($doc, 'xCidade', $toma->end->endext->xcidade));
                $endExt->appendChild($this->text($doc, 'xEstProvReg', $toma->end->endext->xestprovreg));
                $endEl->appendChild($endExt);
            }

            $endEl->appendChild($this->text($doc, 'xLgr', $toma->end->xlgr));
            $endEl->appendChild($this->text($doc, 'nro', $toma->end->nro));
            if (isset($toma->end->xcpl)) {
                $endEl->appendChild($this->text($doc, 'xCpl', $toma->end->xcpl));
            }

            $endEl->appendChild($this->text($doc, 'xBairro', $toma->end->xbairro));
            $el->appendChild($endEl);
        }

        if (isset($toma->fone)) {
            $el->appendChild($this->text($doc, 'fone', $toma->fone));
        }

        if (isset($toma->email)) {
            $el->appendChild($this->text($doc, 'email', $toma->email));
        }

        return $el;
    }

    private function text(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
