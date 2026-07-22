<?php
/** @var \OwnerPro\Nfsen\Danfse\NfseData $data */
/** @var string $logo */
/** @var string $qrCode */
/** @var string $css */
/** @var \Closure(string):string $h */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFSe - <?= $h($data->numeroNfse) ?></title>
    <style>
        <?= $css ?>
    </style>
</head>
<body>
    <?php
    /*
     * Marca d'água só nos dois casos da NT: cancelamento (item 2.5.1) e substituição
     * (item 2.5.2). Homologação é sinalizada pela expressão do cabeçalho (item 2.4.3).
     *
     * Comentário em PHP, não em HTML: o texto de um <!-- --> vai para a saída e casaria
     * com asserções sobre o conteúdo impresso.
     */
    ?>
    <?php if ($data->marcaDagua !== null): ?>
    <div class="watermark-nt"><?= $h($data->marcaDagua->texto()) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <!-- Item 2.4.3: no canto esquerdo, a logomarca da NFS-e. -->
            <td class="logo-cell">
                <img src="<?= $h($logo) ?>" alt="NFS-e" style="max-width: 130pt; max-height: 40pt;">
            </td>
            <td class="title-cell">
                <div style="font-size: 9pt; font-weight: bold;">DANFSe v2.0</div>
                <div style="font-size: 9pt; font-weight: bold;">Documento Auxiliar da NFS-e</div>
                <?php if ($data->ambiente->isHomologacao()): ?>
                    <div class="sem-validade">NFS-e SEM VALIDADE JURÍDICA</div>
                <?php endif; ?>
            </td>
            <!-- "QUADRO DA IDENT. MUNICÍPIO/AMBIENTE" do item 2.4.3. -->
            <td class="quadro-ident">
                <?php if ($data->municipioEmitente !== ''): ?>
                <div class="header-municipio">Município: <?= $h($data->municipioEmitente) ?></div>
                <?php endif; ?>
                <div class="header-ambiente">Ambiente Gerador: <?= $h($data->ambienteGerador) ?></div>
                <div class="header-ambiente">Tipo de Ambiente: <?= $h($data->ambiente->label()) ?></div>
            </td>
        </tr>
    </table>

    <!-- Grade de Identificação -->
    <div class="bordered-section first-section">
        <?php
        /*
         * O bloco de identificação vai de 1,48 cm a 4,32 cm na tabela do item 2.4.5 —
         * 2,84 cm, ou 81 pontos. Não é folga estética: o QR Code do item 2.4.3 começa em
         * Y 1,67 e sua descrição desce até cerca de 4,0 cm; com o bloco mais curto que a
         * norma, esse texto invade a primeira linha do bloco do prestador.
         */
        ?>
        <table style="min-height: 81pt;">
            <tr>
                <td colspan="3">
                    <span class="label">Chave de Acesso da NFS-e</span>
                    <span class="value"><?= $h($data->chaveAcesso) ?></span>
                </td>
                <td style="width: 25%; position: relative;" rowspan="3">
                    <div class="qr-container">
                        <img src="<?= $h($qrCode) ?>" alt="QR Code" style="width: 60px; height: 60px; display: block; margin: 0 auto;" />
                        <div class="qr-complemento">
                            A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">Número da NFS-e</span>
                    <span class="value"><?= $h($data->numeroNfse) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Competência da NFS-e</span>
                    <span class="value"><?= $h($data->competencia) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Data e Hora da emissão da NFS-e</span>
                    <span class="value"><?= $h($data->emissaoNfse) ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Número da DPS</span>
                    <span class="value"><?= $h($data->numeroDps) ?></span>
                </td>
                <td>
                    <span class="label">Série da DPS</span>
                    <span class="value"><?= $h($data->serieDps) ?></span>
                </td>
                <td>
                    <span class="label">Data e Hora da emissão da DPS</span>
                    <span class="value"><?= $h($data->emissaoDps) ?></span>
                </td>
            </tr>
            <tr>
                <td class="sombreado">
                    <span class="label">Emitente da NFS-e</span>
                    <span class="value"><?= $h($data->emitidaPor) ?></span>
                </td>
                <td>
                    <span class="label">Situação da NFS-e</span>
                    <span class="value"><?= $h($data->situacao) ?></span>
                </td>
                <td>
                    <span class="label">Finalidade</span>
                    <span class="value"><?= $h($data->finalidade) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Emitente -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                    <span class="section-title">PRESTADOR / FORNECEDOR</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $h($data->emitente->cnpjCpf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Indicador Municipal (Inscrição)</span>
                    <span class="value"><?= $h($data->emitente->im) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $h($data->emitente->telefone) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $h($data->emitente->nome) ?></span>
                </td>
                <td>
                    <span class="label">Município / Sigla UF</span>
                    <span class="value"><?= $h($data->emitente->municipio) ?></span>
                </td>
                <td>
                    <span class="label">Código IBGE / CEP</span>
                    <span class="value"><?= $h($data->emitente->codigoIbge) ?> / <?= $h($data->emitente->cep) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->emitente->endereco) ?></span>
                </td>
                <td colspan="2">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->emitente->email) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Simples Nacional na Data de Competência</span>
                    <span class="value"><?= $h($data->emitente->simplesNacional) ?></span>
                </td>
                <td colspan="2">
                    <span class="label">Regime de Apuração Tributária pelo SN</span>
                    <span class="value"><?= $h($data->emitente->regimeSN) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tomador -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                    <span class="section-title">TOMADOR / ADQUIRENTE</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $h($data->tomador->cnpjCpf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Indicador Municipal (Inscrição)</span>
                    <span class="value"><?= $h($data->tomador->im) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $h($data->tomador->telefone) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $h($data->tomador->nome) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município / Sigla UF</span>
                    <span class="value"><?= $h($data->tomador->municipio) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código IBGE / CEP</span>
                    <span class="value"><?= $h($data->tomador->codigoIbge) ?> / <?= $h($data->tomador->cep) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->tomador->endereco) ?></span>
                </td>
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->tomador->email) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Destinatário da Operação -->
    <?php if ($data->destinatarioEhTomador): ?>
    <div class="bordered-section" style="text-align: center; font-weight: normal; font-size: 7pt;">
        O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO
    </div>
    <?php elseif ($data->destinatario): ?>
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">DESTINATÁRIO DA OPERAÇÃO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $h($data->destinatario->cnpjCpf) ?></span>
                </td>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $h($data->destinatario->telefone) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $h($data->destinatario->nome) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município / Sigla UF</span>
                    <span class="value"><?= $h($data->destinatario->municipio) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código IBGE / CEP</span>
                    <span class="value"><?= $h($data->destinatario->codigoIbge) ?> / <?= $h($data->destinatario->cep) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->destinatario->endereco) ?></span>
                </td>
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->destinatario->email) ?></span>
                </td>
            </tr>
        </table>
    </div>
    <?php else: ?>
    <div class="bordered-section" style="text-align: center; font-weight: normal; font-size: 7pt;">
        DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e
    </div>
    <?php endif; ?>

    <!-- Intermediário -->
    <?php if ($data->intermediario !== null): ?>
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">INTERMEDIÁRIO DA OPERAÇÃO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF</span>
                    <span class="value"><?= $h($data->intermediario->cnpjCpf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Indicador Municipal (Inscrição)</span>
                    <span class="value"><?= $h($data->intermediario->im) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Telefone</span>
                    <span class="value"><?= $h($data->intermediario->telefone) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Nome / Nome Empresarial</span>
                    <span class="value"><?= $h($data->intermediario->nome) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município / Sigla UF</span>
                    <span class="value"><?= $h($data->intermediario->municipio) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código IBGE / CEP</span>
                    <span class="value"><?= $h($data->intermediario->codigoIbge) ?> / <?= $h($data->intermediario->cep) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->intermediario->endereco) ?></span>
                </td>
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->intermediario->email) ?></span>
                </td>
            </tr>
        </table>
    </div>
    <?php else: ?>
    <div class="bordered-section" style="text-align: center; font-weight: normal; font-size: 7pt;">
        INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e
    </div>
    <?php endif; ?>

    <!-- Serviço Prestado -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">SERVIÇO PRESTADO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código de Tributação Nacional / Municipal</span>
                    <span class="value"><?= $h($data->servico->codigoTribNacional) ?> / <?= $h($data->servico->codigoTribMunicipal) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código da NBS</span>
                    <span class="value"><?= $h($data->servico->codigoNbs) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Local da Prestação / Sigla UF / País</span>
                    <span class="value"><?= $h($data->servico->localPrestacao) ?> / <?= $h($data->servico->paisPrestacao) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="value"><?= $h($data->servico->descricaoTributacao) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="label">Descrição do Serviço</span>
                    <div class="value texto-livre"><?= $h($data->servico->descricao) ?></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tributação Municipal -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">TRIBUTAÇÃO MUNICIPAL (ISSQN)</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Tipo de Tributação do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->tributacaoIssqn) ?></span>
                </td>
                <?php
                /*
                 * A tabela do item 2.4.5 põe estes dois campos em 0,30 e 5,41, o que não
                 * deixa coluna para o título do bloco. O Anexo I os desloca uma coluna à
                 * direita, e é ele que manda: o item 2.2.4 exige a disposição do anexo.
                 */
                ?>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Município / Sigla UF / País da Incidência do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->municipioIncidencia) ?></span>
                </td>
            </tr>
            <?php if ($data->tribMun->exibeRegimeEImunidade): ?>
            <tr>
                <td>
                    <span class="label">Regime Especial de Tributação do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->regimeEspecial) ?></span>
                </td>
                <td>
                    <span class="label">Tipo de Imunidade do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->tipoImunidade) ?></span>
                </td>
                <td>
                    <span class="label">Suspensão da Exigibilidade do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->suspensaoExigibilidade) ?></span>
                </td>
                <td>
                    <span class="label">Número Processo Suspensão</span>
                    <span class="value"><?= $h($data->tribMun->numeroProcessoSuspensao) ?></span>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($data->tribMun->exibeBeneficioEDeducoes): ?>
            <tr>
                <td>
                    <span class="label">Benefício Municipal</span>
                    <span class="value"><?= $h($data->tribMun->beneficioMunicipal) ?></span>
                </td>
                <td>
                    <span class="label">Cálculo do BM</span>
                    <span class="value"><?= $h($data->tribMun->calculoBM) ?></span>
                </td>
                <td>
                    <span class="label">Total Deduções/Reduções</span>
                    <span class="value"><?= $h($data->tribMun->totalDeducoesReducoes) ?></span>
                </td>
                <td>
                    <span class="label">Desconto Incondicionado</span>
                    <span class="value"><?= $h($data->totais->descontoIncondicionado) ?></span>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>
                    <span class="label">BC ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->bcIssqn) ?></span>
                </td>
                <td>
                    <span class="label">Alíquota Aplicada</span>
                    <span class="value"><?= $h($data->tribMun->aliquota) ?></span>
                </td>
                <td>
                    <span class="label">Retenção do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->retencaoIssqn) ?></span>
                </td>
                <td>
                    <span class="label">ISSQN Apurado</span>
                    <span class="value"><?= $h($data->tribMun->issqnApurado) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tributação Federal -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">TRIBUTAÇÃO FEDERAL (EXCETO CBS)</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">IRRF</span>
                    <span class="value"><?= $h($data->tribFed->irrf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Contribuição Previdenciária - Retida</span>
                    <span class="value"><?= $h($data->tribFed->cp) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Contribuições Sociais - Retidas</span>
                    <span class="value"><?= $h($data->tribFed->csll) ?></span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">PIS - Débito Apuração Própria</span>
                    <span class="value"><?= $h($data->tribFed->pis) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">COFINS - Débito Apuração Própria</span>
                    <span class="value"><?= $h($data->tribFed->cofins) ?></span>
                </td>
                <td colspan="2">
                    <span class="label">Descrição Contrib. Sociais - Retidas</span>
                    <span class="value"><?= $h($data->tribFed->descricaoContribuicoesRetidas) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tributação IBS / CBS -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">TRIBUTAÇÃO IBS / CBS</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CST / cClassTrib</span>
                    <span class="value"><?= $h($data->tribIbsCbs->cstClassTrib) ?></span>
                </td>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF</span>
                    <span class="value"><?= $h($data->tribIbsCbs->indicadorOperacao) ?></span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">Exclusões e Reduções da Base de Cálculo</span>
                    <span class="value"><?= $h($data->tribIbsCbs->exclusoesReducoes) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Base de Cálculo Após Exclusões e Reduções</span>
                    <span class="value"><?= $h($data->tribIbsCbs->baseCalculo) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Red. Alíquota IBS / Red. Alíquota CBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->reducaoAliquotas) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Alíquota - IBS UF / IBS Mun</span>
                    <span class="value"><?= $h($data->tribIbsCbs->aliquotaIbs) ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Alíq. Efetiva Municipal - IBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->aliquotaEfetivaMunicipal) ?></span>
                </td>
                <td>
                    <span class="label">Valor Apurado Municipal - IBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->valorApuradoMunicipal) ?></span>
                </td>
                <td>
                    <span class="label">Alíq. Efetiva Estadual - IBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->aliquotaEfetivaEstadual) ?></span>
                </td>
                <td>
                    <span class="label">Valor Apurado Estadual - IBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->valorApuradoEstadual) ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Valor Total Apurado - IBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->valorTotalIbs) ?></span>
                </td>
                <td>
                    <span class="label">Alíquota - CBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->aliquotaCbs) ?></span>
                </td>
                <td>
                    <span class="label">Alíquota Efetiva - CBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->aliquotaEfetivaCbs) ?></span>
                </td>
                <td>
                    <span class="label">Valor Total Apurado - CBS</span>
                    <span class="value"><?= $h($data->tribIbsCbs->valorTotalCbs) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Valor Total -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%;" class="section-header">
                  <span class="section-title">VALOR TOTAL DA NFS-e</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Valor da Operação / Serviço</span>
                    <span class="value"><?= $h($data->totais->valorServico) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Desconto Incondicionado</span>
                    <span class="value"><?= $h($data->totais->descontoIncondicionado) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Desconto Condicionado</span>
                    <span class="value"><?= $h($data->totais->descontoCondicionado) ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Total das Retenções (ISSQN / Federais)</span>
                    <span class="value"><?= $h($data->totais->totalRetencoes) ?></span>
                </td>
                <td>
                    <span class="label">Valor Líquido da NFS-e</span>
                    <span class="value"><?= $h($data->totais->valorLiquido) ?></span>
                </td>
                <td>
                    <span class="label">Total do IBS/CBS</span>
                    <span class="value"><?= $h($data->totais->totalIbsCbs) ?></span>
                </td>
                <td class="sombreado">
                    <span class="label">Valor Líquido da NFS-e + IBS/CBS</span>
                    <span class="value"><?= $h($data->totais->valorLiquidoComIbsCbs) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!--
        Os totais aproximados de tributos não têm bloco próprio: a nota 10 do item 2.4.5
        os põe aqui, numa linha fixa. Ela ocupa uma célula própria porque a nota manda
        que o corte do texto livre seja "sem prejuízo" dela, e só o layout de tabela
        garante isso.
    -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header">
                  <span class="section-title">INFORMAÇÕES COMPLEMENTARES</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 3pt 5pt 0 5pt;">
                    <div class="value texto-livre"><?= $h($data->informacoesComplementares) ?></div>
                </td>
            </tr>
            <tr>
                <td style="padding: 0 5pt 3pt 5pt;">
                    <div class="value"><?= $h($data->totaisTributos->linhaNt008()) ?></div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
