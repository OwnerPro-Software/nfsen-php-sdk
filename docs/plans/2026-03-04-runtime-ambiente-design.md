# Design: Runtime Environment Override

## Problem

`NfseClient::for()` and `NfseNacional::for()` always read `ambiente` from
config. In a multi-tenant scenario different tenants may need different
environments (homologação vs. produção), so the caller must be able to
override the environment per-client instance.

## Approach

Add an optional `?NfseAmbiente $ambiente = null` parameter to
`NfseClient::for()` and `NfseNacional::for()`.

- When `null` → uses `config('nfse-nacional.ambiente')` (current behavior).
- When provided → uses the given value, ignoring config.

`forStandalone()` already accepts `ambiente` — no changes needed there.

## Changes

| File | Change |
|------|--------|
| `src/NfseClient.php` | Add `?NfseAmbiente $ambiente = null` to `for()`, use it when non-null |
| `src/Facades/NfseNacional.php` | Propagate parameter to `NfseClient::for()`, update phpdoc |
| `README.md` | Document the new parameter |
| Tests | Verify override sends to correct URL |

## API

```php
// Config fallback (backward-compatible)
NfseNacional::for($pfx, $senha, '3550308');

// Override
NfseNacional::for($pfx, $senha, '3550308', NfseAmbiente::PRODUCAO);
```

## What does NOT change

- `NfseNacionalServiceProvider` — binding uses config, unchanged.
- `forStandalone()` — already has the parameter.
- No other config parameter is exposed for runtime override.
