<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

class ValoresBuilder
{
    public function build(DOMDocument $doc, stdClass $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        // vServPrest (obrigatório)
        if (isset($valores->vservprest)) {
            $vServ = $doc->createElement('vServPrest');
            if (isset($valores->vservprest->vreceb)) {
                $vServ->appendChild($doc->createElement('vReceb', $valores->vservprest->vreceb));
            }

            if (isset($valores->vservprest->vserv)) {
                $vServ->appendChild($doc->createElement('vServ', $valores->vservprest->vserv));
            }

            $el->appendChild($vServ);
        }

        // vDescCondIncond (opcional)
        if (isset($valores->vdesccondincond)) {
            $vDesc = $doc->createElement('vDescCondIncond');
            if (isset($valores->vdesccondincond->vdescincond)) {
                $vDesc->appendChild($doc->createElement('vDescIncond', $valores->vdesccondincond->vdescincond));
            }

            if (isset($valores->vdesccondincond->vdesccond)) {
                $vDesc->appendChild($doc->createElement('vDescCond', $valores->vdesccondincond->vdesccond));
            }

            $el->appendChild($vDesc);
        }

        // trib (obrigatório)
        if (isset($valores->trib)) {
            $el->appendChild($this->buildTrib($doc, $valores->trib));
        }

        return $el;
    }

    private function buildTrib(DOMDocument $doc, stdClass $trib): DOMElement
    {
        $el = $doc->createElement('trib');

        // tribMun (obrigatório)
        if (isset($trib->tribmun)) {
            $tribMun = $doc->createElement('tribMun');
            $tribMun->appendChild($doc->createElement('tribISSQN', $trib->tribmun->tribissqn));
            if (isset($trib->tribmun->cpaisresult)) {
                $tribMun->appendChild($doc->createElement('cPaisResult', $trib->tribmun->cpaisresult));
            }

            if (isset($trib->tribmun->tpimunidade)) {
                $tribMun->appendChild($doc->createElement('tpImunidade', $trib->tribmun->tpimunidade));
            }

            if (isset($trib->tribmun->exigsusp)) {
                $exigSusp = $doc->createElement('exigSusp');
                if (isset($trib->tribmun->exigsusp->nmotsusp)) {
                    $exigSusp->appendChild($doc->createElement('nMotSusp', $trib->tribmun->exigsusp->nmotsusp));
                }

                if (isset($trib->tribmun->exigsusp->nprocesso)) {
                    $exigSusp->appendChild($doc->createElement('nProcesso', $trib->tribmun->exigsusp->nprocesso));
                }

                $tribMun->appendChild($exigSusp);
            }

            if (isset($trib->tribmun->bm)) {
                $bm = $doc->createElement('BM');
                $bm->appendChild($doc->createElement('nBM', $trib->tribmun->bm->nbm));
                if (isset($trib->tribmun->bm->vredbcbm)) {
                    $bm->appendChild($doc->createElement('vRedBCBM', $trib->tribmun->bm->vredbcbm));
                }

                if (isset($trib->tribmun->bm->predbcbm)) {
                    $bm->appendChild($doc->createElement('pRedBCBM', $trib->tribmun->bm->predbcbm));
                }

                $tribMun->appendChild($bm);
            }

            $tribMun->appendChild($doc->createElement('tpRetISSQN', $trib->tribmun->tpretissqn));
            if (isset($trib->tribmun->paliq)) {
                $tribMun->appendChild($doc->createElement('pAliq', $trib->tribmun->paliq));
            }

            $el->appendChild($tribMun);
        }

        // tribFed (opcional)
        if (isset($trib->tribfed)) {
            $tribFed = $doc->createElement('tribFed');
            if (isset($trib->tribfed->piscofins)) {
                $pisCofins = $doc->createElement('pisCofins');
                if (isset($trib->tribfed->piscofins->cst)) {
                    $pisCofins->appendChild($doc->createElement('CST', $trib->tribfed->piscofins->cst));
                }

                if (isset($trib->tribfed->piscofins->vbcpiscofins)) {
                    $pisCofins->appendChild($doc->createElement('vBCPisCofins', $trib->tribfed->piscofins->vbcpiscofins));
                }

                if (isset($trib->tribfed->piscofins->paliqpis)) {
                    $pisCofins->appendChild($doc->createElement('pAliqPis', $trib->tribfed->piscofins->paliqpis));
                }

                if (isset($trib->tribfed->piscofins->paliqcofins)) {
                    $pisCofins->appendChild($doc->createElement('pAliqCofins', $trib->tribfed->piscofins->paliqcofins));
                }

                if (isset($trib->tribfed->piscofins->vpis)) {
                    $pisCofins->appendChild($doc->createElement('vPis', $trib->tribfed->piscofins->vpis));
                }

                if (isset($trib->tribfed->piscofins->vcofins)) {
                    $pisCofins->appendChild($doc->createElement('vCofins', $trib->tribfed->piscofins->vcofins));
                }

                if (isset($trib->tribfed->piscofins->tpretpiscofins)) {
                    $pisCofins->appendChild($doc->createElement('tpRetPisCofins', $trib->tribfed->piscofins->tpretpiscofins));
                }

                $tribFed->appendChild($pisCofins);
            }

            if (isset($trib->tribfed->vretcp)) {
                $tribFed->appendChild($doc->createElement('vRetCP', $trib->tribfed->vretcp));
            }

            if (isset($trib->tribfed->vretirrf)) {
                $tribFed->appendChild($doc->createElement('vRetIRRF', $trib->tribfed->vretirrf));
            }

            if (isset($trib->tribfed->vretcsll)) {
                $tribFed->appendChild($doc->createElement('vRetCSLL', $trib->tribfed->vretcsll));
            }

            $el->appendChild($tribFed);
        }

        // totTrib (obrigatório — choice)
        if (isset($trib->totaltrib)) {
            $totTrib = $doc->createElement('totTrib');
            if (isset($trib->totaltrib->vtottrib)) {
                $vTotTrib = $doc->createElement('vTotTrib');
                $vTotTrib->appendChild($doc->createElement('vTotTribFed', $trib->totaltrib->vtottrib->vtottribfed));
                $vTotTrib->appendChild($doc->createElement('vTotTribEst', $trib->totaltrib->vtottrib->vtottribest));
                $vTotTrib->appendChild($doc->createElement('vTotTribMun', $trib->totaltrib->vtottrib->vtottribmun));
                $totTrib->appendChild($vTotTrib);
            } elseif (isset($trib->totaltrib->ptottrib)) {
                $pTotTrib = $doc->createElement('pTotTrib');
                $pTotTrib->appendChild($doc->createElement('pTotTribFed', $trib->totaltrib->ptottrib->ptottribfed));
                $pTotTrib->appendChild($doc->createElement('pTotTribEst', $trib->totaltrib->ptottrib->ptottribest));
                $pTotTrib->appendChild($doc->createElement('pTotTribMun', $trib->totaltrib->ptottrib->ptottribmun));
                $totTrib->appendChild($pTotTrib);
            } elseif (isset($trib->totaltrib->indtottrib)) {
                $totTrib->appendChild($doc->createElement('indTotTrib', $trib->totaltrib->indtottrib));
            } elseif (isset($trib->totaltrib->ptottribsn)) {
                $totTrib->appendChild($doc->createElement('pTotTribSN', $trib->totaltrib->ptottribsn));
            }

            $el->appendChild($totTrib);
        }

        return $el;
    }
}
