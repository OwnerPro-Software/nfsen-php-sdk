<?php

use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoDest;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoIBSCBS;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoImovel;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoReeRepRes;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosDif;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosIBSCBS;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosSitClas;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoTributosTribRegular;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\InfoValoresIBSCBS;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocDFe;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocFiscalOutro;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocFornec;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocOutro;
use Pulsar\NfseNacional\DTOs\Dps\IBSCBS\ListaDocReeRepRes;
use Pulsar\NfseNacional\DTOs\Dps\InfDPS\InfDPS;
use Pulsar\NfseNacional\DTOs\Dps\InfDPS\SubstituicaoData;
use Pulsar\NfseNacional\DTOs\Dps\Prestador\Prestador;
use Pulsar\NfseNacional\DTOs\Dps\Servico\AtividadeEvento;
use Pulsar\NfseNacional\DTOs\Dps\Servico\CodigoServico;
use Pulsar\NfseNacional\DTOs\Dps\Servico\ComercioExterior;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoExteriorObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoObra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\EnderecoSimples;
use Pulsar\NfseNacional\DTOs\Dps\Servico\ExploracaoRodoviaria;
use Pulsar\NfseNacional\DTOs\Dps\Servico\InfoComplementar;
use Pulsar\NfseNacional\DTOs\Dps\Servico\LocacaoSublocacao;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Obra;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;
use Pulsar\NfseNacional\DTOs\Dps\Shared\Endereco;
use Pulsar\NfseNacional\DTOs\Dps\Shared\EnderecoExterior;
use Pulsar\NfseNacional\DTOs\Dps\Shared\EnderecoNacional;
use Pulsar\NfseNacional\DTOs\Dps\Shared\RegTrib;
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
use Pulsar\NfseNacional\DTOs\Dps\Valores\TributacaoMunicipal;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Valores;
use Pulsar\NfseNacional\DTOs\Dps\Valores\ValorServicoPrestado;

// ── InfDPS ──────────────────────────────────────────────────────────────

it('InfDPS::fromArray creates instance from array', function () {
    $dto = InfDPS::fromArray([
        'tpAmb' => '2',
        'dhEmi' => '2026-02-27T10:00:00-03:00',
        'verAplic' => '1.0',
        'serie' => '1',
        'nDPS' => 1,
        'dCompet' => '2026-02-27',
        'tpEmit' => '1',
        'cLocEmi' => '3501608',
    ]);

    expect($dto)->toBeInstanceOf(InfDPS::class)
        ->and($dto->serie)->toBe('1');
});

it('SubstituicaoData::fromArray creates instance from array', function () {
    $dto = SubstituicaoData::fromArray([
        'chSubstda' => '12345678901234567890123456789012345678901234567890',
        'cMotivo' => '01',
        'xMotivo' => 'Motivo teste',
    ]);

    expect($dto)->toBeInstanceOf(SubstituicaoData::class)
        ->and($dto->chSubstda)->toBe('12345678901234567890123456789012345678901234567890');
});

// ── Shared ──────────────────────────────────────────────────────────────

it('RegTrib::fromArray creates instance from array', function () {
    $dto = RegTrib::fromArray([
        'opSimpNac' => '1',
        'regEspTrib' => '0',
    ]);

    expect($dto)->toBeInstanceOf(RegTrib::class);
});

