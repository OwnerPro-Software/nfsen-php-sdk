<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use stdClass;

final class ValoresBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, stdClass $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        // vServPrest (obrigatório)
        $vServPrest = $doc->createElement('vServPrest');
        if (isset($valores->vServPrest->vReceb)) {
            $vServPrest->appendChild($this->text($doc, 'vReceb', $valores->vServPrest->vReceb));
        }

        $vServPrest->appendChild($this->text($doc, 'vServ', $valores->vServPrest->vServ));
        $el->appendChild($vServPrest);

        // vDescCondIncond (opcional)
        if (isset($valores->vDescCondIncond)) {
            $vDesc = $doc->createElement('vDescCondIncond');
            if (isset($valores->vDescCondIncond->vDescIncond)) {
                $vDesc->appendChild($this->text($doc, 'vDescIncond', $valores->vDescCondIncond->vDescIncond));
            }

            if (isset($valores->vDescCondIncond->vDescCond)) {
                $vDesc->appendChild($this->text($doc, 'vDescCond', $valores->vDescCondIncond->vDescCond));
            }

            $el->appendChild($vDesc);
        }

        // trib (obrigatório)
        $el->appendChild($this->buildTrib($doc, $valores->trib));

        return $el;
    }

    private function buildTrib(DOMDocument $doc, stdClass $trib): DOMElement
    {
        $el = $doc->createElement('trib');

        // tribMun (obrigatório)
        $tribMun = $doc->createElement('tribMun');
        $tribMun->appendChild($this->text($doc, 'tribISSQN', $trib->tribMun->tribISSQN));
        if (isset($trib->tribMun->cPaisResult)) {
            $tribMun->appendChild($this->text($doc, 'cPaisResult', $trib->tribMun->cPaisResult));
        }

        if (isset($trib->tribMun->tpImunidade)) {
            $tribMun->appendChild($this->text($doc, 'tpImunidade', $trib->tribMun->tpImunidade));
        }

        if (isset($trib->tribMun->exigSusp)) {
            $exigSusp = $doc->createElement('exigSusp');
            $exigSusp->appendChild($this->text($doc, 'tpSusp', $trib->tribMun->exigSusp->tpSusp));
            $exigSusp->appendChild($this->text($doc, 'nProcesso', $trib->tribMun->exigSusp->nProcesso));
            $tribMun->appendChild($exigSusp);
        }

        if (isset($trib->tribMun->BM)) {
            if (isset($trib->tribMun->BM->vRedBCBM) && isset($trib->tribMun->BM->pRedBCBM)) {
                throw new InvalidArgumentException('BM deve ter apenas vRedBCBM ou pRedBCBM, não ambos.');
            }

            $bm = $doc->createElement('BM');
            $bm->appendChild($this->text($doc, 'nBM', $trib->tribMun->BM->nBM));
            if (isset($trib->tribMun->BM->vRedBCBM)) {
                $bm->appendChild($this->text($doc, 'vRedBCBM', $trib->tribMun->BM->vRedBCBM));
            } elseif (isset($trib->tribMun->BM->pRedBCBM)) {
                $bm->appendChild($this->text($doc, 'pRedBCBM', $trib->tribMun->BM->pRedBCBM));
            }

            $tribMun->appendChild($bm);
        }

        $tribMun->appendChild($this->text($doc, 'tpRetISSQN', $trib->tribMun->tpRetISSQN));
        if (isset($trib->tribMun->pAliq)) {
            $tribMun->appendChild($this->text($doc, 'pAliq', $trib->tribMun->pAliq));
        }

        $el->appendChild($tribMun);

        // tribFed (opcional)
        if (isset($trib->tribFed)) {
            $tribFed = $doc->createElement('tribFed');
            if (isset($trib->tribFed->pisCofins)) {
                $pisCofins = $doc->createElement('pisCofins');
                $pisCofins->appendChild($this->text($doc, 'CST', $trib->tribFed->pisCofins->CST));

                if (isset($trib->tribFed->pisCofins->vBCPisCofins)) {
                    $pisCofins->appendChild($this->text($doc, 'vBCPisCofins', $trib->tribFed->pisCofins->vBCPisCofins));
                }

                if (isset($trib->tribFed->pisCofins->pAliqPis)) {
                    $pisCofins->appendChild($this->text($doc, 'pAliqPis', $trib->tribFed->pisCofins->pAliqPis));
                }

                if (isset($trib->tribFed->pisCofins->pAliqCofins)) {
                    $pisCofins->appendChild($this->text($doc, 'pAliqCofins', $trib->tribFed->pisCofins->pAliqCofins));
                }

                if (isset($trib->tribFed->pisCofins->vPis)) {
                    $pisCofins->appendChild($this->text($doc, 'vPis', $trib->tribFed->pisCofins->vPis));
                }

                if (isset($trib->tribFed->pisCofins->vCofins)) {
                    $pisCofins->appendChild($this->text($doc, 'vCofins', $trib->tribFed->pisCofins->vCofins));
                }

                if (isset($trib->tribFed->pisCofins->tpRetPisCofins)) {
                    $pisCofins->appendChild($this->text($doc, 'tpRetPisCofins', $trib->tribFed->pisCofins->tpRetPisCofins));
                }

                $tribFed->appendChild($pisCofins);
            }

            if (isset($trib->tribFed->vRetCP)) {
                $tribFed->appendChild($this->text($doc, 'vRetCP', $trib->tribFed->vRetCP));
            }

            if (isset($trib->tribFed->vRetIRRF)) {
                $tribFed->appendChild($this->text($doc, 'vRetIRRF', $trib->tribFed->vRetIRRF));
            }

            if (isset($trib->tribFed->vRetCSLL)) {
                $tribFed->appendChild($this->text($doc, 'vRetCSLL', $trib->tribFed->vRetCSLL));
            }

            $el->appendChild($tribFed);
        }

        // totTrib (obrigatório e exclusivo — choice)
        $totTribCount = (int) isset($trib->totTrib->vTotTrib) + (int) isset($trib->totTrib->pTotTrib) + (int) isset($trib->totTrib->indTotTrib) + (int) isset($trib->totTrib->pTotTribSN);
        if ($totTribCount > 1) {
            throw new InvalidArgumentException('totTrib deve ter apenas um entre vTotTrib, pTotTrib, indTotTrib ou pTotTribSN.');
        }

        $totTrib = $doc->createElement('totTrib');
        if (isset($trib->totTrib->vTotTrib)) {
            $vTotTrib = $doc->createElement('vTotTrib');
            $vTotTrib->appendChild($this->text($doc, 'vTotTribFed', $trib->totTrib->vTotTrib->vTotTribFed));
            $vTotTrib->appendChild($this->text($doc, 'vTotTribEst', $trib->totTrib->vTotTrib->vTotTribEst));
            $vTotTrib->appendChild($this->text($doc, 'vTotTribMun', $trib->totTrib->vTotTrib->vTotTribMun));
            $totTrib->appendChild($vTotTrib);
        } elseif (isset($trib->totTrib->pTotTrib)) {
            $pTotTrib = $doc->createElement('pTotTrib');
            $pTotTrib->appendChild($this->text($doc, 'pTotTribFed', $trib->totTrib->pTotTrib->pTotTribFed));
            $pTotTrib->appendChild($this->text($doc, 'pTotTribEst', $trib->totTrib->pTotTrib->pTotTribEst));
            $pTotTrib->appendChild($this->text($doc, 'pTotTribMun', $trib->totTrib->pTotTrib->pTotTribMun));
            $totTrib->appendChild($pTotTrib);
        } elseif (isset($trib->totTrib->indTotTrib)) {
            $totTrib->appendChild($this->text($doc, 'indTotTrib', $trib->totTrib->indTotTrib));
        } elseif (isset($trib->totTrib->pTotTribSN)) {
            $totTrib->appendChild($this->text($doc, 'pTotTribSN', $trib->totTrib->pTotTribSN));
        } else {
            throw new InvalidArgumentException('totTrib requer vTotTrib, pTotTrib, indTotTrib ou pTotTribSN.');
        }

        $el->appendChild($totTrib);

        return $el;
    }
}
