<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class PrestadorBuilder
{
    public function build(DOMDocument $doc, stdClass $prest): DOMElement
    {
        $el = $doc->createElement('prest');

        if (isset($prest->cnpj)) {
            $el->appendChild($doc->createElement('CNPJ', $prest->cnpj));
        }
        if (isset($prest->cpf)) {
            $el->appendChild($doc->createElement('CPF', $prest->cpf));
        }
        if (isset($prest->nif)) {
            $el->appendChild($doc->createElement('NIF', $prest->nif));
        }
        if (isset($prest->cnaonif)) {
            $el->appendChild($doc->createElement('cNaoNIF', $prest->cnaonif));
        }
        if (isset($prest->caepf)) {
            $el->appendChild($doc->createElement('CAEPF', $prest->caepf));
        }
        if (isset($prest->im)) {
            $el->appendChild($doc->createElement('IM', $prest->im));
        }
        if (isset($prest->xnome)) {
            $el->appendChild($doc->createElement('xNome', $prest->xnome));
        }
        if (isset($prest->end)) {
            $el->appendChild($this->buildEnd($doc, $prest->end));
        }
        if (isset($prest->fone)) {
            $el->appendChild($doc->createElement('fone', $prest->fone));
        }
        if (isset($prest->email)) {
            $el->appendChild($doc->createElement('email', $prest->email));
        }

        $regTrib = $doc->createElement('regTrib');
        $regTrib->appendChild($doc->createElement('opSimpNac', (string) $prest->regtrib->opsimpnac));
        if (isset($prest->regtrib->regaptribsn)) {
            $regTrib->appendChild($doc->createElement('regApTribSN', (string) $prest->regtrib->regaptribsn));
        }
        $regTrib->appendChild($doc->createElement('regEspTrib', (string) $prest->regtrib->regesptrib));
        $el->appendChild($regTrib);

        return $el;
    }

    private function buildEnd(DOMDocument $doc, stdClass $end): DOMElement
    {
        $el = $doc->createElement('end');
        if (isset($end->endnac)) {
            $endNac = $doc->createElement('endNac');
            $endNac->appendChild($doc->createElement('cMun', $end->endnac->cmun));
            $endNac->appendChild($doc->createElement('CEP', $end->endnac->cep));
            $el->appendChild($endNac);
        } elseif (isset($end->endext)) {
            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($doc->createElement('cPais', $end->endext->cpais));
            $endExt->appendChild($doc->createElement('cEndPost', $end->endext->cendpost));
            $endExt->appendChild($doc->createElement('xCidade', $end->endext->xcidade));
            $endExt->appendChild($doc->createElement('xEstProvReg', $end->endext->xestprovreg));
            $el->appendChild($endExt);
        }
        $el->appendChild($doc->createElement('xLgr', $end->xlgr));
        $el->appendChild($doc->createElement('nro', $end->nro));
        if (isset($end->xcpl)) {
            $el->appendChild($doc->createElement('xCpl', $end->xcpl));
        }
        $el->appendChild($doc->createElement('xBairro', $end->xbairro));
        return $el;
    }
}
