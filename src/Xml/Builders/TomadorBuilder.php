<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class TomadorBuilder
{
    public function build(DOMDocument $doc, stdClass $toma): DOMElement
    {
        $el = $doc->createElement('toma');

        if (isset($toma->cnpj)) {
            $el->appendChild($doc->createElement('CNPJ', $toma->cnpj));
        }

        if (isset($toma->cpf)) {
            $el->appendChild($doc->createElement('CPF', $toma->cpf));
        }

        if (isset($toma->nif)) {
            $el->appendChild($doc->createElement('NIF', $toma->nif));
        }

        if (isset($toma->cnaonif)) {
            $el->appendChild($doc->createElement('cNaoNIF', $toma->cnaonif));
        }

        if (isset($toma->caepf)) {
            $el->appendChild($doc->createElement('CAEPF', $toma->caepf));
        }

        if (isset($toma->im)) {
            $el->appendChild($doc->createElement('IM', $toma->im));
        }

        $el->appendChild($doc->createElement('xNome', $toma->xnome));

        if (isset($toma->end)) {
            $endEl = $doc->createElement('end');
            if (isset($toma->end->endnac)) {
                $endNac = $doc->createElement('endNac');
                $endNac->appendChild($doc->createElement('cMun', $toma->end->endnac->cmun));
                $endNac->appendChild($doc->createElement('CEP', $toma->end->endnac->cep));
                $endEl->appendChild($endNac);
            } elseif (isset($toma->end->endext)) {
                $endExt = $doc->createElement('endExt');
                $endExt->appendChild($doc->createElement('cPais', $toma->end->endext->cpais));
                $endExt->appendChild($doc->createElement('cEndPost', $toma->end->endext->cendpost));
                $endExt->appendChild($doc->createElement('xCidade', $toma->end->endext->xcidade));
                $endExt->appendChild($doc->createElement('xEstProvReg', $toma->end->endext->xestprovreg));
                $endEl->appendChild($endExt);
            }

            $endEl->appendChild($doc->createElement('xLgr', $toma->end->xlgr));
            $endEl->appendChild($doc->createElement('nro', $toma->end->nro));
            if (isset($toma->end->xcpl)) {
                $endEl->appendChild($doc->createElement('xCpl', $toma->end->xcpl));
            }

            $endEl->appendChild($doc->createElement('xBairro', $toma->end->xbairro));
            $el->appendChild($endEl);
        }

        if (isset($toma->fone)) {
            $el->appendChild($doc->createElement('fone', $toma->fone));
        }

        if (isset($toma->email)) {
            $el->appendChild($doc->createElement('email', $toma->email));
        }

        return $el;
    }
}
