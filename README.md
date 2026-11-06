# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

MATMA is an agent-oriented testing architecture for PHP/Laravel. It preserves the host project's PSR-4 conventions and places mirrored tests and centralized mocks under the project's `tests/` directory, with dependency manifests generated on demand rather than committed.

## Implementation status

**Phases 1–4 are implemented and CI-verified.** MATMA discovers PHP symbols, validates Laravel/Composer PSR-4 naming, builds deterministic dependency indexes, and safely scaffolds missing unit/interaction tests and centralized PHPUnit mock factories. **`matma:index`, `matma:validate`, and `matma:scaffold` work.** Only `matma:impact` remains unimplemented (Phase 5). Generated tests explicitly fail until their assertions are written, and MATMA never alters application source.

Architecture: [accepted ADR](AIDocumentation/ARCHITECTURE_DECISIONS.md) · [task checklist](AIDocumentation/TASKS.md) · [Phase 1 implementation details](AIDocumentation/PHASE_1_IMPLEMENTATION.md) · [Phase 2 implementation details](AIDocumentation/PHASE_2_IMPLEMENTATION.md) · [Phase 3 implementation details](AIDocumentation/PHASE_3_IMPLEMENTATION.md) · [Phase 4 implementation details](AIDocumentation/PHASE_4_IMPLEMENTATION.md)

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

## Artisan CLI: indexing, scaffolding and validation

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target=App\\Services\\DocumentParser
php artisan matma:impact --target=App\\Services\\DocumentParser --level=2 --format=yaml
php artisan matma:validate --source-root=app --check --format=json
```

**`matma:index --scaffold` and `matma:scaffold` now generate missing mirrored test and mock files** and regenerate the complete dependency index. `matma:validate` is read-only. `matma:impact` is the only unimplemented command. Generated tests fail intentionally, and scaffolding never overwrites existing PHP test/mock files.

| Command | Current status / purpose |
| --- | --- |
| `matma:index` | **Implemented:** AST graph, reverse dependents, safe YAML regeneration, optional `--scaffold` |
| `matma:scaffold` | **Implemented:** missing failing unit/interaction tests and centralized PHPUnit mock factories |
| `matma:impact` | Pending Phase 5: manifest-only reverse-dependency test selection |
| `matma:validate` | **Implemented:** read-only AST discovery, PSR-4 audit and source-mirror mapping |

Shared flags: `--source-root/-s` (default `app/`), `--tests-root/-t` (`tests/`), `--scaffold/-S` (boolean), `--level/-l` (nonnegative integer, default 0), `--target/-f`, `--check/-c` (boolean), and `--format/-o` (`text`, `json`, or `yaml`).

| Exit code | Meaning |
| --- | --- |
| `0` | Index/scaffolding completed successfully, or validation finished (possibly with a non-strict diagnostic report) |
| `1` | Invalid source/target, unsafe output, strict validation failure, or incomplete scaffolding due to non-mockable dependencies |
| `2` | Only unimplemented `matma:impact` |
| `64` | Invalid MATMA flag value (for example negative `--level`) |

The response includes `command`, `status`, `message`, `exit_code`, and valid `options`. Validation adds `summary`, `files`, `symbol_to_source`, and line-level `diagnostics`. Index output adds `summary`, `index_fingerprint`, `target`, and `manifests` (written, unchanged and removed). JSON is intended for agents; `--format=text|json|yaml` works. `validate --check` fails on violations; indexing always refuses invalid source graphs to avoid storing misleading data.

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:index --target='App\\Services\\DocumentParser' --format=yaml
```

Indexing or scaffolding always rebuilds the **whole** reverse-dependency graph, even with `--target`, to avoid inconsistent dependents. `--target` identifies the requested source in the command response. Generated `tests/mirror/**/*.auto.yaml` are ignored by Git. MATMA never overwrites a YAML file lacking its generated ownership header, and removes stale YAML only when MATMA owns it. Re-running an unchanged index does not rewrite byte-identical manifests. External/unresolved references and dynamic references are explicitly identified; they are not invented as internal dependencies. See [Phase 3](AIDocumentation/PHASE_3_IMPLEMENTATION.md).

## Manual test and mock topology

MATMA now generates missing mirrored **test and mock** files. The package's own unit, interaction and business tests were authored manually to validate the scaffolder; generated consumer test files intentionally fail until authored. Production PHP classes retain Laravel's PascalCase names; tests use PHPUnit's `*Test.php` suffix:

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
- **Business:** actual validation/index/scaffold behavior is tested; only the impact query remains unimplemented.
- **Regression:** reserved for actual external/data E2E verification, not fake placeholder assertions.

Generated tests contain `self::fail('MATMA: implement ...')` and can be executed explicitly with `php artisan test tests/unit/mirror/<path>/ClassTest.php`. The scaffolder does not modify a Laravel project's existing PHPUnit suite configuration; default `php artisan test` discovery may need the generated lower-case mirror directories explicitly configured. Mock factories require real PHPUnit doubles instead of fake passing implementations. See [Phase 4](AIDocumentation/PHASE_4_IMPLEMENTATION.md).

## Running tests

In a Laravel application: `php artisan test` (the documented execution interface).

To develop MATMA itself, install Composer dependencies and run `composer test`. The repository additionally includes a minimal Laravel 11 consumer under `tests/fixtures/laravel-app/` which installs MATMA through a Composer path repository and runs real `php artisan test` tests. GitHub Actions exercises both the package's PHPUnit suites and this native Laravel smoke test. [Phase 4 CI run 42294621231](https://github.com/rzepeda/matma-php/actions/runs/42294621231) passed all six jobs: five PHP/Laravel package combinations (88 package tests, 373 assertions in the checked job) plus the Laravel 11 consumer's `php artisan test` (4 tests, 24 assertions, including a real intentionally failing generated stub).

## Planned phases

See [TASKS.md](AIDocumentation/TASKS.md). Phases 1–4 are complete; Phase 5 implements manifest-only impact queries and bounded verification-tier selection. Business/regression orchestration remains separate.
