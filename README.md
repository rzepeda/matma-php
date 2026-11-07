# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

MATMA is an agent-oriented testing architecture for PHP/Laravel. It preserves the host project's PSR-4 conventions and places mirrored tests and centralized mocks under the project's `tests/` directory, with dependency manifests generated on demand rather than committed.

## Implementation status

**Phases 1–6 are implemented and CI-verified.** MATMA discovers and validates PHP symbols, builds deterministic dependency manifests, safely scaffolds explicitly failing unit/interaction tests and centralized PHPUnit mock factories, and selects verification through read-only `matma:impact`. All four Artisan commands work; none rewrites production PHP or automatically executes tests. **Release preparation is complete, but no version tag, GitHub Release, or Packagist publication has been created.**

Architecture: [accepted ADR](AIDocumentation/ARCHITECTURE_DECISIONS.md) · [task checklist](AIDocumentation/TASKS.md) · [Phase 1 implementation details](AIDocumentation/PHASE_1_IMPLEMENTATION.md) · [Phase 2 implementation details](AIDocumentation/PHASE_2_IMPLEMENTATION.md) · [Phase 3 implementation details](AIDocumentation/PHASE_3_IMPLEMENTATION.md) · [Phase 4 implementation details](AIDocumentation/PHASE_4_IMPLEMENTATION.md) · [Phase 5 implementation details](AIDocumentation/PHASE_5_IMPLEMENTATION.md) · [Phase 6 implementation details](AIDocumentation/PHASE_6_IMPLEMENTATION.md) · [adoption guide](AIDocumentation/ADOPTION_GUIDE.md) · [release checklist](AIDocumentation/RELEASE_CHECKLIST.md) · [changelog](CHANGELOG.md)

## Compatibility

