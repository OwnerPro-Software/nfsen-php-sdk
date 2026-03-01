<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use stdClass;

final class PrestadorBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, stdClass $prest): DOMElement
    {
        $el = $doc->createElement('prest');

        // choice (obrigatório e exclusivo): CNPJ | CPF | NIF | cNaoNIF
        $idCount = (int) isset($prest->CNPJ) + (int) isset($prest->CPF) + (int) isset($prest->NIF) + (int) isset($prest->cNaoNIF);
        if ($idCount === 0) {
            throw new InvalidArgumentException('Prestador requer CNPJ, CPF, NIF ou cNaoNIF.');
        }

        if ($idCount > 1) {
            throw new InvalidArgumentException('Prestador deve ter apenas um entre CNPJ, CPF, NIF ou cNaoNIF.');
        }

        if (isset($prest->CNPJ)) {
            $el->appendChild($this->text($doc, 'CNPJ', $prest->CNPJ));
        } elseif (isset($prest->CPF)) {
            $el->appendChild($this->text($doc, 'CPF', $prest->CPF));
        } elseif (isset($prest->NIF)) {
            $el->appendChild($this->text($doc, 'NIF', $prest->NIF));
        } else {
            $el->appendChild($this->text($doc, 'cNaoNIF', $prest->cNaoNIF));
        }

        if (isset($prest->CAEPF)) {
            $el->appendChild($this->text($doc, 'CAEPF', $prest->CAEPF));
        }

        if (isset($prest->IM)) {
            $el->appendChild($this->text($doc, 'IM', $prest->IM));
        }

        if (isset($prest->xNome)) {
            $el->appendChild($this->text($doc, 'xNome', $prest->xNome));
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
        $regTrib->appendChild($this->text($doc, 'opSimpNac', (string) $prest->regTrib->opSimpNac));
        if (isset($prest->regTrib->regApTribSN)) {
            $regTrib->appendChild($this->text($doc, 'regApTribSN', (string) $prest->regTrib->regApTribSN));
        }

        $regTrib->appendChild($this->text($doc, 'regEspTrib', (string) $prest->regTrib->regEspTrib));
        $el->appendChild($regTrib);

        return $el;
    }

    private function buildEnd(DOMDocument $doc, stdClass $end): DOMElement
    {
        $el = $doc->createElement('end');
        if (isset($end->endNac)) {
            $endNac = $doc->createElement('endNac');
            $endNac->appendChild($this->text($doc, 'cMun', $end->endNac->cMun));
            $endNac->appendChild($this->text($doc, 'CEP', $end->endNac->CEP));
            $el->appendChild($endNac);
        } elseif (isset($end->endExt)) {
            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($this->text($doc, 'cPais', $end->endExt->cPais));
            $endExt->appendChild($this->text($doc, 'cEndPost', $end->endExt->cEndPost));
            $endExt->appendChild($this->text($doc, 'xCidade', $end->endExt->xCidade));
            $endExt->appendChild($this->text($doc, 'xEstProvReg', $end->endExt->xEstProvReg));
            $el->appendChild($endExt);
        }

        $el->appendChild($this->text($doc, 'xLgr', $end->xLgr));
        $el->appendChild($this->text($doc, 'nro', $end->nro));
        if (isset($end->xCpl)) {
            $el->appendChild($this->text($doc, 'xCpl', $end->xCpl));
        }

        $el->appendChild($this->text($doc, 'xBairro', $end->xBairro));

        return $el;
    }
}
