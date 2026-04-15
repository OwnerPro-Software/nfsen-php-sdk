<?php
/** @var \OwnerPro\Nfsen\Danfse\NfseData $data */
/** @var \OwnerPro\Nfsen\Danfse\MunicipalityBranding|null $municipality */
/** @var string|null $logo */
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
    <?php if ($data->ambiente->isHomologacao()): ?>
    <div class="watermark">HOMOLOGAÇÃO</div>
    <?php endif; ?>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <?php if ($logo): ?>
                <img src="<?= $h($logo) ?>" alt="Logo" style="max-width: 130pt; max-height: 40pt;">
                <?php endif; ?>
            </td>
            <td class="title-cell">
                <div style="font-size: 10pt; font-weight: bold;">DANFSe v1.0</div>
                <div style="font-size: 8pt; font-weight: bold;">Documento Auxiliar da NFS-e</div>
                <?php if ($data->ambiente->isHomologacao()): ?>
                    <div style="color: red; font-weight: bold;">NFS-e SEM VALIDADE JURÍDICA</div>
                <?php endif; ?>
            </td>
            <td class="municipality-cell">
                <?php if ($municipality): ?>
                <table>
                    <tr>
                        <?php if ($municipality->logoDataUri): ?>
                        <td><img style="height: 30pt; width: auto" src="<?= $h($municipality->logoDataUri) ?>" alt="Prefeitura" /></td>
                        <?php endif; ?>
                        <td style="font-size: 7pt;">
                            <?= $h($municipality->name) ?><br>
                            <?php if ($municipality->department): ?>
                            <?= $h($municipality->department) ?><br>
                            <?php endif; ?>
                            <?php if ($municipality->email): ?>
                            <?= $h($municipality->email) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Grade de Identificação -->
    <div class="bordered-section first-section">
        <table style="min-height: 110px;">
            <tr>
                <td colspan="3">
                    <span class="label">Chave de Acesso da NFS-e</span>
                    <span class="value"><?= $h($data->chaveAcesso) ?></span>
                </td>
                <td style="width: 25%; position: relative;" rowspan="3">
                    <div class="qr-container">
                        <img src="<?= $h($qrCode) ?>" alt="QR Code" style="width: 70px; height: 70px; display: block; margin: 0 auto;" />
                        <div style="font-size: 6pt; padding-top: 2pt; text-align: left; line-height: 1.2;">
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
                    <span class="label">Número do DPS</span>
                    <span class="value"><?= $h($data->numeroDps) ?></span>
                </td>
                <td>
                    <span class="label">Série do DPS</span>
                    <span class="value"><?= $h($data->serieDps) ?></span>
                </td>
                <td>
                    <span class="label">Data e Hora da emissão da DPS</span>
                    <span class="value"><?= $h($data->emissaoDps) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Emitente -->
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%; font-weight: bold; font-size: 7pt;">
                    <span class="label section-title">EMITENTE DA NFS-e</span>
                    <span class="value">Prestador do Serviço</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $h($data->emitente->cnpjCpf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Inscrição Municipal</span>
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
                <td colspan="2">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->emitente->email) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->emitente->endereco) ?></span>
                </td>
                <td>
                    <span class="label">Município</span>
                    <span class="value"><?= $h($data->emitente->municipio) ?></span>
                </td>
                <td>
                    <span class="label">CEP</span>
                    <span class="value"><?= $h($data->emitente->cep) ?></span>
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
                <td style="width: 25%; font-weight: bold; font-size: 7pt;">
                    <span class="section-title">TOMADOR DO SERVIÇO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF / NIF</span>
                    <span class="value"><?= $h($data->tomador->cnpjCpf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Inscrição Municipal</span>
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
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->tomador->email) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->tomador->endereco) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município</span>
                    <span class="value"><?= $h($data->tomador->municipio) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CEP</span>
                    <span class="value"><?= $h($data->tomador->cep) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Intermediário -->
    <?php if ($data->intermediario !== null): ?>
    <div class="bordered-section">
        <table>
            <tr>
                <td style="width: 25%; font-weight: bold; font-size: 7pt;">
                  <span class="section-title">INTERMEDIÁRIO DO SERVIÇO</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CNPJ / CPF</span>
                    <span class="value"><?= $h($data->intermediario->cnpjCpf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Inscrição Municipal</span>
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
                <td colspan="2" style="width: 50%;">
                    <span class="label">E-mail</span>
                    <span class="value"><?= $h($data->intermediario->email) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">Endereço</span>
                    <span class="value"><?= $h($data->intermediario->endereco) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município</span>
                    <span class="value"><?= $h($data->intermediario->municipio) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CEP</span>
                    <span class="value"><?= $h($data->intermediario->cep) ?></span>
                </td>
            </tr>
        </table>
    </div>
    <?php else: ?>
    <div class="bordered-section" style="text-align: center; font-weight: normal; font-size: 7pt;">
        INTERMEDIÁRIO DO SERVIÇO NÃO IDENTIFICADO NA NFS-e
    </div>
    <?php endif; ?>

    <!-- Serviço Prestado -->
    <div class="bordered-section">
        <table>
            <tr>
                <td colspan="4" class="section-header">
                  <span class="section-title">SERVIÇO PRESTADO</span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">Código de Tributação Nacional</span>
                    <span class="value"><?= $h($data->servico->codigoTribNacional) ?> - <?= $h($data->servico->descTribNacional) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Código de Tributação Municipal</span>
                    <span class="value"><?= $h($data->servico->codigoTribMunicipal) ?> - <?= $h($data->servico->descTribMunicipal) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Local da Prestação</span>
                    <span class="value"><?= $h($data->servico->localPrestacao) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">País da Prestação</span>
                    <span class="value"><?= $h($data->servico->paisPrestacao) ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="label">Descrição do Serviço</span>
                    <span class="value"><?= $h($data->servico->descricao) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tributação Municipal -->
    <div class="bordered-section">
        <table>
            <tr>
                <td colspan="4" class="section-header">
                  <span class="section-title">TRIBUTAÇÃO MUNICIPAL</span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">Tributação do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->tributacaoIssqn) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Município de Incidência do ISSQN</span>
                    <span class="value"><?= $h($data->tribMun->municipioIncidencia) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Regime Especial de Tributação</span>
                    <span class="value"><?= $h($data->tribMun->regimeEspecial) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Valor do Serviço</span>
                    <span class="value"><?= $h($data->tribMun->valorServico) ?></span>
                </td>
            </tr>
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
                <td colspan="4" class="section-header">
                  <span class="section-title">TRIBUTAÇÃO FEDERAL</span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">IRRF</span>
                    <span class="value"><?= $h($data->tribFed->irrf) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Contribuição Previdenciária - Retida</span>
                    <span class="value"><?= $h($data->tribFed->cp) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">CSLL</span>
                    <span class="value"><?= $h($data->tribFed->csll) ?></span>
                </td>
                <td style="width: 25%;"></td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">PIS - Débito Apuração Própria</span>
                    <span class="value"><?= $h($data->tribFed->pis) ?></span>
                </td>
                <td colspan="2">
                    <span class="label">COFINS - Débito Apuração Própria</span>
                    <span class="value"><?= $h($data->tribFed->cofins) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Valor Total -->
    <div class="bordered-section">
        <table>
            <tr>
                <td colspan="4" class="section-header">
                  <span class="section-title">VALOR TOTAL DA NFS-e</span>
                </td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <span class="label">Valor do Serviço</span>
                    <span class="value"><?= $h($data->totais->valorServico) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Desconto Condicionado</span>
                    <span class="value"><?= $h($data->totais->descontoCondicionado) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Desconto Incondicionado</span>
                    <span class="value"><?= $h($data->totais->descontoIncondicionado) ?></span>
                </td>
                <td style="width: 25%;">
                    <span class="label">ISSQN Retido</span>
                    <span class="value"><?= $h($data->totais->issqnRetido) ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Total das Retenções Federais</span>
                    <span class="value"><?= $h($data->totais->retencoesFederais) ?></span>
                </td>
                <td colspan="2">
                    <span class="label">PIS/COFINS - Débito Apur. Própria</span>
                    <span class="value"><?= $h($data->totais->pisCofins) ?></span>
                </td>
                <td>
                    <span class="label">Valor Líquido da NFS-e</span>
                    <span class="value" style="font-weight: bold;"><?= $h($data->totais->valorLiquido) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Totais Aproximados de Tributos -->
    <div class="bordered-section">
        <table>
            <tr>
                <td colspan="3" class="section-header">
                  <span class="section-title">TOTAIS APROXIMADOS DOS TRIBUTOS</span>
                </td>
            </tr>
            <tr>
                <td style="width: 33.33%; text-align: center;">
                    <span class="label">Federais</span>
                    <span class="value"><?= $h($data->totaisTributos->federais) ?></span>
                </td>
                <td style="width: 33.33%; text-align: center;">
                    <span class="label">Estaduais</span>
                    <span class="value"><?= $h($data->totaisTributos->estaduais) ?></span>
                </td>
                <td style="width: 33.33%; text-align: center;">
                    <span class="label">Municipais</span>
                    <span class="value"><?= $h($data->totaisTributos->municipais) ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Informações Complementares -->
    <div class="bordered-section">
        <table>
            <tr>
                <td class="section-header">
                  <span class="section-title">INFORMAÇÕES COMPLEMENTARES</span>
                </td>
            </tr>
            <tr>
                <td style="min-height: 30pt; padding: 5pt;">
                    <span class="value"><?= $h($data->informacoesComplementares) ?></span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