- PHP **8.2+**.
- Laravel **11 onward**, with per-version CI matrices (the Laravel version's own minimum PHP requirement still applies).
- Composer development dependency with Laravel package discovery. No production file changes.
- PHPUnit **or** Pest projects can continue to use Laravel's native `php artisan test`. MATMA generates PHPUnit-compatible tests; its tested baseline is PHPUnit 11, not a separately certified Pest version matrix.

## Installation

The repository is currently private and is **not published on Packagist**. From a Laravel application with GitHub access to the repository:

```bash
composer config repositories.matma-php vcs https://github.com/rzepeda/matma-php
composer require --dev rzepeda/matma-php:dev-main
php artisan list --raw
```

Laravel's Composer discovery registers `Matma\Php\MatmaServiceProvider`. If package discovery is disabled, add `Matma\Php\MatmaServiceProvider::class` manually to the application's `bootstrap/providers.php`.

No public distribution or license grant is implied: package metadata currently uses `proprietary`.

## Artisan CLI: index, scaffold, validate and impact

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target=App\\Services\\DocumentParser
php artisan matma:impact --target=App\\Services\\DocumentParser --level=2 --format=yaml
php artisan matma:validate --source-root=app --check --format=json
```

**`matma:index --scaffold` and `matma:scaffold` create only missing mirrored tests/mocks** and regenerate the index. `matma:validate` audits source without changing files. **`matma:impact` reads persisted YAML and reports selected tests without running them.** New test templates explicitly fail until authored, and scaffolding never overwrites existing PHP test/mock files.

| Command | Current status / purpose |
| --- | --- |
| `matma:index` | **Implemented:** AST graph, reverse dependents, safe YAML regeneration, optional `--scaffold` |
| `matma:scaffold` | **Implemented:** missing failing unit/interaction tests and centralized PHPUnit mock factories |
| `matma:impact` | **Implemented:** manifest-only reverse-dependency selection, inclusive levels, missing/stale diagnostics, registered workflows |
| `matma:validate` | **Implemented:** read-only AST discovery, PSR-4 audit and source-mirror mapping |

Shared flags: `--source-root/-s` (default `app/`), `--tests-root/-t` (`tests/`), `--scaffold/-S` (boolean), `--level/-l` (nonnegative integer, default 0), `--target/-f`, `--check/-c` (boolean), and `--format/-o` (`text`, `json`, or `yaml`).

| Exit code | Meaning |
| --- | --- |
| `0` | Successful indexing, scaffolding, impact selection, or validation (non-strict may still report violations) |
| `1` | Invalid/stale/missing index, unsafe output, invalid target, strict validation failure, or incomplete scaffolding |
| `2` | Reserved for unimplemented future operations; all four current commands have engines |
| `64` | Invalid MATMA flag value (for example negative `--level`) |

The response includes `command`, `status`, `message`, `exit_code`, and valid `options`. Validation adds `summary`, `files`, `symbol_to_source`, and line-level `diagnostics`. Index output adds `summary`, `index_fingerprint`, `target`, and `manifests` (written, unchanged and removed). JSON is intended for agents; `--format=text|json|yaml` works. `validate --check` fails on violations; indexing always refuses invalid source graphs to avoid storing misleading data.

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:index --target='App\\Services\\DocumentParser' --format=yaml
```

Indexing or scaffolding always rebuilds the **whole** reverse-dependency graph, even with `--target`, to avoid inconsistent dependents. `--target` identifies the requested source in the command response. Generated `tests/mirror/**/*.auto.yaml` are ignored by Git. MATMA never overwrites a YAML file lacking its generated ownership header, and removes stale YAML only when MATMA owns it. Re-running an unchanged index does not rewrite byte-identical manifests. External/unresolved references and dynamic references are explicitly identified; they are not invented as internal dependencies. See [Phase 3](AIDocumentation/PHASE_3_IMPLEMENTATION.md).

### Impact query and registered workflows

```bash
php artisan matma:index --source-root=app --tests-root=tests
php artisan matma:impact --target='App\Services\DocumentParser' --level=2 --format=json
php artisan test tests/unit/mirror/Services/DocumentParserTest.php
```

`--level 0` selects target unit verification; `--level 1` adds direct dependents and interaction verification; `--level 2+` adds deeper reverse dependents inclusively. The command returns sorted `affected_sources`, `tests.unit`, `tests.interaction`, `missing_tests`, and separately `registered_workflows`. It does **not** execute `php artisan test`.

A generated `tests/mirror/.index.auto.yaml` companion enables stale/missing checks without AST reparsing. The query hashes known indexed source files for freshness, so its total filesystem I/O is not constant-time even though reverse-dependency selection uses the single target manifest. To register non-mirrored workflows explicitly, create a user-authored `tests/matma-verification.yaml` containing `business` and `regression` registrations. See [Phase 5](AIDocumentation/PHASE_5_IMPLEMENTATION.md) for the schema, level selection and error codes.

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
- **Business:** actual command behavior is tested; additional workflows are reported only when explicitly registered, not automatically run.
- **Regression:** reserved for actual external/data E2E verification, not fake placeholder assertions.

Generated tests contain `self::fail('MATMA: implement ...')` and can be executed explicitly with `php artisan test tests/unit/mirror/<path>/ClassTest.php`. The scaffolder does not modify a Laravel project's existing PHPUnit suite configuration; default `php artisan test` discovery may need the generated lower-case mirror directories explicitly configured. Mock factories require real PHPUnit doubles instead of fake passing implementations. See [Phase 4](AIDocumentation/PHASE_4_IMPLEMENTATION.md).

## Full Laravel consumer walkthrough

The fixture application now includes `App\Services\SmokeService` and the dependent `App\Services\SmokeConsumer`. From `tests/fixtures/laravel-app` after Composer installation:

```bash
php artisan matma:index --format=json
php artisan matma:scaffold --target='App\Services\SmokeConsumer' --format=json
php artisan matma:validate --check --format=json
php artisan matma:impact --target='App\Services\SmokeService' --level=1 --format=json
php artisan test tests/unit/mirror/Services/SmokeConsumerTest.php
```

The last command is **expected to fail until assertions are authored**. MATMA-generated stubs never report false success. The fixture's own feature test verifies this deliberate failure and confirms that rerunning scaffolding preserves developer edits.

## Running tests

In a Laravel application: `php artisan test` (the documented execution interface).

To develop MATMA itself, install Composer dependencies and run `composer test`, `composer analyse` (PHPStan level 0) and `composer style:check` (focused PHPCS rules, not full PSR-12). The real Laravel 11 consumer in `tests/fixtures/laravel-app/` installs MATMA via Composer path repository and runs `php artisan test`. [Phase 6 CI run 42336822288](https://github.com/rzepeda/matma-php/actions/runs/42336822288) passed **all seven jobs**: five PHP/Laravel package combinations, the Laravel consumer and Windows/PHP 8.3/Laravel 11. The checked package and Windows jobs each passed **116 tests with 502 assertions**; the Laravel consumer passed **6 tests with 59 assertions**.

## Benchmark and release preparation

The reproducible `benchmarks/benchmark.php` measures AST indexing + YAML writes separately from manifest-based impact selection + indexed-source hash checks. The Phase 6 CI job retains a JSON benchmark artifact. A representative 100-class, five-sample Linux run measured **122.537 ms** indexing versus **7.321 ms** impact lookup (16.737× on that runner only; not a performance guarantee). Run `php benchmarks/benchmark.php --classes=100 --iterations=5` locally to compare your environment. Impact still hashes known source files and does not provide constant-time end-to-end I/O.

[ADOPTION_GUIDE.md](AIDocumentation/ADOPTION_GUIDE.md) covers migrating existing suites, native Laravel naming and troubleshooting. [RELEASE_CHECKLIST.md](AIDocumentation/RELEASE_CHECKLIST.md) proposes **v0.1.0** and documents approval gates, tag creation and optional Packagist publication. **No tag or distribution has been published**, and the repository remains private with a proprietary license. Pest-specific version certification remains a separate compatibility item.

See [TASKS.md](AIDocumentation/TASKS.md) for Phase 6 acceptance and remaining Phase 0 compatibility details.
