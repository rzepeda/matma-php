# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

MATMA is an agent-oriented testing architecture for PHP/Laravel. It preserves the host project's PSR-4 conventions and places mirrored tests and centralized mocks under the project's `tests/` directory, with dependency manifests generated on demand rather than committed.

## Implementation status

**Phases 1–3 are implemented and CI-verified.** MATMA discovers PHP symbols using an AST, validates Laravel/Composer PSR-4 naming, computes deterministic forward/reverse dependencies, and writes regenerable YAML manifests. **`matma:index` and `matma:validate` work.** `matma:scaffold` and `matma:impact` still exit 2 (`not_implemented`) until Phases 4–5. Indexing writes only MATMA-owned generated manifests; validation never modifies application files.

Architecture: [accepted ADR](AIDocumentation/ARCHITECTURE_DECISIONS.md) · [task checklist](AIDocumentation/TASKS.md) · [Phase 1 implementation details](AIDocumentation/PHASE_1_IMPLEMENTATION.md) · [Phase 2 implementation details](AIDocumentation/PHASE_2_IMPLEMENTATION.md) · [Phase 3 implementation details](AIDocumentation/PHASE_3_IMPLEMENTATION.md)

## Compatibility

- PHP **8.2+**.
- Laravel **11 onward**, with per-version CI matrices (the Laravel version's own minimum PHP requirement still applies).
- Composer development dependency with Laravel package discovery. No production file changes.
- PHPUnit **or** Pest projects can continue to use Laravel's native `php artisan test`. MATMA's *package* test suite currently uses PHPUnit and Orchestra Testbench.

## Installation

The repository is currently private and is **not published on Packagist**. From a Laravel application with GitHub access to the repository:

```bash
composer config repositories.matma-php vcs https://github.com/rzepeda/matma-php
composer require --dev rzepeda/matma-php:dev-main
php artisan list --raw
```

Laravel's Composer discovery registers `Matma\Php\MatmaServiceProvider`. If package discovery is disabled, add `Matma\Php\MatmaServiceProvider::class` manually to the application's `bootstrap/providers.php`.

No public distribution or license grant is implied: package metadata currently uses `proprietary`.

## Artisan CLI: indexing and validation

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target=App\\Services\\DocumentParser
php artisan matma:impact --target=App\\Services\\DocumentParser --level=2 --format=yaml
php artisan matma:validate --source-root=app --check --format=json
```

**`matma:index` now performs full AST indexing and writes manifests.** `matma:validate` remains read-only. `matma:scaffold` and `matma:impact` are explicitly deferred. Using `matma:index --scaffold` exits 2 without writing anything rather than silently producing missing tests.

| Command | Current status / purpose |
| --- | --- |
| `matma:index` | **Implemented:** full AST dependency graph, shortest-path reverse dependents, safe YAML regeneration |
| `matma:scaffold` | Pending Phase 4: non-destructive test/mock generation |
| `matma:impact` | Pending Phase 5: manifest-only reverse-dependency test selection |
| `matma:validate` | **Implemented:** read-only AST discovery, PSR-4 audit and source-mirror mapping |

Shared flags: `--source-root/-s` (default `app/`), `--tests-root/-t` (`tests/`), `--scaffold/-S` (boolean), `--level/-l` (nonnegative integer, default 0), `--target/-f`, `--check/-c` (boolean), and `--format/-o` (`text`, `json`, or `yaml`).

| Exit code | Meaning |
| --- | --- |
| `0` | Index generated successfully, or validation finished (possibly with a non-strict diagnostic report) |
| `1` | Index failed (structural errors, unsafe/unowned manifest collision, missing target/root), or strict validation failed |
| `2` | An unimplemented operation was requested (`scaffold`, `impact`, or `index --scaffold`) |
| `64` | Invalid MATMA flag value (for example negative `--level`) |

The response includes `command`, `status`, `message`, `exit_code`, and valid `options`. Validation adds `summary`, `files`, `symbol_to_source`, and line-level `diagnostics`. Index output adds `summary`, `index_fingerprint`, `target`, and `manifests` (written, unchanged and removed). JSON is intended for agents; `--format=text|json|yaml` works. `validate --check` fails on violations; indexing always refuses invalid source graphs to avoid storing misleading data.

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:index --target='App\\Services\\DocumentParser' --format=yaml
```

Indexing always rebuilds the **whole** reverse-dependency graph, even with `--target`, to avoid inconsistent dependents. `--target` identifies the requested source in the command response. Generated `tests/mirror/**/*.auto.yaml` are ignored by Git. MATMA never overwrites a YAML file lacking its generated ownership header, and removes stale YAML only when MATMA owns it. Re-running an unchanged index does not rewrite byte-identical manifests. External/unresolved references and dynamic references are explicitly identified; they are not invented as internal dependencies. See [Phase 3](AIDocumentation/PHASE_3_IMPLEMENTATION.md).

## Manual test and mock topology

Through Phase 3 the mirrored **test and mock** tree remains manually authored, because `matma:scaffold` does not exist yet. Unlike test stubs, generated **YAML manifests** are now written by `matma:index`. Production PHP classes follow native PascalCase names; tests use PHPUnit's `*Test.php` suffix:

```text
src/Console/MatmaOptions.php
tests/unit/mirror/Console/MatmaOptionsTest.php
tests/interaction/mirror/Console/MatmaCommandInteractionTest.php
tests/mocks/mirror/Console/MockMatmaCommand.php
tests/mirror/                           # generated .auto.yaml outputs, ignored by Git
tests/business/                         # non-mirrored workflow tests
tests/regression/                       # non-mirrored E2E tests, future
```

- **Unit:** target behavior is isolated; only useful boundary doubles are centralized.
- **Interaction:** real CLI/option/output/provider interactions are exercised with Testbench.
- **Business:** real validator/indexer behavior and explicitly unfinished scaffolding/impact shells are exercised without fake success.
- **Regression:** reserved for actual external/data E2E verification, not fake placeholder assertions.

When scaffolding is implemented, its generated tests must **explicitly fail until assertions are authored**. Manually authored Phase 1–3 tests contain real assertions about actual parser, indexer, YAML and CLI behavior.

## Running tests

In a Laravel application: `php artisan test` (the documented execution interface).

To develop MATMA itself, install Composer dependencies and run `composer test`. The repository additionally includes a minimal Laravel 11 consumer under `tests/fixtures/laravel-app/` which installs MATMA through a Composer path repository and runs real `php artisan test` tests. GitHub Actions exercises both the package's PHPUnit suites and this native Laravel smoke test. [Phase 3 CI run 42291922018](https://github.com/rzepeda/matma-php/actions/runs/42291922018) passed all six jobs: five PHP/Laravel package combinations (71 package tests, 260 assertions in the checked job) plus the Laravel 11 consumer's `php artisan test` (3 tests, 14 assertions).

## Planned phases

See [TASKS.md](AIDocumentation/TASKS.md). Phase 3 is complete. Phase 4 adds non-destructive test/mock scaffolding; Phase 5 implements manifest-only impact queries. Business/regression orchestration remains separate.
