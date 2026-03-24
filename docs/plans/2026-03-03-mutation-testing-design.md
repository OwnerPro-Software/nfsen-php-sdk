# Mutation Testing Setup — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Pest mutation testing (`--mutate`) for NfseClient and Operations classes.

**Architecture:** Add `covers()` declarations to 6 existing test files so Pest knows which source classes to mutate. Add a composer script for manual execution. No threshold enforcement yet.

**Tech Stack:** Pest 3 built-in `--mutate`, `covers()` function.

---

### Task 1: Add `covers()` to NfseClient feature tests

**Files:**
- Modify: `tests/Feature/NfseClientEmitirTest.php:1-2`
- Modify: `tests/Feature/NfseClientConsultarTest.php:1-2`
- Modify: `tests/Feature/NfseClientCancelarTest.php:1-2`
- Modify: `tests/Feature/NfseClientSubstituirTest.php:1-2`
- Modify: `tests/Feature/NfseClientEmitirDecisaoJudicialTest.php:1-2`

**Step 1: Add `covers()` after the opening `<?php` tag in each file**

Each file already imports `NfseClient`. Add `covers(NfseClient::class);` after the `<?php` tag and before the `use` statements. Example for `NfseClientEmitirTest.php`:

```php
<?php

covers(NfseClient::class);

use Illuminate\Http\Client\Request;
// ... rest of file unchanged
```

Apply the same pattern to all 5 files listed above.

**Step 2: Run tests to verify nothing broke**

Run: `./vendor/bin/pest`
Expected: All tests PASS. The `covers()` call has no effect on normal test execution.

**Step 3: Commit**

```bash
git add tests/Feature/NfseClientEmitirTest.php tests/Feature/NfseClientConsultarTest.php tests/Feature/NfseClientCancelarTest.php tests/Feature/NfseClientSubstituirTest.php tests/Feature/NfseClientEmitirDecisaoJudicialTest.php
git commit -m "test: add covers(NfseClient) for mutation testing"
```

---

### Task 2: Add `covers()` to NfseConsulter unit test

**Files:**
- Modify: `tests/Unit/Operations/NfseConsulterTest.php:1-2`

**Step 1: Add `covers()` after the opening `<?php` tag**

The file already imports `NfseConsulter`. Add `covers(NfseConsulter::class);` after `<?php`:

```php
<?php

covers(NfseConsulter::class);

use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
// ... rest of file unchanged
```

Note: `NfseConsulter` needs to be imported. Check if a `use` statement exists; if not, the `covers()` call needs the FQCN or a `use` import must be added:

```php
use OwnerPro\Nfsen\Operations\NfseConsulter;

covers(NfseConsulter::class);
```

**Step 2: Run tests to verify nothing broke**

Run: `./vendor/bin/pest`
Expected: All tests PASS.

**Step 3: Commit**

```bash
git add tests/Unit/Operations/NfseConsulterTest.php
git commit -m "test: add covers(NfseConsulter) for mutation testing"
```

---

### Task 3: Add composer script for mutation testing

**Files:**
- Modify: `composer.json:43-57` (scripts section)

**Step 1: Add the `test:mutate` script**

Add `"test:mutate": "./vendor/bin/pest --mutate"` to the scripts section, after the existing `test:types` entry:

```json
"scripts": {
    "test": "./vendor/bin/pest --coverage --min=100",
    "test:types": "./vendor/bin/pest --type-coverage --min=100",
    "test:mutate": "./vendor/bin/pest --mutate",
    "analyse": "./vendor/bin/phpstan analyse",
    ...
}
```

**Step 2: Verify the script works**

Run: `composer test:mutate`
Expected: Pest runs mutation testing against NfseClient and NfseConsulter. Output shows mutation score.

**Step 3: Commit**

```bash
git add composer.json
git commit -m "build: add composer test:mutate script"
```

---

### Task 4: Run baseline and report results

**Step 1: Run mutation testing**

Run: `composer test:mutate`

**Step 2: Record the baseline mutation score**

Note the mutation score percentage, number of mutants killed, escaped, and uncovered. This is the baseline to improve from.

No commit needed — this is informational.
