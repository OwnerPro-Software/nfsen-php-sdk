<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class ServicoBuilder
{
    public function build(DOMDocument $doc, stdClass $serv): DOMElement
    {
        $el = $doc->createElement('serv');

        // locPrest (obrigatório)
        $locPrest = $doc->createElement('locPrest');
        $locPrest->appendChild($doc->createElement('cLocPrestacao', $serv->locprest->clocprestacao));
        if (isset($serv->locprest->cpaisprestacao)) {
            $locPrest->appendChild($doc->createElement('cPaisPrestacao', $serv->locprest->cpaisprestacao));
        }

        $el->appendChild($locPrest);

        // cServ (obrigatório)
        $cServ = $doc->createElement('cServ');
        $cServ->appendChild($doc->createElement('cTribNac', $serv->cserv->ctribnac));
        if (isset($serv->cserv->ctribmun)) {
            $cServ->appendChild($doc->createElement('cTribMun', $serv->cserv->ctribmun));
        }

        $cServ->appendChild($doc->createElement('xDescServ', $serv->cserv->xdescserv));
        if (isset($serv->cserv->cnbs)) {
            $cServ->appendChild($doc->createElement('cNBS', $serv->cserv->cnbs));
        }

        if (isset($serv->cserv->cintcontrib)) {
            $cServ->appendChild($doc->createElement('cIntContrib', $serv->cserv->cintcontrib));
        }

        $el->appendChild($cServ);

        // comExt (opcional)
        if (isset($serv->comext)) {
            $comExt = $doc->createElement('comExt');
            $comExt->appendChild($doc->createElement('mdPrestacao', $serv->comext->mdprestacao));
            $comExt->appendChild($doc->createElement('vincPrest', $serv->comext->vincprest));
            $comExt->appendChild($doc->createElement('tpMoeda', $serv->comext->tpmoeda));
            $comExt->appendChild($doc->createElement('vServMoeda', $serv->comext->vservmoeda));
            $comExt->appendChild($doc->createElement('mecAFComexP', $serv->comext->mecafcomexp));
            $comExt->appendChild($doc->createElement('mecAFComexT', $serv->comext->mecafcomext));
            $comExt->appendChild($doc->createElement('movTempBens', $serv->comext->movtempbens));
            if (isset($serv->comext->ndi)) {
                $comExt->appendChild($doc->createElement('nDI', $serv->comext->ndi));
            }

            if (isset($serv->comext->nre)) {
                $comExt->appendChild($doc->createElement('nRE', $serv->comext->nre));
            }

            $comExt->appendChild($doc->createElement('mdic', $serv->comext->mdic));
            $el->appendChild($comExt);
        }

        // obra (opcional)
        if (isset($serv->obra)) {
            $obra = $doc->createElement('obra');
            if (isset($serv->obra->inscimobfisc)) {
                $obra->appendChild($doc->createElement('inscImobFisc', $serv->obra->inscimobfisc));
            }

            if (isset($serv->obra->cobra)) {
                $obra->appendChild($doc->createElement('cObra', $serv->obra->cobra));
            }

            if (isset($serv->obra->ccib)) {
                $obra->appendChild($doc->createElement('cCIB', $serv->obra->ccib));
            }

            if (isset($serv->obra->end)) {
                $endObra = $doc->createElement('end');
                if (isset($serv->obra->end->cep)) {
                    $endObra->appendChild($doc->createElement('CEP', $serv->obra->end->cep));
                }

                if (isset($serv->obra->end->cmun)) {
                    $endObra->appendChild($doc->createElement('cMun', $serv->obra->end->cmun));
                }

                if (isset($serv->obra->end->xlgr)) {
                    $endObra->appendChild($doc->createElement('xLgr', $serv->obra->end->xlgr));
                }

                if (isset($serv->obra->end->nro)) {
                    $endObra->appendChild($doc->createElement('nro', $serv->obra->end->nro));
                }

                if (isset($serv->obra->end->xcpl)) {
                    $endObra->appendChild($doc->createElement('xCpl', $serv->obra->end->xcpl));
                }

                if (isset($serv->obra->end->xbairro)) {
                    $endObra->appendChild($doc->createElement('xBairro', $serv->obra->end->xbairro));
                }

                $obra->appendChild($endObra);
            }

            $el->appendChild($obra);
        }

        return $el;
    }
}
