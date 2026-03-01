<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Xml\Builders;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\DTOs\Dps\Servico\ComercioExterior;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Obra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;

final class ServicoBuilder
{
    use CreatesTextElements;

    public function build(DOMDocument $doc, Servico $serv): DOMElement
    {
        $el = $doc->createElement('serv');

        // locPrest
        $locPrest = $doc->createElement('locPrest');
        if ($serv->cLocPrestacao !== null) {
            $locPrest->appendChild($this->text($doc, 'cLocPrestacao', $serv->cLocPrestacao));
        } elseif ($serv->cPaisPrestacao !== null) {
            $locPrest->appendChild($this->text($doc, 'cPaisPrestacao', $serv->cPaisPrestacao));
        }

        $el->appendChild($locPrest);

        // cServ
        $cServ = $doc->createElement('cServ');
        $cServ->appendChild($this->text($doc, 'cTribNac', $serv->cServ->cTribNac));
        if ($serv->cServ->cTribMun !== null) {
            $cServ->appendChild($this->text($doc, 'cTribMun', $serv->cServ->cTribMun));
        }

        $cServ->appendChild($this->text($doc, 'xDescServ', $serv->cServ->xDescServ));
        $cServ->appendChild($this->text($doc, 'cNBS', $serv->cServ->cNBS));
        if ($serv->cServ->cIntContrib !== null) {
            $cServ->appendChild($this->text($doc, 'cIntContrib', $serv->cServ->cIntContrib));
        }

        $el->appendChild($cServ);

        // comExt (optional)
        if ($serv->comExt instanceof ComercioExterior) {
            $comExt = $doc->createElement('comExt');
            $comExt->appendChild($this->text($doc, 'mdPrestacao', $serv->comExt->mdPrestacao->value));
            $comExt->appendChild($this->text($doc, 'vincPrest', $serv->comExt->vincPrest->value));
            $comExt->appendChild($this->text($doc, 'tpMoeda', $serv->comExt->tpMoeda));
            $comExt->appendChild($this->text($doc, 'vServMoeda', $serv->comExt->vServMoeda));
            $comExt->appendChild($this->text($doc, 'mecAFComexP', $serv->comExt->mecAFComexP->value));
            $comExt->appendChild($this->text($doc, 'mecAFComexT', $serv->comExt->mecAFComexT->value));
            $comExt->appendChild($this->text($doc, 'movTempBens', $serv->comExt->movTempBens->value));
            if ($serv->comExt->nDI !== null) {
                $comExt->appendChild($this->text($doc, 'nDI', $serv->comExt->nDI));
            }

            if ($serv->comExt->nRE !== null) {
                $comExt->appendChild($this->text($doc, 'nRE', $serv->comExt->nRE));
            }

            $comExt->appendChild($this->text($doc, 'mdic', $serv->comExt->mdic->value));
            $el->appendChild($comExt);
        }

        // obra (optional)
        if ($serv->obra instanceof Obra) {
            $obra = $doc->createElement('obra');
            if ($serv->obra->inscImobFisc !== null) {
                $obra->appendChild($this->text($doc, 'inscImobFisc', $serv->obra->inscImobFisc));
            }

            if ($serv->obra->cObra !== null) {
                $obra->appendChild($this->text($doc, 'cObra', $serv->obra->cObra));
            } elseif ($serv->obra->cCIB !== null) {
                $obra->appendChild($this->text($doc, 'cCIB', $serv->obra->cCIB));
            } elseif ($serv->obra->end instanceof EnderecoObra) {
                $endObra = $doc->createElement('end');
                if ($serv->obra->end->CEP !== null) {
                    $endObra->appendChild($this->text($doc, 'CEP', $serv->obra->end->CEP));
                } elseif ($serv->obra->end->endExt instanceof EnderecoExteriorObra) {
                    $endExt = $doc->createElement('endExt');
                    $endExt->appendChild($this->text($doc, 'cEndPost', $serv->obra->end->endExt->cEndPost));
                    $endExt->appendChild($this->text($doc, 'xCidade', $serv->obra->end->endExt->xCidade));
                    $endExt->appendChild($this->text($doc, 'xEstProvReg', $serv->obra->end->endExt->xEstProvReg));
                    $endObra->appendChild($endExt);
                }

                $endObra->appendChild($this->text($doc, 'xLgr', $serv->obra->end->xLgr));
                $endObra->appendChild($this->text($doc, 'nro', $serv->obra->end->nro));
                if ($serv->obra->end->xCpl !== null) {
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
