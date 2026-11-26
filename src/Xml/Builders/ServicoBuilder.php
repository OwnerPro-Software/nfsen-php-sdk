<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Xml\Builders;

use DOMDocument;
use DOMElement;
use OwnerPro\Nfsen\Dps\DTO\Serv\AtvEvento;
use OwnerPro\Nfsen\Dps\DTO\Serv\ComExt;
use OwnerPro\Nfsen\Dps\DTO\Serv\CServ;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndExt;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndObra;
use OwnerPro\Nfsen\Dps\DTO\Serv\EndSimples;
use OwnerPro\Nfsen\Dps\DTO\Serv\InfoCompl;
use OwnerPro\Nfsen\Dps\DTO\Serv\Obra;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Enums\Leiaute;

final readonly class ServicoBuilder
{
    use CreatesTextElements;

    public function __construct(
        private Leiaute $leiaute = Leiaute::V101,
    ) {}

    public function build(DOMDocument $doc, Serv $serv): DOMElement
    {
        $el = $doc->createElement('serv');
        $el->appendChild($this->buildLocPrest($doc, $serv));
        $el->appendChild($this->buildCServ($doc, $serv->cServ));

        if ($serv->comExt instanceof ComExt) {
            $el->appendChild($this->buildComExt($doc, $serv->comExt));
        }

        if ($serv->obra instanceof Obra) { // @pest-mutate-ignore InstanceOfToTrue — coverage-guided mutation only runs tests where obra is set
            $el->appendChild($this->buildObra($doc, $serv->obra));
        }

        if ($serv->atvEvento instanceof AtvEvento) {
            $el->appendChild($this->buildAtvEvento($doc, $serv->atvEvento));
        }

        if ($serv->infoCompl instanceof InfoCompl) { // @pest-mutate-ignore InstanceOfToTrue — coverage-guided mutation only runs tests where infoCompl is set
            $el->appendChild($this->buildInfoCompl($doc, $serv->infoCompl));
        }

        return $el;
    }

    private function buildLocPrest(DOMDocument $doc, Serv $serv): DOMElement
    {
        $el = $doc->createElement('locPrest');
        if ($serv->cLocPrestacao !== null) {
            $el->appendChild($this->text($doc, 'cLocPrestacao', $serv->cLocPrestacao));
        } elseif ($serv->cPaisPrestacao !== null) {
            $el->appendChild($this->text($doc, 'cPaisPrestacao', $serv->cPaisPrestacao));
        }

        return $el;
    }

    private function buildCServ(DOMDocument $doc, CServ $cServ): DOMElement
    {
        $el = $doc->createElement('cServ');
        $el->appendChild($this->text($doc, 'cTribNac', $cServ->cTribNac));
        $this->appendOptional($doc, $el, 'cTribMun', $cServ->cTribMun);
        if ($this->leiaute === Leiaute::V101) {
            $el->appendChild($this->text($doc, 'xDescServ', $cServ->xDescServ));
        }

        $this->appendOptional($doc, $el, 'cNBS', $cServ->cNBS);
        $this->appendOptional($doc, $el, 'cIntContrib', $cServ->cIntContrib);
        if ($this->leiaute === Leiaute::Nt009) {
            $el->appendChild($this->text($doc, 'xDescServ', $cServ->xDescServ));
        }

        return $el;
    }

    private function buildComExt(DOMDocument $doc, ComExt $comExt): DOMElement
    {
        $el = $doc->createElement('comExt');
        $el->appendChild($this->text($doc, 'mdPrestacao', $comExt->mdPrestacao->value));
        $el->appendChild($this->text($doc, 'vincPrest', $comExt->vincPrest->value));
        $el->appendChild($this->text($doc, 'tpMoeda', $comExt->tpMoeda));
        $el->appendChild($this->text($doc, 'vServMoeda', $comExt->vServMoeda));
        $el->appendChild($this->text($doc, 'mecAFComexP', $comExt->mecAFComexP->value));
        $el->appendChild($this->text($doc, 'mecAFComexT', $comExt->mecAFComexT->value));
        $el->appendChild($this->text($doc, 'movTempBens', $comExt->movTempBens->value));
        $this->appendOptional($doc, $el, 'nDI', $comExt->nDI);
        $this->appendOptional($doc, $el, 'nRE', $comExt->nRE);
        $el->appendChild($this->text($doc, 'mdic', $comExt->mdic->value));

        return $el;
    }

    private function buildObra(DOMDocument $doc, Obra $obra): DOMElement
    {
        $el = $doc->createElement('obra');
        $this->appendOptional($doc, $el, 'inscImobFisc', $obra->inscImobFisc);

        if ($obra->cObra !== null) {
            $el->appendChild($this->text($doc, 'cObra', $obra->cObra));
        } elseif ($obra->cCIB !== null) {
            $el->appendChild($this->text($doc, 'cCIB', $obra->cCIB));
        } elseif ($obra->end instanceof EndObra) { // @pest-mutate-ignore InstanceOfToTrue — last branch in elseif, value is already EndObra when reached
            $el->appendChild($this->buildSimpleAddress($doc, $obra->end));
        }

        return $el;
    }

    private function buildAtvEvento(DOMDocument $doc, AtvEvento $atvEvento): DOMElement
    {
        $el = $doc->createElement('atvEvento');
        $el->appendChild($this->text($doc, 'xNome', $atvEvento->xNome));
        $el->appendChild($this->text($doc, 'dtIni', $atvEvento->dtIni));
        $el->appendChild($this->text($doc, 'dtFim', $atvEvento->dtFim));

        if ($atvEvento->idAtvEvt !== null) {
            $el->appendChild($this->text($doc, 'idAtvEvt', $atvEvento->idAtvEvt));
        } elseif ($atvEvento->end instanceof EndSimples) { // @pest-mutate-ignore InstanceOfToTrue — last branch in elseif, value is already EndSimples when reached
            $el->appendChild($this->buildSimpleAddress($doc, $atvEvento->end));
        }

        return $el;
    }

    private function buildSimpleAddress(DOMDocument $doc, EndObra|EndSimples $end): DOMElement
    {
        $el = $doc->createElement('end');
        if ($end->CEP !== null) {
            $el->appendChild($this->text($doc, 'CEP', $end->CEP));
        } elseif ($end->endExt instanceof EndExt) { // @pest-mutate-ignore InstanceOfToTrue — last branch in elseif, value is already the expected type when reached
            $endExt = $doc->createElement('endExt');
            $endExt->appendChild($this->text($doc, 'cEndPost', $end->endExt->cEndPost));
            $endExt->appendChild($this->text($doc, 'xCidade', $end->endExt->xCidade));
            $endExt->appendChild($this->text($doc, 'xEstProvReg', $end->endExt->xEstProvReg));
            $el->appendChild($endExt);
        }

        $el->appendChild($this->text($doc, 'xLgr', $end->xLgr));
        $el->appendChild($this->text($doc, 'nro', $end->nro));
        $this->appendOptional($doc, $el, 'xCpl', $end->xCpl);
        $el->appendChild($this->text($doc, 'xBairro', $end->xBairro));

        return $el;
    }

    private function buildInfoCompl(DOMDocument $doc, InfoCompl $infoCompl): DOMElement
    {
        $el = $doc->createElement('infoCompl');
        $this->appendOptional($doc, $el, 'idDocTec', $infoCompl->idDocTec);
        $this->appendOptional($doc, $el, 'docRef', $infoCompl->docRef);
        $this->appendOptional($doc, $el, 'xPed', $infoCompl->xPed);

        if ($infoCompl->xItemPed !== null) {
            $gItemPed = $doc->createElement('gItemPed');
            foreach ($infoCompl->xItemPed as $item) {
                $gItemPed->appendChild($this->text($doc, 'xItemPed', $item));
            }

            $el->appendChild($gItemPed);
        }

        $this->appendOptional($doc, $el, 'xInfComp', $infoCompl->xInfComp);

        return $el;
    }
}
