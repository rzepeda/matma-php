# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

MATMA is an agent-oriented testing architecture for PHP/Laravel. It preserves the host project's PSR-4 conventions and places mirrored tests and centralized mocks under the project's `tests/` directory, with dependency manifests generated on demand rather than committed.

## Implementation status

**Phases 1 and 2 are implemented and CI-verified.** MATMA now discovers PHP symbols through an AST, validates Laravel/Composer PSR-4 naming, detects structural violations, and computes mirrored test/mock/manifest paths without creating them. **`matma:validate` is working.** `matma:index`, `matma:scaffold`, and `matma:impact` still return exit code 2 (`not_implemented`) until Phases 3–5. No Phase 2 operation modifies application files.

Architecture: [accepted ADR](AIDocumentation/ARCHITECTURE_DECISIONS.md) · [task checklist](AIDocumentation/TASKS.md) · [Phase 1 implementation details](AIDocumentation/PHASE_1_IMPLEMENTATION.md) · [Phase 2 implementation details](AIDocumentation/PHASE_2_IMPLEMENTATION.md)

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

## Artisan CLI: Phase 2 validator and future commands

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target=App\\Services\\DocumentParser
php artisan matma:impact --target=App\\Services\\DocumentParser --level=2 --format=yaml
php artisan matma:validate --source-root=app --check --format=json
```

**Only `matma:validate` currently performs its advertised operation.** It audits PHP ASTs and reports diagnostics without changing any source, test, mock or manifest files.

| Command | Current status / purpose |
| --- | --- |
| `matma:index` | Pending Phase 3: dependency graph and `.auto.yaml` persistence |
| `matma:scaffold` | Pending Phase 4: non-destructive test/mock generation |
| `matma:impact` | Pending Phase 5: manifest-only reverse-dependency test selection |
| `matma:validate` | **Implemented:** read-only AST discovery, PSR-4 audit and source-mirror mapping |

Shared flags: `--source-root/-s` (default `app/`), `--tests-root/-t` (`tests/`), `--scaffold/-S` (boolean), `--level/-l` (nonnegative integer, default 0), `--target/-f`, `--check/-c` (boolean), and `--format/-o` (`text`, `json`, or `yaml`).

| Exit code | Meaning |
| --- | --- |
| `0` | Validator finished (with zero errors, or a non-strict diagnostic report) |
| `1` | Strict validation violations, source-root or target missing |
| `2` | Valid command invocation for an engine not yet implemented |
| `64` | Invalid MATMA flag value (for example negative `--level`) |

The response includes `command`, `status`, `message`, `exit_code`, and valid `options`. Validation adds `summary`, `files`, `symbol_to_source`, and line-level `diagnostics`. JSON is intended for agents; `--format=text|json|yaml` works. With `--check`, violations exit 1; without `--check`, they are reported with status `violations` and exit 0. Named classes, abstract classes and interfaces are testable; anonymous classes inherit their source-file test identity. Traits and enums are discoverable symbols but not standalone test targets. See [Phase 2](AIDocumentation/PHASE_2_IMPLEMENTATION.md).

## Manual test and mock topology

Through Phase 2 the mirrored tree remains **manually authored**, because `matma:scaffold` does not exist yet. Production PHP classes follow native PascalCase names; tests use PHPUnit's `*Test.php` suffix:

```text
src/Console/MatmaOptions.php
tests/unit/mirror/Console/MatmaOptionsTest.php
tests/interaction/mirror/Console/MatmaCommandInteractionTest.php
tests/mocks/mirror/Console/MockMatmaCommand.php
tests/mirror/                           # future .auto.yaml outputs, ignored by Git
tests/business/                         # non-mirrored workflow tests
tests/regression/                       # non-mirrored E2E tests, future
```

- **Unit:** target behavior is isolated; only useful boundary doubles are centralized.
- **Interaction:** real CLI/option/output/provider interactions are exercised with Testbench.
- **Business:** actual validator checks and explicitly unfinished indexing/scaffolding/impact shells are exercised without fake success.
- **Regression:** reserved for actual external/data E2E verification, not fake placeholder assertions.

When scaffolding is implemented, its generated tests must **explicitly fail until assertions are authored**. The manual Phase 1 tests instead contain real, meaningful assertions about existing shell behavior.

## Running tests

In a Laravel application: `php artisan test` (the documented execution interface).

To develop MATMA itself, install Composer dependencies and run `composer test`. The repository additionally includes a minimal Laravel 11 consumer under `tests/fixtures/laravel-app/` which installs MATMA through a Composer path repository and runs real `php artisan test` tests. GitHub Actions exercises both the package's PHPUnit suites and this native Laravel smoke test. [Phase 2 CI run 42285897847](https://github.com/rzepeda/matma-php/actions/runs/42285897847) passed all six jobs: five PHP/Laravel package combinations (51 package tests) plus the Laravel 11 consumer's `php artisan test`.

## Planned phases

See [TASKS.md](AIDocumentation/TASKS.md). Phase 3 builds persisted reverse-dependency YAML; Phase 4 adds non-destructive scaffolding; Phase 5 implements impact queries. Business/regression orchestration remains separate.
