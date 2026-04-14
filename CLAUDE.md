# CLAUDE.md

## Architecture

Hexagonal / ports & adapters:

- `src/NfsenClient.php` — wiring root. `for()` resolves Laravel config; `forStandalone()` constructs everything by hand. Both paths must keep working.
- `src/Contracts/Driving/*` — driving ports (what clients call: `EmitsNfse`, `CancelsNfse`, `ConsultsNfse`, `DistributesNfse`, `SubstitutesNfse`, …).
- `src/Contracts/Driven/*` — driven ports (what the core needs: `SendsHttpRequests`, `SignsXml`, `ResolvesPrefeituras`, `ExtractsAuthorIdentity`, …).
- `src/Operations/` — application services (one per driving port).
- `src/Pipeline/` — `NfseRequestPipeline` / `NfseResponsePipeline` orchestrate the XML → sign → compress → HTTP → parse flow.
- `src/Adapters/` — driven adapters (`NfseHttpClient`, `XmlSigner`, `CertificateManager`, `PrefeituraResolver`).
- `src/Xml/Builders/` — XSD-validated XML builders. `storage/schemes/` holds XSDs; `storage/prefeituras.json` maps IBGE codes to SEFIN/ADN URLs.

## Quality Checks

Always run before considering the work done:

```bash
# tests

./vendor/bin/pest --coverage --min=100 --parallel # runs the complete suite test
./vendor/bin/pest --mutate --min=100 --parallel # mutation tests

# quality checks

./vendor/bin/pest --type-coverage --min=100 # runs type coverage tests
./vendor/bin/rector --dry-run # runs rector quality checks
./vendor/bin/phpstan analyse # run static analysis check
./vendor/bin/psalm --taint-analysis # security analysis
./vendor/bin/pint -p # runs the pint format rules

# if any quality checks changed any file, full suite need to be run again!
./vendor/bin/pest --coverage --min=100 --parallel # runs the complete suite test
# mutation
# type-coverage
# rector
# phpstan
# psalm
# pint
```

Type coverage must remain at 100%. Any new or changed code must include complete type hints.

If a line genuinely can't be killed by mutation testing (e.g. defensive null-coalesce on an invariant), suppress with an inline `// @pest-mutate-ignore <MutatorName> — <reason>` comment. Use sparingly — see `NfsenClient.php`, `Support/GzipCompressor.php`, `Xml/DpsBuilder.php` for precedent.

## Documentation

Whenever the public API changes (new methods, renamed parameters, changed behavior), update `README.md` to reflect those changes.

User-facing strings (README, exception messages, enum descriptions) are in **Brazilian Portuguese**. Match the existing tone when adding new ones.

## Git

Never commit the `composer.lock` file — this is a library, not an application; consumers resolve their own versions. (Already in `.gitignore`, but PRs occasionally try to add it back.)

## Gotchas

- **Dual-mode (Laravel + standalone).** The SDK must keep working under Laravel *and* without it. `NfsenClient::for()` is the Laravel path; `NfsenClient::forStandalone()` is the framework-free path. Don't pull Laravel-only helpers into `Operations/`, `Pipeline/`, `Adapters/`, `Xml/`, or `Support/`.
- **SSL in production is non-negotiable.** `NfsenClient::forStandalone()` forces `sslVerify=true` whenever `ambiente === PRODUCAO`, regardless of the `$sslVerify` argument. Do not "simplify" this away — it's a safety-critical invariant guarded by a reflection-based test (the sanctioned exception to "no reflection in tests" — see below).
- **Identity validation default.** `validateIdentity` defaults to `true` and checks that the certificate's CNPJ/CPF matches the DPS prestador before sending. Only disable it for the legal-representative use case (accountant / procurador emitting on behalf of a third party).
- **Cert fixtures.** Tests use fake ICP-BR certs under `tests/fixtures/certs/` (`fake.pfx`, `fake-icpbr.pfx`, `fake-icpbr-cpf.pfx`, `expired.pfx`). Reuse them — don't generate new ones.

## Testing Principles (Uncle Bob / Clean Code)

### F.I.R.S.T.

- **Fast** — Tests must run quickly. Slow tests don't get run.
- **Independent** — Tests must not depend on each other. Any test should run alone or in any order.
- **Repeatable** — Tests must produce the same result in any environment, every time.
- **Self-Validating** — Tests either pass or fail. No manual inspection of logs or output.
- **Timely** — Write tests at the right time (ideally before the code, TDD).

### Test the Public API, Not Implementation Details

- Tests should exercise the public interface of the system under test.
- Never test private methods or internal state directly — if the behavior matters, it's observable through the public API.
- Reflection in tests is a code smell. Exception: verifying safety-critical invariants (e.g., SSL enforcement in production) where the behavior cannot be observed otherwise.
- If something is hard to test through the public API, the design likely needs to change, not the test.

### One Assertion Per Concept

- Each test should verify one logical concept.
- Multiple asserts are fine when they all verify the same behavior from different angles.
- Avoid testing unrelated things in the same test.

### Arrange-Act-Assert (AAA)

- **Arrange** — Set up the preconditions and inputs.
- **Act** — Execute the behavior under test.
- **Assert** — Verify the expected outcome.
- Keep these sections clearly separated.

### Clean Tests Are Readable

- Tests are documentation. A developer should understand the expected behavior by reading the test.
- Use descriptive test names that state the scenario and expected outcome.
- Minimize noise — use helpers/factories for setup, keep the test body focused on what's being verified.

### Don't Test the Framework

- Don't test that Laravel, Pest, or PHP itself works.
- Test *your* code and *your* business rules.

### Tests Should Be as Easy to Change as Production Code

- If changing an internal detail (renaming a private property, restructuring objects) breaks tests without changing behavior, the tests are coupled to implementation — fix them.
- Tests protect behavior, not structure.

