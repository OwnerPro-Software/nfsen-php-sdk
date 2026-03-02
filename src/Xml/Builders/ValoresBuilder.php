<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\DTOs\Dps\Tomador\Tomador;
use Pulsar\NfseNacional\DTOs\Dps\Valores\BeneficioMunicipal;
use Pulsar\NfseNacional\DTOs\Dps\Valores\DescontoCondIncond;
use Pulsar\NfseNacional\DTOs\Dps\Valores\DocDedRed;
use Pulsar\NfseNacional\DTOs\Dps\Valores\DocNFNFS;
use Pulsar\NfseNacional\DTOs\Dps\Valores\DocOutNFSe;
use Pulsar\NfseNacional\DTOs\Dps\Valores\ExigibilidadeSuspensa;
use Pulsar\NfseNacional\DTOs\Dps\Valores\InfoDedRed;
use Pulsar\NfseNacional\DTOs\Dps\Valores\PisCofins;
use Pulsar\NfseNacional\DTOs\Dps\Valores\TotTribPercentual;
use Pulsar\NfseNacional\DTOs\Dps\Valores\TotTribValor;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Tributacao;
use Pulsar\NfseNacional\DTOs\Dps\Valores\TributacaoFederal;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Valores;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoImunidadeISSQN;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoRetPisCofins;

