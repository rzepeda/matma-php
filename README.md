# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

MATMA is an agent-oriented testing architecture for PHP/Laravel. It preserves the host project's PSR-4 conventions and places mirrored tests and centralized mocks under the project's `tests/` directory, with dependency manifests generated on demand rather than committed.

## Implementation status

**Phase 1 package foundation is implemented and CI-verified.** Composer metadata, Laravel provider auto-discovery, four Artisan command **shells**, common flags, diagnostic serializers, and manually authored tests/mocks are present. **The AST indexer, source validation, scaffold generator, and impact resolver are not implemented yet.** Running the four shells returns nonzero exit code `2` with `not_implemented`; they do not write manifests or alter source files.

Architecture: [accepted ADR](AIDocumentation/ARCHITECTURE_DECISIONS.md) · [task checklist](AIDocumentation/TASKS.md) · [Phase 1 implementation details](AIDocumentation/PHASE_1_IMPLEMENTATION.md)

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

## Command interface (Phase 1 shells)

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target=App\\Services\\DocumentParser
php artisan matma:impact --target=App\\Services\\DocumentParser --level=2 --format=yaml
php artisan matma:validate --check
```

These invocations currently **do not index, scaffold, analyze impact or validate production code**.

| Command | Future responsibility |
| --- | --- |
| `matma:index` | Parse PHP ASTs, build forward/reverse dependency indexes, persist `.auto.yaml` manifests |
| `matma:scaffold` | Create missing mirrored test and centralized mock files without overwriting existing work |
| `matma:impact` | Use the persisted reverse-dependency index to select tests at an inclusive `--level` |
| `matma:validate` | Audit class-file and mirror conventions without changing production code |

Shared flags: `--source-root/-s` (default `app/`), `--tests-root/-t` (`tests/`), `--scaffold/-S` (boolean), `--level/-l` (nonnegative integer, default 0), `--target/-f`, `--check/-c` (boolean), and `--format/-o` (`text`, `json`, or `yaml`).

| Exit code | Meaning |
| --- | --- |
| `2` | Valid command invocation, operation not implemented yet |
| `64` | Invalid MATMA flag value (for example negative `--level`) |

The standard shell response includes `command`, `status`, `message`, `exit_code`, and (for valid flags) `options`. JSON output is intended for coding agents.

## Manual test and mock topology

During Phase 1 the mirrored tree is built **manually**, because `matma:scaffold` does not exist yet. Production PHP classes follow native PascalCase names; tests use PHPUnit's `*Test.php` suffix:

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
- **Business:** the four command shells are exercised as a workflow; no fabricated successful index or test run.
- **Regression:** reserved for actual external/data E2E verification, not fake placeholder assertions.

When scaffolding is implemented, its generated tests must **explicitly fail until assertions are authored**. The manual Phase 1 tests instead contain real, meaningful assertions about existing shell behavior.

## Running tests

In a Laravel application: `php artisan test` (the documented execution interface).

To develop MATMA itself, install Composer dependencies and run `composer test`. The repository additionally includes a minimal Laravel 11 consumer under `tests/fixtures/laravel-app/` which installs MATMA through a Composer path repository and runs real `php artisan test` tests. GitHub Actions exercises both the package's PHPUnit suites and this native Laravel smoke test. [CI run 42277378877](https://github.com/rzepeda/matma-php/actions/runs/42277378877) passed all six jobs: five compatibility-matrix jobs (24 tests and 98 assertions each) plus the Laravel 11 consumer's `php artisan test` (3 tests and 10 assertions).

## Planned phases

See [TASKS.md](AIDocumentation/TASKS.md). Later phases add AST symbol discovery, deterministic mirrored YAML generation, centralized mock/test scaffolding, impact queries and additional business/regression integration.
