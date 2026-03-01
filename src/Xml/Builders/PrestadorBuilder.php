<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

final class PrestadorBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, stdClass $prest): DOMElement
    {
        $el = $doc->createElement('prest');

        // choice (obrigatório): CNPJ | CPF | NIF | cNaoNIF
        if (isset($prest->cnpj)) {
            $el->appendChild($this->text($doc, 'CNPJ', $prest->cnpj));
        } elseif (isset($prest->cpf)) {
            $el->appendChild($this->text($doc, 'CPF', $prest->cpf));
        } elseif (isset($prest->nif)) {
            $el->appendChild($this->text($doc, 'NIF', $prest->nif));
        } elseif (isset($prest->cnaonif)) {
            $el->appendChild($this->text($doc, 'cNaoNIF', $prest->cnaonif));
        }

        if (isset($prest->caepf)) {
            $el->appendChild($this->text($doc, 'CAEPF', $prest->caepf));
        }

        if (isset($prest->im)) {
            $el->appendChild($this->text($doc, 'IM', $prest->im));
        }

        if (isset($prest->xnome)) {
            $el->appendChild($this->text($doc, 'xNome', $prest->xnome));
        }

        if (isset($prest->end)) {
            $el->appendChild($this->buildEnd($doc, $prest->end));
        }

        if (isset($prest->fone)) {
            $el->appendChild($this->text($doc, 'fone', $prest->fone));
        }

        if (isset($prest->email)) {
            $el->appendChild($this->text($doc, 'email', $prest->email));
        }

        $regTrib = $doc->createElement('regTrib');
        $regTrib->appendChild($this->text($doc, 'opSimpNac', (string) $prest->regtrib->opsimpnac));
        if (isset($prest->regtrib->regaptribsn)) {
            $regTrib->appendChild($this->text($doc, 'regApTribSN', (string) $prest->regtrib->regaptribsn));
        }

        $regTrib->appendChild($this->text($doc, 'regEspTrib', (string) $prest->regtrib->regesptrib));
        $el->appendChild($regTrib);

        return $el;
    }

    private function buildEnd(DOMDocument $doc, stdClass $end): DOMElement
    {
        $el = $doc->createElement('end');
        if (isset($end->endnac)) {
            $endNac = $doc->createElement('endNac');
            $endNac->appendChild($this->text($doc, 'cMun', $end->endnac->cmun));
            $endNac->appendChild($this->text($doc, 'CEP', $end->endnac->cep));
            $el->appendChild($endNac);
        } elseif (isset($end->endext)) {
            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($this->text($doc, 'cPais', $end->endext->cpais));
            $endExt->appendChild($this->text($doc, 'cEndPost', $end->endext->cendpost));
            $endExt->appendChild($this->text($doc, 'xCidade', $end->endext->xcidade));
            $endExt->appendChild($this->text($doc, 'xEstProvReg', $end->endext->xestprovreg));
            $el->appendChild($endExt);
        }

        $el->appendChild($this->text($doc, 'xLgr', $end->xlgr));
        $el->appendChild($this->text($doc, 'nro', $end->nro));
        if (isset($end->xcpl)) {
            $el->appendChild($this->text($doc, 'xCpl', $end->xcpl));
        }

        $el->appendChild($this->text($doc, 'xBairro', $end->xbairro));

        return $el;
    }
}