final class ValoresBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, Valores $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        // vServPrest (obrigatório)
        $vServPrest = $doc->createElement('vServPrest');
        if ($valores->vServPrest->vReceb !== null) {
            $vServPrest->appendChild($this->text($doc, 'vReceb', $valores->vServPrest->vReceb));
        }

        $vServPrest->appendChild($this->text($doc, 'vServ', $valores->vServPrest->vServ));
        $el->appendChild($vServPrest);

        // vDescCondIncond (opcional)
        if ($valores->vDescCondIncond instanceof DescontoCondIncond) {
            $vDesc = $doc->createElement('vDescCondIncond');
            if ($valores->vDescCondIncond->vDescIncond !== null) {
                $vDesc->appendChild($this->text($doc, 'vDescIncond', $valores->vDescCondIncond->vDescIncond));
            }

            if ($valores->vDescCondIncond->vDescCond !== null) {
                $vDesc->appendChild($this->text($doc, 'vDescCond', $valores->vDescCondIncond->vDescCond));
            }

            $el->appendChild($vDesc);
        }

        // vDedRed (opcional)
        if ($valores->vDedRed instanceof InfoDedRed) {
            $el->appendChild($this->buildVDedRed($doc, $valores->vDedRed));
        }

        // trib (obrigatório)
        $el->appendChild($this->buildTrib($doc, $valores->trib));

        return $el;
    }

    private function buildVDedRed(DOMDocument $doc, InfoDedRed $info): DOMElement
    {
        $el = $doc->createElement('vDedRed');

        if ($info->pDR !== null) {
            $el->appendChild($this->text($doc, 'pDR', $info->pDR));
        } elseif ($info->vDR !== null) {
            $el->appendChild($this->text($doc, 'vDR', $info->vDR));
        } elseif ($info->documentos !== null) {
            $documentos = $doc->createElement('documentos');
            foreach ($info->documentos as $docDedRed) {
                $documentos->appendChild($this->buildDocDedRed($doc, $docDedRed));
            }

            $el->appendChild($documentos);
        }

        return $el;
    }

    private function buildDocDedRed(DOMDocument $doc, DocDedRed $docDedRed): DOMElement
    {
        $el = $doc->createElement('docDedRed');

        if ($docDedRed->chNFSe !== null) {
            $el->appendChild($this->text($doc, 'chNFSe', $docDedRed->chNFSe));
        } elseif ($docDedRed->chNFe !== null) {
            $el->appendChild($this->text($doc, 'chNFe', $docDedRed->chNFe));
        } elseif ($docDedRed->NFSeMun instanceof DocOutNFSe) {
            $nfseMun = $doc->createElement('NFSeMun');
            $nfseMun->appendChild($this->text($doc, 'cMunNFSeMun', $docDedRed->NFSeMun->cMunNFSeMun));
            $nfseMun->appendChild($this->text($doc, 'nNFSeMun', $docDedRed->NFSeMun->nNFSeMun));
            $nfseMun->appendChild($this->text($doc, 'cVerifNFSeMun', $docDedRed->NFSeMun->cVerifNFSeMun));
            $el->appendChild($nfseMun);
        } elseif ($docDedRed->NFNFS instanceof DocNFNFS) {
            $nfnfs = $doc->createElement('NFNFS');
            $nfnfs->appendChild($this->text($doc, 'nNFS', $docDedRed->NFNFS->nNFS));
            $nfnfs->appendChild($this->text($doc, 'modNFS', $docDedRed->NFNFS->modNFS));
            $nfnfs->appendChild($this->text($doc, 'serieNFS', $docDedRed->NFNFS->serieNFS));
            $el->appendChild($nfnfs);
        } elseif ($docDedRed->nDocFisc !== null) {
            $el->appendChild($this->text($doc, 'nDocFisc', $docDedRed->nDocFisc));
        } elseif ($docDedRed->nDoc !== null) {
            $el->appendChild($this->text($doc, 'nDoc', $docDedRed->nDoc));
        }

        $el->appendChild($this->text($doc, 'tpDedRed', $docDedRed->tpDedRed->value));
        if ($docDedRed->xDescOutDed !== null) {
            $el->appendChild($this->text($doc, 'xDescOutDed', $docDedRed->xDescOutDed));
        }

        $el->appendChild($this->text($doc, 'dtEmiDoc', $docDedRed->dtEmiDoc));
        $el->appendChild($this->text($doc, 'vDedutivelRedutivel', $docDedRed->vDedutivelRedutivel));
        $el->appendChild($this->text($doc, 'vDeducaoReducao', $docDedRed->vDeducaoReducao));

        if ($docDedRed->fornec instanceof Tomador) {
            $el->appendChild((new TomadorBuilder)->build($doc, $docDedRed->fornec, 'fornec'));
        }

        return $el;
    }

    private function buildTrib(DOMDocument $doc, Tributacao $trib): DOMElement
    {
        $el = $doc->createElement('trib');

        // tribMun (obrigatório)
        $tribMun = $doc->createElement('tribMun');
        $tribMun->appendChild($this->text($doc, 'tribISSQN', $trib->tribMun->tribISSQN->value));
        if ($trib->tribMun->cPaisResult !== null) {
            $tribMun->appendChild($this->text($doc, 'cPaisResult', $trib->tribMun->cPaisResult));
        }

        if ($trib->tribMun->tpImunidade instanceof TipoImunidadeISSQN) {
            $tribMun->appendChild($this->text($doc, 'tpImunidade', $trib->tribMun->tpImunidade->value));
        }

        if ($trib->tribMun->exigSusp instanceof ExigibilidadeSuspensa) {
            $exigSusp = $doc->createElement('exigSusp');
            $exigSusp->appendChild($this->text($doc, 'tpSusp', $trib->tribMun->exigSusp->tpSusp->value));
            $exigSusp->appendChild($this->text($doc, 'nProcesso', $trib->tribMun->exigSusp->nProcesso));
            $tribMun->appendChild($exigSusp);
        }

        if ($trib->tribMun->BM instanceof BeneficioMunicipal) {
            $bm = $doc->createElement('BM');
            $bm->appendChild($this->text($doc, 'nBM', $trib->tribMun->BM->nBM));
            if ($trib->tribMun->BM->vRedBCBM !== null) {
                $bm->appendChild($this->text($doc, 'vRedBCBM', $trib->tribMun->BM->vRedBCBM));
            } elseif ($trib->tribMun->BM->pRedBCBM !== null) {
                $bm->appendChild($this->text($doc, 'pRedBCBM', $trib->tribMun->BM->pRedBCBM));
            }

            $tribMun->appendChild($bm);
        }

        $tribMun->appendChild($this->text($doc, 'tpRetISSQN', $trib->tribMun->tpRetISSQN->value));
        if ($trib->tribMun->pAliq !== null) {
            $tribMun->appendChild($this->text($doc, 'pAliq', $trib->tribMun->pAliq));
        }

        $el->appendChild($tribMun);

        // tribFed (opcional)
        if ($trib->tribFed instanceof TributacaoFederal) {
            $tribFed = $doc->createElement('tribFed');
            if ($trib->tribFed->piscofins instanceof PisCofins) {
                $piscofins = $doc->createElement('piscofins');
                $piscofins->appendChild($this->text($doc, 'CST', $trib->tribFed->piscofins->CST->value));

                if ($trib->tribFed->piscofins->vBCPisCofins !== null) {
                    $piscofins->appendChild($this->text($doc, 'vBCPisCofins', $trib->tribFed->piscofins->vBCPisCofins));
                }

                if ($trib->tribFed->piscofins->pAliqPis !== null) {
                    $piscofins->appendChild($this->text($doc, 'pAliqPis', $trib->tribFed->piscofins->pAliqPis));
                }

                if ($trib->tribFed->piscofins->pAliqCofins !== null) {
                    $piscofins->appendChild($this->text($doc, 'pAliqCofins', $trib->tribFed->piscofins->pAliqCofins));
                }

                if ($trib->tribFed->piscofins->vPis !== null) {
                    $piscofins->appendChild($this->text($doc, 'vPis', $trib->tribFed->piscofins->vPis));
                }

                if ($trib->tribFed->piscofins->vCofins !== null) {
                    $piscofins->appendChild($this->text($doc, 'vCofins', $trib->tribFed->piscofins->vCofins));
                }

                if ($trib->tribFed->piscofins->tpRetPisCofins instanceof TipoRetPisCofins) {
                    $piscofins->appendChild($this->text($doc, 'tpRetPisCofins', $trib->tribFed->piscofins->tpRetPisCofins->value));
                }

                $tribFed->appendChild($piscofins);
            }

            if ($trib->tribFed->vRetCP !== null) {
                $tribFed->appendChild($this->text($doc, 'vRetCP', $trib->tribFed->vRetCP));
            }

            if ($trib->tribFed->vRetIRRF !== null) {
                $tribFed->appendChild($this->text($doc, 'vRetIRRF', $trib->tribFed->vRetIRRF));
            }

            if ($trib->tribFed->vRetCSLL !== null) {
                $tribFed->appendChild($this->text($doc, 'vRetCSLL', $trib->tribFed->vRetCSLL));
            }

            $el->appendChild($tribFed);
        }

        // totTrib
        $totTrib = $doc->createElement('totTrib');
        if ($trib->vTotTrib instanceof TotTribValor) {
            $vTotTrib = $doc->createElement('vTotTrib');
            $vTotTrib->appendChild($this->text($doc, 'vTotTribFed', $trib->vTotTrib->vTotTribFed));
            $vTotTrib->appendChild($this->text($doc, 'vTotTribEst', $trib->vTotTrib->vTotTribEst));
            $vTotTrib->appendChild($this->text($doc, 'vTotTribMun', $trib->vTotTrib->vTotTribMun));
            $totTrib->appendChild($vTotTrib);
        } elseif ($trib->pTotTrib instanceof TotTribPercentual) {
            $pTotTrib = $doc->createElement('pTotTrib');
            $pTotTrib->appendChild($this->text($doc, 'pTotTribFed', $trib->pTotTrib->pTotTribFed));
            $pTotTrib->appendChild($this->text($doc, 'pTotTribEst', $trib->pTotTrib->pTotTribEst));
            $pTotTrib->appendChild($this->text($doc, 'pTotTribMun', $trib->pTotTrib->pTotTribMun));
            $totTrib->appendChild($pTotTrib);
        } elseif ($trib->indTotTrib !== null) {
            $totTrib->appendChild($this->text($doc, 'indTotTrib', $trib->indTotTrib));
        } elseif ($trib->pTotTribSN !== null) {
            $totTrib->appendChild($this->text($doc, 'pTotTribSN', $trib->pTotTribSN));
        }

        $el->appendChild($totTrib);

        return $el;
    }
}
