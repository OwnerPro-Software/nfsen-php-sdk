<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

final class ServicoBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, stdClass $serv): DOMElement
    {
        $el = $doc->createElement('serv');

        // locPrest (obrigatório — choice)
        $locPrest = $doc->createElement('locPrest');
        if (isset($serv->locprest->clocprestacao)) {
            $locPrest->appendChild($this->text($doc, 'cLocPrestacao', $serv->locprest->clocprestacao));
        } elseif (isset($serv->locprest->cpaisprestacao)) {
            $locPrest->appendChild($this->text($doc, 'cPaisPrestacao', $serv->locprest->cpaisprestacao));
        }

        $el->appendChild($locPrest);

        // cServ (obrigatório)
        $cServ = $doc->createElement('cServ');
        $cServ->appendChild($this->text($doc, 'cTribNac', $serv->cserv->ctribnac));
        if (isset($serv->cserv->ctribmun)) {
            $cServ->appendChild($this->text($doc, 'cTribMun', $serv->cserv->ctribmun));
        }

        $cServ->appendChild($this->text($doc, 'xDescServ', $serv->cserv->xdescserv));
        $cServ->appendChild($this->text($doc, 'cNBS', $serv->cserv->cnbs));
        if (isset($serv->cserv->cintcontrib)) {
            $cServ->appendChild($this->text($doc, 'cIntContrib', $serv->cserv->cintcontrib));
        }

        $el->appendChild($cServ);

        // comExt (opcional)
        if (isset($serv->comext)) {
            $comExt = $doc->createElement('comExt');
            $comExt->appendChild($this->text($doc, 'mdPrestacao', $serv->comext->mdprestacao));
            $comExt->appendChild($this->text($doc, 'vincPrest', $serv->comext->vincprest));
            $comExt->appendChild($this->text($doc, 'tpMoeda', $serv->comext->tpmoeda));
            $comExt->appendChild($this->text($doc, 'vServMoeda', $serv->comext->vservmoeda));
            $comExt->appendChild($this->text($doc, 'mecAFComexP', $serv->comext->mecafcomexp));
            $comExt->appendChild($this->text($doc, 'mecAFComexT', $serv->comext->mecafcomext));
            $comExt->appendChild($this->text($doc, 'movTempBens', $serv->comext->movtempbens));
            if (isset($serv->comext->ndi)) {
                $comExt->appendChild($this->text($doc, 'nDI', $serv->comext->ndi));
            }

            if (isset($serv->comext->nre)) {
                $comExt->appendChild($this->text($doc, 'nRE', $serv->comext->nre));
            }

            $comExt->appendChild($this->text($doc, 'mdic', $serv->comext->mdic));
            $el->appendChild($comExt);
        }

        // obra (opcional)
        if (isset($serv->obra)) {
            $obra = $doc->createElement('obra');
            if (isset($serv->obra->inscimobfisc)) {
                $obra->appendChild($this->text($doc, 'inscImobFisc', $serv->obra->inscimobfisc));
            }

            // choice (obrigatório): cObra | cCIB | end
            if (isset($serv->obra->cobra)) {
                $obra->appendChild($this->text($doc, 'cObra', $serv->obra->cobra));
            } elseif (isset($serv->obra->ccib)) {
                $obra->appendChild($this->text($doc, 'cCIB', $serv->obra->ccib));
            } elseif (isset($serv->obra->end)) {
                $endObra = $doc->createElement('end');
                if (isset($serv->obra->end->cep)) {
                    $endObra->appendChild($this->text($doc, 'CEP', $serv->obra->end->cep));
                } elseif (isset($serv->obra->end->endext)) {
                    $endExt = $doc->createElement('endExt');
                    $endExt->appendChild($this->text($doc, 'cEndPost', $serv->obra->end->endext->cendpost));
                    $endExt->appendChild($this->text($doc, 'xCidade', $serv->obra->end->endext->xcidade));
                    $endExt->appendChild($this->text($doc, 'xEstProvReg', $serv->obra->end->endext->xestprovreg));
                    $endObra->appendChild($endExt);
                }

                $endObra->appendChild($this->text($doc, 'xLgr', $serv->obra->end->xlgr));
                $endObra->appendChild($this->text($doc, 'nro', $serv->obra->end->nro));
                if (isset($serv->obra->end->xcpl)) {
                    $endObra->appendChild($this->text($doc, 'xCpl', $serv->obra->end->xcpl));
                }

                $endObra->appendChild($this->text($doc, 'xBairro', $serv->obra->end->xbairro));
                $obra->appendChild($endObra);
            }

            $el->appendChild($obra);
        }

        return $el;
    }
}
