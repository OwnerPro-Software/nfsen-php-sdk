<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Xml\Builders;

use DOMDocument;
use DOMElement;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Dest;
use OwnerPro\Nfsen\Dps\DTO\Shared\End;
use OwnerPro\Nfsen\Dps\Enums\Shared\CNaoNIF;

final class DestinatarioBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, Dest $dest): DOMElement
    {
        $el = $doc->createElement('dest');

        if ($dest->CNPJ !== null) {
            $el->appendChild($this->text($doc, 'CNPJ', $dest->CNPJ));
        } elseif ($dest->CPF !== null) {
            $el->appendChild($this->text($doc, 'CPF', $dest->CPF));
        } elseif ($dest->NIF !== null) {
            $el->appendChild($this->text($doc, 'NIF', $dest->NIF));
        } elseif ($dest->cNaoNIF instanceof CNaoNIF) { // @pest-mutate-ignore InstanceOfToTrue unkillable — validation guarantees exactly one ID
            $el->appendChild($this->text($doc, 'cNaoNIF', $dest->cNaoNIF->value));
        }

        $el->appendChild($this->text($doc, 'xNome', $dest->xNome));

        if ($dest->end instanceof End) {
            $el->appendChild($this->buildEnd($doc, $dest->end));
        }

        if ($dest->fone !== null) {
            $el->appendChild($this->text($doc, 'fone', $dest->fone));
        }

        if ($dest->email !== null) {
            $el->appendChild($this->text($doc, 'email', $dest->email));
        }

        return $el;
    }
}
