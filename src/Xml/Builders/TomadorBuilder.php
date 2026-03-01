<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use stdClass;

final class TomadorBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, stdClass $toma): DOMElement
    {
        $el = $doc->createElement('toma');

        // choice (obrigatório e exclusivo): CNPJ | CPF | NIF | cNaoNIF
        $idCount = (int) isset($toma->CNPJ) + (int) isset($toma->CPF) + (int) isset($toma->NIF) + (int) isset($toma->cNaoNIF);
        if ($idCount === 0) {
            throw new InvalidArgumentException('Tomador requer CNPJ, CPF, NIF ou cNaoNIF.');
        }

        if ($idCount > 1) {
            throw new InvalidArgumentException('Tomador deve ter apenas um entre CNPJ, CPF, NIF ou cNaoNIF.');
        }

        if (isset($toma->CNPJ)) {
            $el->appendChild($this->text($doc, 'CNPJ', $toma->CNPJ));
        } elseif (isset($toma->CPF)) {
            $el->appendChild($this->text($doc, 'CPF', $toma->CPF));
        } elseif (isset($toma->NIF)) {
            $el->appendChild($this->text($doc, 'NIF', $toma->NIF));
        } else {
            $el->appendChild($this->text($doc, 'cNaoNIF', $toma->cNaoNIF));
        }

        if (isset($toma->CAEPF)) {
            $el->appendChild($this->text($doc, 'CAEPF', $toma->CAEPF));
        }

        if (isset($toma->IM)) {
            $el->appendChild($this->text($doc, 'IM', $toma->IM));
        }

        $el->appendChild($this->text($doc, 'xNome', $toma->xNome));

        if (isset($toma->end)) {
            $endEl = $doc->createElement('end');
            if (isset($toma->end->endNac)) {
                $endNac = $doc->createElement('endNac');
                $endNac->appendChild($this->text($doc, 'cMun', $toma->end->endNac->cMun));
                $endNac->appendChild($this->text($doc, 'CEP', $toma->end->endNac->CEP));
                $endEl->appendChild($endNac);
            } elseif (isset($toma->end->endExt)) {
                $endExt = $doc->createElement('endExt');
                $endExt->appendChild($this->text($doc, 'cPais', $toma->end->endExt->cPais));
                $endExt->appendChild($this->text($doc, 'cEndPost', $toma->end->endExt->cEndPost));
                $endExt->appendChild($this->text($doc, 'xCidade', $toma->end->endExt->xCidade));
                $endExt->appendChild($this->text($doc, 'xEstProvReg', $toma->end->endExt->xEstProvReg));
                $endEl->appendChild($endExt);
            }

            $endEl->appendChild($this->text($doc, 'xLgr', $toma->end->xLgr));
            $endEl->appendChild($this->text($doc, 'nro', $toma->end->nro));
            if (isset($toma->end->xCpl)) {
                $endEl->appendChild($this->text($doc, 'xCpl', $toma->end->xCpl));
            }

            $endEl->appendChild($this->text($doc, 'xBairro', $toma->end->xBairro));
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
}
