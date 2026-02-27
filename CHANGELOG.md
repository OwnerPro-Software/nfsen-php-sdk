# Changelog

## [Unreleased]

### Breaking Changes
- Requisito mínimo de PHP alterado de 8.1 para **8.2**
- Namespace alterado de `Hadder\NfseNacional` para `Pulsar\NfseNacional`
- Identificação de prefeituras exclusivamente por **código IBGE** (7 dígitos); suporte a nome legado (`americana-sp`) removido
- API pública completamente nova: `NfseClient::for($pfx, $senha, $ibge)->emitir($dpsData)`

### Added
- `NfseClient::for()` — instância configurada por tenant via container Laravel (com fallback automático para standalone)
- `NfseClient::forStandalone()` — instância sem dependência do container Laravel
- Fluent consulta: `consultar()->nfse/dps/danfse/eventos($chave)`
- DTOs tipados: `DpsData`, `NfseResponse`, `DanfseResponse`, `EventosResponse`
- Laravel Events: `NfseRequested`, `NfseEmitted`, `NfseCancelled`, `NfseQueried`, `NfseFailed`, `NfseRejected`
- mTLS via `tmpfile()` — sem escrita nomeada em disco, sem CNPJ no path
- SSL habilitado corretamente (`verify: true`)
- Validação XSD do DPS via `DpsBuilder::buildAndValidate()`

### Removed
- Arquivo `Helpers.php` com `now()` global (substituído por `illuminate/support`)
- Suporte a identificação de prefeitura por nome (chaves por nome removidas do JSON)
- Chaves duplicadas por nome no `prefeituras.json` (mantido apenas IBGE 7 dígitos)