it('EnderecoNacional::fromArray creates instance from array', function () {
    $dto = EnderecoNacional::fromArray([
        'cMun' => '3501608',
        'CEP' => '01310100',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoNacional::class)
        ->and($dto->cMun)->toBe('3501608');
});

it('EnderecoExterior::fromArray creates instance from array', function () {
    $dto = EnderecoExterior::fromArray([
        'cPais' => '01058',
        'cEndPost' => '10001',
        'xCidade' => 'New York',
        'xEstProvReg' => 'NY',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoExterior::class)
        ->and($dto->cPais)->toBe('01058');
});

it('Endereco::fromArray creates instance with endNac', function () {
    $dto = Endereco::fromArray([
        'xLgr' => 'Rua Teste',
        'nro' => '100',
        'xBairro' => 'Centro',
        'endNac' => [
            'cMun' => '3501608',
            'CEP' => '01310100',
        ],
    ]);

    expect($dto)->toBeInstanceOf(Endereco::class)
        ->and($dto->endNac)->toBeInstanceOf(EnderecoNacional::class);
});

// ── Prestador ───────────────────────────────────────────────────────────

it('Prestador::fromArray creates instance from array', function () {
    $dto = Prestador::fromArray([
        'CNPJ' => '12345678000195',
        'regTrib' => [
            'opSimpNac' => '1',
            'regEspTrib' => '0',
        ],
        'xNome' => 'Empresa Teste',
    ]);

    expect($dto)->toBeInstanceOf(Prestador::class)
        ->and($dto->CNPJ)->toBe('12345678000195');
});

// ── Tomador ─────────────────────────────────────────────────────────────

it('Tomador::fromArray creates instance from array', function () {
    $dto = Tomador::fromArray([
        'xNome' => 'Tomador Teste',
        'CNPJ' => '98765432000100',
    ]);

    expect($dto)->toBeInstanceOf(Tomador::class)
        ->and($dto->xNome)->toBe('Tomador Teste');
});

// ── Servico ─────────────────────────────────────────────────────────────

it('CodigoServico::fromArray creates instance from array', function () {
    $dto = CodigoServico::fromArray([
        'cTribNac' => '010101',
        'xDescServ' => 'Serviço de Teste',
        'cNBS' => '123456789',
    ]);

    expect($dto)->toBeInstanceOf(CodigoServico::class);
});

it('Servico::fromArray creates instance from array', function () {
    $dto = Servico::fromArray([
        'cServ' => [
            'cTribNac' => '010101',
            'xDescServ' => 'Serviço de Teste',
            'cNBS' => '123456789',
        ],
        'cLocPrestacao' => '3501608',
    ]);

    expect($dto)->toBeInstanceOf(Servico::class);
});

it('ComercioExterior::fromArray creates instance from array', function () {
    $dto = ComercioExterior::fromArray([
        'mdPrestacao' => '0',
        'vincPrest' => '0',
        'tpMoeda' => 'USD',
        'vServMoeda' => '100.00',
        'mecAFComexP' => '00',
        'mecAFComexT' => '00',
        'movTempBens' => '0',
        'mdic' => '0',
    ]);

    expect($dto)->toBeInstanceOf(ComercioExterior::class);
});

it('Obra::fromArray creates instance from array', function () {
    $dto = Obra::fromArray([
        'cObra' => '12345678901234',
    ]);

    expect($dto)->toBeInstanceOf(Obra::class)
        ->and($dto->cObra)->toBe('12345678901234');
});

it('EnderecoObra::fromArray creates instance from array', function () {
    $dto = EnderecoObra::fromArray([
        'xLgr' => 'Rua Obra',
        'nro' => '200',
        'xBairro' => 'Bairro Obra',
        'CEP' => '01310100',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoObra::class);
});

it('EnderecoExteriorObra::fromArray creates instance from array', function () {
    $dto = EnderecoExteriorObra::fromArray([
        'cEndPost' => '10001',
        'xCidade' => 'New York',
        'xEstProvReg' => 'NY',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoExteriorObra::class);
});

it('EnderecoSimples::fromArray creates instance from array', function () {
    $dto = EnderecoSimples::fromArray([
        'xLgr' => 'Rua Simples',
        'nro' => '300',
        'xBairro' => 'Bairro',
        'CEP' => '01310100',
    ]);

    expect($dto)->toBeInstanceOf(EnderecoSimples::class);
});

it('LocacaoSublocacao::fromArray creates instance from array', function () {
    $dto = LocacaoSublocacao::fromArray([
        'categ' => '1',
        'objeto' => '1',
        'extensao' => '100',
        'nPostes' => '10',
    ]);

    expect($dto)->toBeInstanceOf(LocacaoSublocacao::class);
});

it('AtividadeEvento::fromArray creates instance from array', function () {
    $dto = AtividadeEvento::fromArray([
        'xNome' => 'Evento Teste',
        'dtIni' => '2026-01-01',
        'dtFim' => '2026-01-02',
        'idAtvEvt' => '12345',
    ]);

    expect($dto)->toBeInstanceOf(AtividadeEvento::class);
});

it('ExploracaoRodoviaria::fromArray creates instance from array', function () {
    $dto = ExploracaoRodoviaria::fromArray([
        'categVeic' => '00',
        'nEixos' => '2',
        'rodagem' => '1',
        'sentido' => 'Norte',
        'placa' => 'ABC1234',
        'codAcessoPed' => '123456',
        'codContrato' => '789012',
    ]);

    expect($dto)->toBeInstanceOf(ExploracaoRodoviaria::class);
});

it('InfoComplementar::fromArray creates instance from array', function () {
    $dto = InfoComplementar::fromArray([
        'idDocTec' => 'DOC123',
    ]);

    expect($dto)->toBeInstanceOf(InfoComplementar::class);
});

// ── Valores ─────────────────────────────────────────────────────────────

it('ValorServicoPrestado::fromArray creates instance from array', function () {
    $dto = ValorServicoPrestado::fromArray([
        'vServ' => '100.00',
    ]);

    expect($dto)->toBeInstanceOf(ValorServicoPrestado::class);
});

it('TributacaoMunicipal::fromArray creates instance from array', function () {
    $dto = TributacaoMunicipal::fromArray([
        'tribISSQN' => '1',
        'tpRetISSQN' => '1',
    ]);

    expect($dto)->toBeInstanceOf(TributacaoMunicipal::class);
});

it('TributacaoFederal::fromArray creates instance from array', function () {
    $dto = TributacaoFederal::fromArray([
        'piscofins' => [
            'CST' => '00',
        ],
        'vRetCP' => '10.00',
        'vRetIRRF' => '5.00',
        'vRetCSLL' => '3.00',
    ]);

    expect($dto)->toBeInstanceOf(TributacaoFederal::class)
        ->and($dto->piscofins)->toBeInstanceOf(PisCofins::class);
});

it('PisCofins::fromArray creates instance from array', function () {
    $dto = PisCofins::fromArray([
        'CST' => '00',
    ]);

    expect($dto)->toBeInstanceOf(PisCofins::class);
});

it('ExigibilidadeSuspensa::fromArray creates instance from array', function () {
    $dto = ExigibilidadeSuspensa::fromArray([
        'tpSusp' => '1',
        'nProcesso' => '12345',
    ]);

    expect($dto)->toBeInstanceOf(ExigibilidadeSuspensa::class);
});

it('BeneficioMunicipal::fromArray creates instance from array', function () {
    $dto = BeneficioMunicipal::fromArray([
        'nBM' => 'BM001',
    ]);

    expect($dto)->toBeInstanceOf(BeneficioMunicipal::class);
});

it('TotTribValor::fromArray creates instance from array', function () {
    $dto = TotTribValor::fromArray([
        'vTotTribFed' => '10.00',
        'vTotTribEst' => '5.00',
        'vTotTribMun' => '3.00',
    ]);

    expect($dto)->toBeInstanceOf(TotTribValor::class);
});

it('TotTribPercentual::fromArray creates instance from array', function () {
    $dto = TotTribPercentual::fromArray([
        'pTotTribFed' => '10.00',
        'pTotTribEst' => '5.00',
        'pTotTribMun' => '3.00',
    ]);

    expect($dto)->toBeInstanceOf(TotTribPercentual::class);
});

it('Tributacao::fromArray creates instance from array', function () {
    $dto = Tributacao::fromArray([
        'tribMun' => [
            'tribISSQN' => '1',
            'tpRetISSQN' => '1',
        ],
        'indTotTrib' => '0',
    ]);

    expect($dto)->toBeInstanceOf(Tributacao::class);
});

it('DescontoCondIncond::fromArray creates instance from array', function () {
    $dto = DescontoCondIncond::fromArray([
        'vDescIncond' => '5.00',
    ]);

    expect($dto)->toBeInstanceOf(DescontoCondIncond::class);
});

it('DocOutNFSe::fromArray creates instance from array', function () {
    $dto = DocOutNFSe::fromArray([
        'cMunNFSeMun' => '3501608',
        'nNFSeMun' => '123',
        'cVerifNFSeMun' => 'ABC',
    ]);

    expect($dto)->toBeInstanceOf(DocOutNFSe::class);
});

it('DocNFNFS::fromArray creates instance from array', function () {
    $dto = DocNFNFS::fromArray([
        'nNFS' => '123',
        'modNFS' => '55',
        'serieNFS' => '1',
    ]);

    expect($dto)->toBeInstanceOf(DocNFNFS::class);
});

it('DocDedRed::fromArray creates instance from array', function () {
    $dto = DocDedRed::fromArray([
        'tpDedRed' => '1',
        'dtEmiDoc' => '2026-01-01',
        'vDedutivelRedutivel' => '100.00',
        'vDeducaoReducao' => '50.00',
        'chNFSe' => '12345678901234567890123456789012345678901234567890',
    ]);

    expect($dto)->toBeInstanceOf(DocDedRed::class);
});

it('InfoDedRed::fromArray creates instance with documentos', function () {
    $dto = InfoDedRed::fromArray([
        'documentos' => [
            [
                'tpDedRed' => '1',
                'dtEmiDoc' => '2026-01-01',
                'vDedutivelRedutivel' => '100.00',
                'vDeducaoReducao' => '50.00',
                'chNFSe' => '12345678901234567890123456789012345678901234567890',
            ],
        ],
    ]);

    expect($dto)->toBeInstanceOf(InfoDedRed::class)
        ->and($dto->documentos)->toHaveCount(1);
});

it('Valores::fromArray creates instance from array', function () {
    $dto = Valores::fromArray([
        'vServPrest' => ['vServ' => '100.00'],
        'trib' => [
            'tribMun' => [
                'tribISSQN' => '1',
                'tpRetISSQN' => '1',
            ],
            'indTotTrib' => '0',
        ],
    ]);

    expect($dto)->toBeInstanceOf(Valores::class);
});

// ── IBSCBS ──────────────────────────────────────────────────────────────

it('InfoTributosTribRegular::fromArray creates instance from array', function () {
    $dto = InfoTributosTribRegular::fromArray([
        'CSTReg' => '00',
        'cClassTribReg' => '001',
    ]);

    expect($dto)->toBeInstanceOf(InfoTributosTribRegular::class);
});

it('InfoTributosDif::fromArray creates instance from array', function () {
    $dto = InfoTributosDif::fromArray([
        'pDifUF' => '10.00',
        'pDifMun' => '5.00',
        'pDifCBS' => '3.00',
    ]);

    expect($dto)->toBeInstanceOf(InfoTributosDif::class);
});

it('InfoTributosSitClas::fromArray creates instance from array', function () {
    $dto = InfoTributosSitClas::fromArray([
        'CST' => '00',
        'cClassTrib' => '001',
    ]);

    expect($dto)->toBeInstanceOf(InfoTributosSitClas::class);
});

it('InfoTributosIBSCBS::fromArray creates instance from array', function () {
    $dto = InfoTributosIBSCBS::fromArray([
        'gIBSCBS' => [
            'CST' => '00',
            'cClassTrib' => '001',
        ],
    ]);

    expect($dto)->toBeInstanceOf(InfoTributosIBSCBS::class);
});

it('InfoValoresIBSCBS::fromArray creates instance from array', function () {
    $dto = InfoValoresIBSCBS::fromArray([
        'trib' => [
            'gIBSCBS' => [
                'CST' => '00',
                'cClassTrib' => '001',
            ],
        ],
    ]);

    expect($dto)->toBeInstanceOf(InfoValoresIBSCBS::class);
});

it('InfoDest::fromArray creates instance from array', function () {
    $dto = InfoDest::fromArray([
        'xNome' => 'Destinatario',
        'CNPJ' => '12345678000195',
    ]);

    expect($dto)->toBeInstanceOf(InfoDest::class);
});

it('InfoImovel::fromArray creates instance from array', function () {
    $dto = InfoImovel::fromArray([
        'cCIB' => '12345678901234',
    ]);

    expect($dto)->toBeInstanceOf(InfoImovel::class);
});

it('ListaDocDFe::fromArray creates instance from array', function () {
    $dto = ListaDocDFe::fromArray([
        'tipoChaveDFe' => '1',
        'chaveDFe' => '12345678901234567890123456789012345678901234',
    ]);

    expect($dto)->toBeInstanceOf(ListaDocDFe::class);
});

it('ListaDocFiscalOutro::fromArray creates instance from array', function () {
    $dto = ListaDocFiscalOutro::fromArray([
        'cMunDocFiscal' => '3501608',
        'nDocFiscal' => '123',
        'xDocFiscal' => 'Doc Fiscal',
    ]);

    expect($dto)->toBeInstanceOf(ListaDocFiscalOutro::class);
});

it('ListaDocOutro::fromArray creates instance from array', function () {
    $dto = ListaDocOutro::fromArray([
        'nDoc' => '123',
        'xDoc' => 'Outro Doc',
    ]);

    expect($dto)->toBeInstanceOf(ListaDocOutro::class);
});

it('ListaDocFornec::fromArray creates instance from array', function () {
    $dto = ListaDocFornec::fromArray([
        'xNome' => 'Fornecedor',
        'CPF' => '12345678901',
    ]);

    expect($dto)->toBeInstanceOf(ListaDocFornec::class);
});

it('ListaDocReeRepRes::fromArray creates instance from array', function () {
    $dto = ListaDocReeRepRes::fromArray([
        'dtEmiDoc' => '2026-01-01',
        'dtCompDoc' => '2026-01-01',
        'tpReeRepRes' => '01',
        'vlrReeRepRes' => '100.00',
        'dFeNacional' => [
            'tipoChaveDFe' => '1',
            'chaveDFe' => '12345678901234567890123456789012345678901234',
        ],
    ]);

    expect($dto)->toBeInstanceOf(ListaDocReeRepRes::class);
});

it('InfoReeRepRes::fromArray creates instance from array', function () {
    $dto = InfoReeRepRes::fromArray([
        'documentos' => [
            [
                'dtEmiDoc' => '2026-01-01',
                'dtCompDoc' => '2026-01-01',
                'tpReeRepRes' => '01',
                'vlrReeRepRes' => '100.00',
                'dFeNacional' => [
                    'tipoChaveDFe' => '1',
                    'chaveDFe' => '12345678901234567890123456789012345678901234',
                ],
            ],
        ],
    ]);

    expect($dto)->toBeInstanceOf(InfoReeRepRes::class);
});

it('InfoIBSCBS::fromArray creates instance from array', function () {
    $dto = InfoIBSCBS::fromArray([
        'finNFSe' => '0',
        'indFinal' => '0',
        'cIndOp' => '001',
        'indDest' => '0',
        'valores' => [
            'trib' => [
                'gIBSCBS' => [
                    'CST' => '00',
                    'cClassTrib' => '001',
                ],
            ],
        ],
    ]);

    expect($dto)->toBeInstanceOf(InfoIBSCBS::class);
});

// ── Negative: invalid enum values throw ValueError ──────────────────────

it('InfDPS::fromArray throws ValueError for invalid tpAmb', function () {
    InfDPS::fromArray([
        'tpAmb' => '99',
        'dhEmi' => '2026-02-27T10:00:00-03:00',
        'verAplic' => '1.0',
        'serie' => '1',
        'nDPS' => 1,
        'dCompet' => '2026-02-27',
        'tpEmit' => '1',
        'cLocEmi' => '3501608',
    ]);
})->throws(ValueError::class);

it('RegTrib::fromArray throws ValueError for invalid opSimpNac', function () {
    RegTrib::fromArray([
        'opSimpNac' => 'INVALID',
        'regEspTrib' => '0',
    ]);
})->throws(ValueError::class);

it('ExigibilidadeSuspensa::fromArray throws ValueError for invalid tpSusp', function () {
    ExigibilidadeSuspensa::fromArray([
        'tpSusp' => 'XYZ',
        'nProcesso' => '12345',
    ]);
})->throws(ValueError::class);

// ── DpsData full fromArray with optional subgroups ──────────────────────

it('DpsData::fromArray creates instance with toma and subst', function () {
    $dto = DpsData::fromArray([
        'infDPS' => [
            'tpAmb' => '2',
            'dhEmi' => '2026-02-27T10:00:00-03:00',
            'verAplic' => '1.0',
            'serie' => '1',
            'nDPS' => 1,
            'dCompet' => '2026-02-27',
            'tpEmit' => '1',
            'cLocEmi' => '3501608',
        ],
        'prest' => [
            'CNPJ' => '12345678000195',
            'regTrib' => [
                'opSimpNac' => '1',
                'regEspTrib' => '0',
            ],
        ],
        'serv' => [
            'cServ' => [
                'cTribNac' => '010101',
                'xDescServ' => 'Serviço',
                'cNBS' => '123456789',
            ],
            'cLocPrestacao' => '3501608',
        ],
        'valores' => [
            'vServPrest' => ['vServ' => '100.00'],
            'trib' => [
                'tribMun' => [
                    'tribISSQN' => '1',
                    'tpRetISSQN' => '1',
                ],
                'indTotTrib' => '0',
            ],
        ],
        'toma' => [
            'xNome' => 'Tomador',
            'CNPJ' => '98765432000100',
        ],
        'subst' => [
            'chSubstda' => '12345678901234567890123456789012345678901234567890',
            'cMotivo' => '01',
        ],
        'interm' => [
            'xNome' => 'Intermediario',
            'CPF' => '12345678901',
        ],
        'IBSCBS' => [
            'finNFSe' => '0',
            'indFinal' => '0',
            'cIndOp' => '001',
            'indDest' => '0',
            'valores' => [
                'trib' => [
                    'gIBSCBS' => [
                        'CST' => '00',
                        'cClassTrib' => '001',
                    ],
                ],
            ],
        ],
    ]);

    expect($dto)->toBeInstanceOf(DpsData::class)
        ->and($dto->toma)->toBeInstanceOf(Tomador::class)
        ->and($dto->subst)->toBeInstanceOf(SubstituicaoData::class)
        ->and($dto->interm)->toBeInstanceOf(Tomador::class)
        ->and($dto->IBSCBS)->toBeInstanceOf(InfoIBSCBS::class);
});
