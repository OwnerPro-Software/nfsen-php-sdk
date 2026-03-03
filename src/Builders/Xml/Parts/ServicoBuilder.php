<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Builders\Xml\Parts;

use DOMDocument;
use DOMElement;
use Pulsar\NfseNacional\DTOs\Dps\Servico\AtividadeEvento;
use Pulsar\NfseNacional\DTOs\Dps\Servico\ComercioExterior;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoSimples;
use Pulsar\NfseNacional\DTOs\Dps\Servico\ExploracaoRodoviaria;
use Pulsar\NfseNacional\DTOs\Dps\Servico\InfoComplementar;
use Pulsar\NfseNacional\DTOs\Dps\Servico\LocacaoSublocacao;
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

        // lsadppu (optional)
        if ($serv->lsadppu instanceof LocacaoSublocacao) {
            $lsadppu = $doc->createElement('lsadppu');
            $lsadppu->appendChild($this->text($doc, 'categ', $serv->lsadppu->categ->value));
            $lsadppu->appendChild($this->text($doc, 'objeto', $serv->lsadppu->objeto->value));
            $lsadppu->appendChild($this->text($doc, 'extensao', $serv->lsadppu->extensao));
            $lsadppu->appendChild($this->text($doc, 'nPostes', $serv->lsadppu->nPostes));
            $el->appendChild($lsadppu);
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

        // atvEvento (optional)
        if ($serv->atvEvento instanceof AtividadeEvento) {
            $atvEvento = $doc->createElement('atvEvento');
            $atvEvento->appendChild($this->text($doc, 'xNome', $serv->atvEvento->xNome));
            $atvEvento->appendChild($this->text($doc, 'dtIni', $serv->atvEvento->dtIni));
            $atvEvento->appendChild($this->text($doc, 'dtFim', $serv->atvEvento->dtFim));
            if ($serv->atvEvento->idAtvEvt !== null) {
                $atvEvento->appendChild($this->text($doc, 'idAtvEvt', $serv->atvEvento->idAtvEvt));
            } elseif ($serv->atvEvento->end instanceof EnderecoSimples) {
                $endEl = $doc->createElement('end');
                if ($serv->atvEvento->end->CEP !== null) {
                    $endEl->appendChild($this->text($doc, 'CEP', $serv->atvEvento->end->CEP));
                } elseif ($serv->atvEvento->end->endExt instanceof EnderecoExteriorObra) {
                    $endExt = $doc->createElement('endExt');
                    $endExt->appendChild($this->text($doc, 'cEndPost', $serv->atvEvento->end->endExt->cEndPost));
                    $endExt->appendChild($this->text($doc, 'xCidade', $serv->atvEvento->end->endExt->xCidade));
                    $endExt->appendChild($this->text($doc, 'xEstProvReg', $serv->atvEvento->end->endExt->xEstProvReg));
                    $endEl->appendChild($endExt);
                }

                $endEl->appendChild($this->text($doc, 'xLgr', $serv->atvEvento->end->xLgr));
                $endEl->appendChild($this->text($doc, 'nro', $serv->atvEvento->end->nro));
                if ($serv->atvEvento->end->xCpl !== null) {
                    $endEl->appendChild($this->text($doc, 'xCpl', $serv->atvEvento->end->xCpl));
                }

                $endEl->appendChild($this->text($doc, 'xBairro', $serv->atvEvento->end->xBairro));
                $atvEvento->appendChild($endEl);
            }

            $el->appendChild($atvEvento);
        }

        // explRod (optional)
        if ($serv->explRod instanceof ExploracaoRodoviaria) {
            $explRod = $doc->createElement('explRod');
            $explRod->appendChild($this->text($doc, 'categVeic', $serv->explRod->categVeic->value));
            $explRod->appendChild($this->text($doc, 'nEixos', $serv->explRod->nEixos));
            $explRod->appendChild($this->text($doc, 'rodagem', $serv->explRod->rodagem->value));
            $explRod->appendChild($this->text($doc, 'sentido', $serv->explRod->sentido));
            $explRod->appendChild($this->text($doc, 'placa', $serv->explRod->placa));
            $explRod->appendChild($this->text($doc, 'codAcessoPed', $serv->explRod->codAcessoPed));
            $explRod->appendChild($this->text($doc, 'codContrato', $serv->explRod->codContrato));
            $el->appendChild($explRod);
        }

        // infoCompl (optional)
        if ($serv->infoCompl instanceof InfoComplementar) {
            $infoCompl = $doc->createElement('infoCompl');
            if ($serv->infoCompl->idDocTec !== null) {
                $infoCompl->appendChild($this->text($doc, 'idDocTec', $serv->infoCompl->idDocTec));
            }

            if ($serv->infoCompl->docRef !== null) {
                $infoCompl->appendChild($this->text($doc, 'docRef', $serv->infoCompl->docRef));
            }

            if ($serv->infoCompl->xPed !== null) {
                $infoCompl->appendChild($this->text($doc, 'xPed', $serv->infoCompl->xPed));
            }

            if ($serv->infoCompl->xItemPed !== null) {
                $gItemPed = $doc->createElement('gItemPed');
                foreach ($serv->infoCompl->xItemPed as $item) {
                    $gItemPed->appendChild($this->text($doc, 'xItemPed', $item));
                }

                $infoCompl->appendChild($gItemPed);
            }

            if ($serv->infoCompl->xInfComp !== null) {
                $infoCompl->appendChild($this->text($doc, 'xInfComp', $serv->infoCompl->xInfComp));
            }

            $el->appendChild($infoCompl);
        }

        return $el;
    }
}
