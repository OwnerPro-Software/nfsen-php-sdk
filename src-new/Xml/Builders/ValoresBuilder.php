<?php

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class ValoresBuilder
{
    public function build(DOMDocument $doc, stdClass $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        if (isset($valores->vservprest)) {
            $vServ = $doc->createElement('vServPrest');
            if (isset($valores->vservprest->vtrib)) {
                $vServ->appendChild($doc->createElement('vTrib', $valores->vservprest->vtrib));
            }
            if (isset($valores->vservprest->vdeduct)) {
                $vServ->appendChild($doc->createElement('vDeduct', $valores->vservprest->vdeduct));
            }
            $el->appendChild($vServ);
        }

        if (isset($valores->trib)) {
            $el->appendChild($this->buildTrib($doc, $valores->trib));
        }

        return $el;
    }

    private function buildTrib(DOMDocument $doc, stdClass $trib): DOMElement
    {
        $el = $doc->createElement('trib');

        if (isset($trib->tribmun)) {
            $tribMun = $doc->createElement('tribMun');
            if (isset($trib->tribmun->vtrib)) {
                $tribMun->appendChild($doc->createElement('vTrib', $trib->tribmun->vtrib));
            }
            if (isset($trib->tribmun->tribissvexig)) {
                $tribMun->appendChild($doc->createElement('tribISSVExig', $trib->tribmun->tribissvexig));
            }
            if (isset($trib->tribmun->xexig)) {
                $tribMun->appendChild($doc->createElement('xExig', $trib->tribmun->xexig));
            }
            $el->appendChild($tribMun);
        }

        if (isset($trib->gtribfed)) {
            $gTribFed = $doc->createElement('gTribFed');
            if (isset($trib->gtribfed->piscofins)) {
                $pisCofins = $doc->createElement('pisCofins');
                if (isset($trib->gtribfed->piscofins->cstpis)) {
                    $pisCofins->appendChild($doc->createElement('cstPis', $trib->gtribfed->piscofins->cstpis));
                }
                $gTribFed->appendChild($pisCofins);
            }
            $el->appendChild($gTribFed);
        }

        if (isset($trib->totaltrib)) {
            $totTrib = $doc->createElement('totalTrib');
            if (isset($trib->totaltrib->vtottrib)) {
                $totTrib->appendChild($doc->createElement('vTotTrib', $trib->totaltrib->vtottrib));
            }
            if (isset($trib->totaltrib->pstottrib)) {
                $totTrib->appendChild($doc->createElement('psTotTrib', $trib->totaltrib->pstottrib));
            }
            $el->appendChild($totTrib);
        }

        return $el;
    }
}
