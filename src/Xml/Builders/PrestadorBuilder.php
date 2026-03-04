<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\Dps\DTO\Prestador\Prestador;
use Pulsar\NfseNacional\Dps\DTO\Shared\Endereco;
use Pulsar\NfseNacional\Dps\Enums\Prestador\RegApTribSN;
use Pulsar\NfseNacional\Dps\Enums\Shared\CodNaoNIF;

final class PrestadorBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, Prestador $prest): DOMElement
    {
        $el = $doc->createElement('prest');

        if ($prest->CNPJ !== null) {
            $el->appendChild($this->text($doc, 'CNPJ', $prest->CNPJ));
        } elseif ($prest->CPF !== null) {
            $el->appendChild($this->text($doc, 'CPF', $prest->CPF));
        } elseif ($prest->NIF !== null) {
            $el->appendChild($this->text($doc, 'NIF', $prest->NIF));
        } elseif ($prest->cNaoNIF instanceof CodNaoNIF) { // @pest-mutate-ignore InstanceOfToTrue unkillable — validation guarantees exactly one ID
            $el->appendChild($this->text($doc, 'cNaoNIF', $prest->cNaoNIF->value));
        }

        if ($prest->CAEPF !== null) {
            $el->appendChild($this->text($doc, 'CAEPF', $prest->CAEPF));
        }

        if ($prest->IM !== null) {
            $el->appendChild($this->text($doc, 'IM', $prest->IM));
        }

        if ($prest->xNome !== null) {
            $el->appendChild($this->text($doc, 'xNome', $prest->xNome));
        }

        if ($prest->end instanceof Endereco) {
            $el->appendChild($this->buildEnd($doc, $prest->end));
        }

        if ($prest->fone !== null) {
            $el->appendChild($this->text($doc, 'fone', $prest->fone));
        }

        if ($prest->email !== null) {
            $el->appendChild($this->text($doc, 'email', $prest->email));
        }

        $regTrib = $doc->createElement('regTrib');
        $regTrib->appendChild($this->text($doc, 'opSimpNac', $prest->regTrib->opSimpNac->value));
        if ($prest->regTrib->regApTribSN instanceof RegApTribSN) {
            $regTrib->appendChild($this->text($doc, 'regApTribSN', $prest->regTrib->regApTribSN->value));
        }

        $regTrib->appendChild($this->text($doc, 'regEspTrib', $prest->regTrib->regEspTrib->value));
        $el->appendChild($regTrib);

        return $el;
    }
}
