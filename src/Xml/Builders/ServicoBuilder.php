<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use stdClass;

final class ServicoBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, stdClass $serv): DOMElement
    {
        $el = $doc->createElement('serv');

        // locPrest (obrigatório e exclusivo — choice)
        if (isset($serv->locPrest->cLocPrestacao) && isset($serv->locPrest->cPaisPrestacao)) {
            throw new InvalidArgumentException('locPrest deve ter apenas cLocPrestacao ou cPaisPrestacao, não ambos.');
        }

        $locPrest = $doc->createElement('locPrest');
        if (isset($serv->locPrest->cLocPrestacao)) {
            $locPrest->appendChild($this->text($doc, 'cLocPrestacao', $serv->locPrest->cLocPrestacao));
        } elseif (isset($serv->locPrest->cPaisPrestacao)) {
            $locPrest->appendChild($this->text($doc, 'cPaisPrestacao', $serv->locPrest->cPaisPrestacao));
        } else {
            throw new InvalidArgumentException('locPrest requer cLocPrestacao ou cPaisPrestacao.');
        }

        $el->appendChild($locPrest);

        // cServ (obrigatório)
        $cServ = $doc->createElement('cServ');
        $cServ->appendChild($this->text($doc, 'cTribNac', $serv->cServ->cTribNac));
        if (isset($serv->cServ->cTribMun)) {
            $cServ->appendChild($this->text($doc, 'cTribMun', $serv->cServ->cTribMun));
        }

        $cServ->appendChild($this->text($doc, 'xDescServ', $serv->cServ->xDescServ));
        $cServ->appendChild($this->text($doc, 'cNBS', $serv->cServ->cNBS));
        if (isset($serv->cServ->cIntContrib)) {
            $cServ->appendChild($this->text($doc, 'cIntContrib', $serv->cServ->cIntContrib));
        }

        $el->appendChild($cServ);

        // comExt (opcional)
        if (isset($serv->comExt)) {
            $comExt = $doc->createElement('comExt');
            $comExt->appendChild($this->text($doc, 'mdPrestacao', $serv->comExt->mdPrestacao));
            $comExt->appendChild($this->text($doc, 'vincPrest', $serv->comExt->vincPrest));
            $comExt->appendChild($this->text($doc, 'tpMoeda', $serv->comExt->tpMoeda));
            $comExt->appendChild($this->text($doc, 'vServMoeda', $serv->comExt->vServMoeda));
            $comExt->appendChild($this->text($doc, 'mecAFComexP', $serv->comExt->mecAFComexP));
            $comExt->appendChild($this->text($doc, 'mecAFComexT', $serv->comExt->mecAFComexT));
            $comExt->appendChild($this->text($doc, 'movTempBens', $serv->comExt->movTempBens));
            if (isset($serv->comExt->nDI)) {
                $comExt->appendChild($this->text($doc, 'nDI', $serv->comExt->nDI));
            }

            if (isset($serv->comExt->nRE)) {
                $comExt->appendChild($this->text($doc, 'nRE', $serv->comExt->nRE));
            }

            $comExt->appendChild($this->text($doc, 'mdic', $serv->comExt->mdic));
            $el->appendChild($comExt);
        }

        // obra (opcional)
        if (isset($serv->obra)) {
            $obra = $doc->createElement('obra');
            if (isset($serv->obra->inscImobFisc)) {
                $obra->appendChild($this->text($doc, 'inscImobFisc', $serv->obra->inscImobFisc));
            }

            // choice (obrigatório e exclusivo): cObra | cCIB | end
            $obraChoiceCount = (int) isset($serv->obra->cObra) + (int) isset($serv->obra->cCIB) + (int) isset($serv->obra->end);
            if ($obraChoiceCount > 1) {
                throw new InvalidArgumentException('Obra deve ter apenas um entre cObra, cCIB ou end.');
            }

            if (isset($serv->obra->cObra)) {
                $obra->appendChild($this->text($doc, 'cObra', $serv->obra->cObra));
            } elseif (isset($serv->obra->cCIB)) {
                $obra->appendChild($this->text($doc, 'cCIB', $serv->obra->cCIB));
            } elseif (isset($serv->obra->end)) {
                $endObra = $doc->createElement('end');
                if (isset($serv->obra->end->CEP)) {
                    $endObra->appendChild($this->text($doc, 'CEP', $serv->obra->end->CEP));
                } elseif (isset($serv->obra->end->endExt)) {
                    $endExt = $doc->createElement('endExt');
                    $endExt->appendChild($this->text($doc, 'cEndPost', $serv->obra->end->endExt->cEndPost));
                    $endExt->appendChild($this->text($doc, 'xCidade', $serv->obra->end->endExt->xCidade));
                    $endExt->appendChild($this->text($doc, 'xEstProvReg', $serv->obra->end->endExt->xEstProvReg));
                    $endObra->appendChild($endExt);
                }

                $endObra->appendChild($this->text($doc, 'xLgr', $serv->obra->end->xLgr));
                $endObra->appendChild($this->text($doc, 'nro', $serv->obra->end->nro));
                if (isset($serv->obra->end->xCpl)) {
                    $endObra->appendChild($this->text($doc, 'xCpl', $serv->obra->end->xCpl));
                }

                $endObra->appendChild($this->text($doc, 'xBairro', $serv->obra->end->xBairro));
                $obra->appendChild($endObra);
            }

            $el->appendChild($obra);
        }

        return $el;
    }
}
