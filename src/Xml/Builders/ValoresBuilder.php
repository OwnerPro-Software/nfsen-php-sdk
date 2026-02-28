<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use stdClass;

final class ValoresBuilder
{
    public function build(DOMDocument $doc, stdClass $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        // vServPrest (obrigatório)
        if (isset($valores->vservprest)) {
            $vServ = $doc->createElement('vServPrest');
            if (isset($valores->vservprest->vreceb)) {
                $vServ->appendChild($this->text($doc, 'vReceb', $valores->vservprest->vreceb));
            }

            if (isset($valores->vservprest->vserv)) {
                $vServ->appendChild($this->text($doc, 'vServ', $valores->vservprest->vserv));
            }

            $el->appendChild($vServ);
        }

        // vDescCondIncond (opcional)
        if (isset($valores->vdesccondincond)) {
            $vDesc = $doc->createElement('vDescCondIncond');
            if (isset($valores->vdesccondincond->vdescincond)) {
                $vDesc->appendChild($this->text($doc, 'vDescIncond', $valores->vdesccondincond->vdescincond));
            }

            if (isset($valores->vdesccondincond->vdesccond)) {
                $vDesc->appendChild($this->text($doc, 'vDescCond', $valores->vdesccondincond->vdesccond));
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
            $tribMun->appendChild($this->text($doc, 'tribISSQN', $trib->tribmun->tribissqn));
            if (isset($trib->tribmun->cpaisresult)) {
                $tribMun->appendChild($this->text($doc, 'cPaisResult', $trib->tribmun->cpaisresult));
            }

            if (isset($trib->tribmun->tpimunidade)) {
                $tribMun->appendChild($this->text($doc, 'tpImunidade', $trib->tribmun->tpimunidade));
            }

            if (isset($trib->tribmun->exigsusp)) {
                $exigSusp = $doc->createElement('exigSusp');
                if (isset($trib->tribmun->exigsusp->nmotsusp)) {
                    $exigSusp->appendChild($this->text($doc, 'nMotSusp', $trib->tribmun->exigsusp->nmotsusp));
                }

                if (isset($trib->tribmun->exigsusp->nprocesso)) {
                    $exigSusp->appendChild($this->text($doc, 'nProcesso', $trib->tribmun->exigsusp->nprocesso));
                }

                $tribMun->appendChild($exigSusp);
            }

            if (isset($trib->tribmun->bm)) {
                $bm = $doc->createElement('BM');
                $bm->appendChild($this->text($doc, 'nBM', $trib->tribmun->bm->nbm));
                if (isset($trib->tribmun->bm->vredbcbm)) {
                    $bm->appendChild($this->text($doc, 'vRedBCBM', $trib->tribmun->bm->vredbcbm));
                }

                if (isset($trib->tribmun->bm->predbcbm)) {
                    $bm->appendChild($this->text($doc, 'pRedBCBM', $trib->tribmun->bm->predbcbm));
                }

                $tribMun->appendChild($bm);
            }

            $tribMun->appendChild($this->text($doc, 'tpRetISSQN', $trib->tribmun->tpretissqn));
            if (isset($trib->tribmun->paliq)) {
                $tribMun->appendChild($this->text($doc, 'pAliq', $trib->tribmun->paliq));
            }

            $el->appendChild($tribMun);
        }

        // tribFed (opcional)
        if (isset($trib->tribfed)) {
            $tribFed = $doc->createElement('tribFed');
            if (isset($trib->tribfed->piscofins)) {
                $pisCofins = $doc->createElement('pisCofins');
                if (isset($trib->tribfed->piscofins->cst)) {
                    $pisCofins->appendChild($this->text($doc, 'CST', $trib->tribfed->piscofins->cst));
                }

                if (isset($trib->tribfed->piscofins->vbcpiscofins)) {
                    $pisCofins->appendChild($this->text($doc, 'vBCPisCofins', $trib->tribfed->piscofins->vbcpiscofins));
                }

                if (isset($trib->tribfed->piscofins->paliqpis)) {
                    $pisCofins->appendChild($this->text($doc, 'pAliqPis', $trib->tribfed->piscofins->paliqpis));
                }

                if (isset($trib->tribfed->piscofins->paliqcofins)) {
                    $pisCofins->appendChild($this->text($doc, 'pAliqCofins', $trib->tribfed->piscofins->paliqcofins));
                }

                if (isset($trib->tribfed->piscofins->vpis)) {
                    $pisCofins->appendChild($this->text($doc, 'vPis', $trib->tribfed->piscofins->vpis));
                }

                if (isset($trib->tribfed->piscofins->vcofins)) {
                    $pisCofins->appendChild($this->text($doc, 'vCofins', $trib->tribfed->piscofins->vcofins));
                }

                if (isset($trib->tribfed->piscofins->tpretpiscofins)) {
                    $pisCofins->appendChild($this->text($doc, 'tpRetPisCofins', $trib->tribfed->piscofins->tpretpiscofins));
                }

                $tribFed->appendChild($pisCofins);
            }

            if (isset($trib->tribfed->vretcp)) {
                $tribFed->appendChild($this->text($doc, 'vRetCP', $trib->tribfed->vretcp));
            }

            if (isset($trib->tribfed->vretirrf)) {
                $tribFed->appendChild($this->text($doc, 'vRetIRRF', $trib->tribfed->vretirrf));
            }

            if (isset($trib->tribfed->vretcsll)) {
                $tribFed->appendChild($this->text($doc, 'vRetCSLL', $trib->tribfed->vretcsll));
            }

            $el->appendChild($tribFed);
        }

        // totTrib (obrigatório — choice)
        if (isset($trib->totaltrib)) {
            $totTrib = $doc->createElement('totTrib');
            if (isset($trib->totaltrib->vtottrib)) {
                $vTotTrib = $doc->createElement('vTotTrib');
                $vTotTrib->appendChild($this->text($doc, 'vTotTribFed', $trib->totaltrib->vtottrib->vtottribfed));
                $vTotTrib->appendChild($this->text($doc, 'vTotTribEst', $trib->totaltrib->vtottrib->vtottribest));
                $vTotTrib->appendChild($this->text($doc, 'vTotTribMun', $trib->totaltrib->vtottrib->vtottribmun));
                $totTrib->appendChild($vTotTrib);
            } elseif (isset($trib->totaltrib->ptottrib)) {
                $pTotTrib = $doc->createElement('pTotTrib');
                $pTotTrib->appendChild($this->text($doc, 'pTotTribFed', $trib->totaltrib->ptottrib->ptottribfed));
                $pTotTrib->appendChild($this->text($doc, 'pTotTribEst', $trib->totaltrib->ptottrib->ptottribest));
                $pTotTrib->appendChild($this->text($doc, 'pTotTribMun', $trib->totaltrib->ptottrib->ptottribmun));
                $totTrib->appendChild($pTotTrib);
            } elseif (isset($trib->totaltrib->indtottrib)) {
                $totTrib->appendChild($this->text($doc, 'indTotTrib', $trib->totaltrib->indtottrib));
            } elseif (isset($trib->totaltrib->ptottribsn)) {
                $totTrib->appendChild($this->text($doc, 'pTotTribSN', $trib->totaltrib->ptottribsn));
            }

            $el->appendChild($totTrib);
        }

        return $el;
    }

    private function text(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
