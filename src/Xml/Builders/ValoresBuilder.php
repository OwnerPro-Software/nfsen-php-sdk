<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Xml\Builders;

use DOMDocument;
use DOMElement;
use OwnerPro\Nfsen\Dps\DTO\Toma\Toma;
use OwnerPro\Nfsen\Dps\DTO\Valores\BM;
use OwnerPro\Nfsen\Dps\DTO\Valores\DocDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\ExigSusp;
use OwnerPro\Nfsen\Dps\DTO\Valores\NFNFS;
use OwnerPro\Nfsen\Dps\DTO\Valores\NFSeMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Piscofins;
use OwnerPro\Nfsen\Dps\DTO\Valores\PTotTrib;
use OwnerPro\Nfsen\Dps\DTO\Valores\Trib;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribFed;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDedRed;
use OwnerPro\Nfsen\Dps\DTO\Valores\VDescCondIncond;
use OwnerPro\Nfsen\Dps\DTO\Valores\VTotTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpImunidade;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetPisCofins;
use OwnerPro\Nfsen\Enums\Leiaute;

final readonly class ValoresBuilder
{
    use CreatesTextElements;

    public function __construct(
        private Leiaute $leiaute = Leiaute::V101,
    ) {}

    public function build(DOMDocument $doc, Valores $valores): DOMElement
    {
        $el = $doc->createElement('valores');

        $vServPrest = $doc->createElement('vServPrest');
        $this->appendOptional($doc, $vServPrest, 'vReceb', $valores->vServPrest->vReceb);
        $vServPrest->appendChild($this->text($doc, 'vServ', $valores->vServPrest->vServ));
        $el->appendChild($vServPrest);

        if ($valores->vDescCondIncond instanceof VDescCondIncond) { // @pest-mutate-ignore InstanceOfToTrue — coverage-guided mutation only runs tests where vDescCondIncond is set
            $vDesc = $doc->createElement('vDescCondIncond');
            $this->appendOptional($doc, $vDesc, 'vDescIncond', $valores->vDescCondIncond->vDescIncond);
            $this->appendOptional($doc, $vDesc, 'vDescCond', $valores->vDescCondIncond->vDescCond);
            $el->appendChild($vDesc);
        }

        if ($valores->vDedRed instanceof VDedRed) {
            $el->appendChild($this->leiaute === Leiaute::Nt009
                ? $this->buildVAjusteBC($doc, $valores->vDedRed)
                : $this->buildVDedRed($doc, $valores->vDedRed));
        }

        $el->appendChild($this->buildTrib($doc, $valores->trib));

        return $el;
    }

    // NT 009, item 2.3: vDedRed passa a se chamar vAjusteBC; pDR e vDR só mudam de nome.
    private function buildVAjusteBC(DOMDocument $doc, VDedRed $info): DOMElement
    {
        $el = $doc->createElement('vAjusteBC');
        $this->appendOptional($doc, $el, 'pAjusteBCISSQN', $info->pDR);
        $this->appendOptional($doc, $el, 'vAjusteBCISSQN', $info->vDR);

        return $el;
    }

    private function buildVDedRed(DOMDocument $doc, VDedRed $info): DOMElement
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
        } elseif ($docDedRed->NFSeMun instanceof NFSeMun) {
            $nfseMun = $doc->createElement('NFSeMun');
            $nfseMun->appendChild($this->text($doc, 'cMunNFSeMun', $docDedRed->NFSeMun->cMunNFSeMun));
            $nfseMun->appendChild($this->text($doc, 'nNFSeMun', $docDedRed->NFSeMun->nNFSeMun));
            $nfseMun->appendChild($this->text($doc, 'cVerifNFSeMun', $docDedRed->NFSeMun->cVerifNFSeMun));
            $el->appendChild($nfseMun);
        } elseif ($docDedRed->NFNFS instanceof NFNFS) {
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
        $this->appendOptional($doc, $el, 'xDescOutDed', $docDedRed->xDescOutDed);
        $el->appendChild($this->text($doc, 'dtEmiDoc', $docDedRed->dtEmiDoc));
        $el->appendChild($this->text($doc, 'vDedutivelRedutivel', $docDedRed->vDedutivelRedutivel));
        $el->appendChild($this->text($doc, 'vDeducaoReducao', $docDedRed->vDeducaoReducao));

        if ($docDedRed->fornec instanceof Toma) {
            $el->appendChild((new TomadorBuilder)->build($doc, $docDedRed->fornec, 'fornec'));
        }

        return $el;
    }

    private function buildTrib(DOMDocument $doc, Trib $trib): DOMElement
    {
        $el = $doc->createElement('trib');
        $el->appendChild($this->buildTribMun($doc, $trib->tribMun));

        if ($trib->tribFed instanceof TribFed) { // @pest-mutate-ignore InstanceOfToTrue — coverage-guided mutation only runs tests where tribFed is set
            $el->appendChild($this->buildTribFed($doc, $trib->tribFed));
        }

        $el->appendChild($this->buildTotTrib($doc, $trib));

        return $el;
    }

    private function buildTribMun(DOMDocument $doc, TribMun $tribMun): DOMElement
    {
        $el = $doc->createElement('tribMun');
        $el->appendChild($this->text($doc, 'tribISSQN', $tribMun->tribISSQN->value));
        $this->appendOptional($doc, $el, 'cPaisResult', $tribMun->cPaisResult);

        if ($tribMun->tpImunidade instanceof TpImunidade) {
            $el->appendChild($this->text($doc, 'tpImunidade', $tribMun->tpImunidade->value));
        }

        if ($tribMun->exigSusp instanceof ExigSusp) {
            $exigSusp = $doc->createElement('exigSusp');
            $exigSusp->appendChild($this->text($doc, 'tpSusp', $tribMun->exigSusp->tpSusp->value));
            $exigSusp->appendChild($this->text($doc, 'nProcesso', $tribMun->exigSusp->nProcesso));
            $el->appendChild($exigSusp);
        }

        if ($tribMun->BM instanceof BM) {
            $el->appendChild($this->buildBM($doc, $tribMun->BM));
        }

        $el->appendChild($this->text($doc, 'tpRetISSQN', $tribMun->tpRetISSQN->value));
        $this->appendOptional($doc, $el, 'pAliq', $tribMun->pAliq);

        return $el;
    }

    private function buildBM(DOMDocument $doc, BM $bm): DOMElement
    {
        $el = $doc->createElement('BM');
        $el->appendChild($this->text($doc, 'nBM', $bm->nBM));
        if ($bm->vRedBCBM !== null) {
            $el->appendChild($this->text($doc, 'vRedBCBM', $bm->vRedBCBM));
        } elseif ($bm->pRedBCBM !== null) {
            $el->appendChild($this->text($doc, 'pRedBCBM', $bm->pRedBCBM));
        }

        return $el;
    }

    private function buildTribFed(DOMDocument $doc, TribFed $tribFed): DOMElement
    {
        $el = $doc->createElement('tribFed');
        if ($tribFed->piscofins instanceof Piscofins) {
            $el->appendChild($this->buildPiscofins($doc, $tribFed->piscofins));
        }

        $this->appendOptional($doc, $el, 'vRetCP', $tribFed->vRetCP);
        $this->appendOptional($doc, $el, 'vRetIRRF', $tribFed->vRetIRRF);
        $this->appendOptional($doc, $el, 'vRetCSLL', $tribFed->vRetCSLL);

        return $el;
    }

    private function buildPiscofins(DOMDocument $doc, Piscofins $piscofins): DOMElement
    {
        $el = $doc->createElement('piscofins');
        $el->appendChild($this->text($doc, 'CST', $piscofins->CST->value));
        $this->appendOptional($doc, $el, 'vBCPisCofins', $piscofins->vBCPisCofins);
        $this->appendOptional($doc, $el, 'pAliqPis', $piscofins->pAliqPis);
        $this->appendOptional($doc, $el, 'pAliqCofins', $piscofins->pAliqCofins);
        $this->appendOptional($doc, $el, 'vPis', $piscofins->vPis);
        $this->appendOptional($doc, $el, 'vCofins', $piscofins->vCofins);

        if ($piscofins->tpRetPisCofins instanceof TpRetPisCofins) {
            $el->appendChild($this->text($doc, 'tpRetPisCofins', $piscofins->tpRetPisCofins->value));
        }

        return $el;
    }

    private function buildTotTrib(DOMDocument $doc, Trib $trib): DOMElement
    {
        $el = $doc->createElement('totTrib');
        if ($trib->vTotTrib instanceof VTotTrib) {
            $vTotTrib = $doc->createElement('vTotTrib');
            $vTotTrib->appendChild($this->text($doc, 'vTotTribFed', $trib->vTotTrib->vTotTribFed));
            $vTotTrib->appendChild($this->text($doc, 'vTotTribEst', $trib->vTotTrib->vTotTribEst));
            $vTotTrib->appendChild($this->text($doc, 'vTotTribMun', $trib->vTotTrib->vTotTribMun));
            $el->appendChild($vTotTrib);
        } elseif ($trib->pTotTrib instanceof PTotTrib) {
            $pTotTrib = $doc->createElement('pTotTrib');
            $pTotTrib->appendChild($this->text($doc, 'pTotTribFed', $trib->pTotTrib->pTotTribFed));
            $pTotTrib->appendChild($this->text($doc, 'pTotTribEst', $trib->pTotTrib->pTotTribEst));
            $pTotTrib->appendChild($this->text($doc, 'pTotTribMun', $trib->pTotTrib->pTotTribMun));
            $el->appendChild($pTotTrib);
        } elseif ($trib->indTotTrib !== null) {
            $el->appendChild($this->text($doc, 'indTotTrib', $trib->indTotTrib));
        } elseif ($trib->pTotTribSN !== null) {
            $el->appendChild($this->text($doc, 'pTotTribSN', $trib->pTotTribSN));
        }

        return $el;
    }
}
