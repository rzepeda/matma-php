# MATMA PHP — Laravel adoption and troubleshooting

**Target:** PHP 8.2+, Laravel 11 and compatible newer Laravel releases. Existing project structure, application code, and PHPUnit/Pest runner remain in control of the host application.

## 1. Install as a development dependency

MATMA is currently a private GitHub repository, not a published Packagist package. Configure its VCS repository in your Laravel application:

```bash
composer config repositories.matma-php vcs https://github.com/rzepeda/matma-php
composer require --dev rzepeda/matma-php:dev-main
php artisan list --raw
```

Laravel package auto-discovery normally registers `Matma\Php\MatmaServiceProvider`. If discovery has been disabled, add that provider to the consuming application's provider registration. Do **not** manually copy MATMA commands into the host's `app/` tree.

For reproducible production rollouts, pin a reviewed tag/commit rather than leaving a moving `dev-main` requirement; see [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md). Do not copy the Laravel 11 **CI-only** Composer advisory override into an application.

## 2. Index, then selectively scaffold

The following sequence operates on the host's existing PSR-4 PascalCase classes:

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target='App\Services\DocumentParser' --format=json
php artisan matma:validate --source-root=app --check --format=json
php artisan matma:impact --target='App\Services\DocumentParser' --level=2 --format=json
```

Index writes `tests/mirror/**/*.auto.yaml` and a `tests/mirror/.index.auto.yaml` companion, all regenerable and Git-ignored. Scaffolding never overwrites existing test/mock PHP files, even generated ones. A targeted scaffold creates the target's missing unit and interaction tests and mockable direct dependency factories, without rewriting production classes.

The generated unit and interaction stubs intentionally contain `self::fail('MATMA: implement ...')`; a test is *not finished* until the developer replaces that with behavioral assertions. Use the host's only documented execution entry point, **`php artisan test`**, to run tests.

## 3. Preserve existing PHPUnit/Pest test suites

MATMA does not move existing tests, replace Pest, or edit `phpunit.xml`. If you want the ordinary `php artisan test` invocation to discover newly scaffolded mirrored tests, explicitly add their lower-case paths in the project's existing PHPUnit suite configuration (or adapt the equivalent Pest configuration):

```xml
<testsuite name="MATMA unit">
    <directory suffix="Test.php">tests/unit/mirror</directory>
</testsuite>
<testsuite name="MATMA interaction">
    <directory suffix="Test.php">tests/interaction/mirror</directory>
</testsuite>
```

You can instead run a single test by path without modifying the project suite configuration:

```bash
php artisan test tests/unit/mirror/Services/DocumentParserTest.php
```

Centralized mocks reside in `tests/mocks/mirror`. A generated `MockDocumentParser` factory creates actual PHPUnit `MockObject` instances when passed a caller-supplied `createMock` closure. Final/readonly/anonymous classes are not silently mocked: scaffolding returns `scaffold_incomplete` (exit 1) with diagnostics while leaving production untouched.

## 4. Run impact selection without executing tests

```bash
php artisan matma:impact --target='App\Services\DocumentParser' --level=0 --format=json
php artisan matma:impact --target='app/Services/DocumentParser.php' --level=1 --format=yaml
```

- Level 0: target unit test only.
- Level 1: target and immediate dependents' unit tests, plus relevant interaction tests.
- Level 2+: inclusively expand to deeper reverse-dependent classes by shortest path.
- `missing_tests` lists not-yet-created or unavailable files: this is informational, not evidence that tests ran or passed.
- `registered_workflows` contains *advisory* business/regression tests registered in a user-authored `tests/matma-verification.yaml`. MATMA does not add them to mirrored test sets or run them automatically.

See [PHASE_5_IMPLEMENTATION.md](PHASE_5_IMPLEMENTATION.md) for the registry YAML schema. Use `php artisan test <path>` explicitly for registered workflows.

## 5. Example application: all four commands

A minimal, real Laravel 11 consumer lives at `tests/fixtures/laravel-app`. It contains `App\Services\SmokeService` and a dependent `App\Services\SmokeConsumer`; the latter invokes the former via constructor injection. Its PHPUnit feature test runs the full sequence and verifies that the generated test intentionally fails.

```bash
cd tests/fixtures/laravel-app
composer install
php artisan matma:index --format=json
php artisan matma:scaffold --target='App\Services\SmokeConsumer' --format=json
php artisan matma:validate --check --format=json
php artisan matma:impact --target='App\Services\SmokeService' --level=1 --format=json
php artisan test tests/unit/mirror/Services/SmokeConsumerTest.php
```

The final `php artisan test` invocation **must fail on purpose** until the sample test is authored. For CI, the fixture's existing `php artisan test` suite verifies this failure as an expected outcome and passes when the assertion is correct. The preceding commands demonstrate that the impact result selects both the source class and the direct dependent at depth 1.

## 6. Troubleshooting

| Symptom | Explanation and next action |
| --- | --- |
| `matma:impact` says `missing_manifest` or `missing_index` | Run `php artisan matma:index` with the same source/tests roots before querying. |
| `stale_index` | A source hash, source directory mtime, or index fingerprint changed. Reindex explicitly; impact never invokes an AST parser. |
| `invalid_manifest` | Generated YAML is malformed/unowned/incompatible. Remove only the verified generated artifact, then regenerate; never delete hand-written YAML blindly. |
| `invalid_target` for FQCN | Confirm Composer PSR-4 mapping and exact PascalCase path, or use `--target=app/.../Class.php`. |
| `validation_failed` | Resolve parse errors, filename/namespace mismatch, multiple primary declarations, or duplicate symbols before indexing. |
| `scaffold_incomplete` | Final, readonly, or anonymous classes cannot safely get a conventional mock. Author assertions using supported seams rather than rewriting production. |
| Generated test absent from `php artisan test` discovery | Add `tests/unit/mirror` and `tests/interaction/mirror` to the project's test suite config, or run the specific file by path. |
| Existing tests are unexpectedly unchanged | This is intentional: MATMA never overwrites existing PHP tests/mocks. |
| Slow impact lookup | Cached reverse-dependency selection is bounded, but freshness checks hash every known indexed source file; benchmark the two steps separately. |
| Laravel 11 dependencies blocked by an advisory | Legacy Laravel compatibility is CI-tested with a **test-only** Composer policy override. Upgrade the application/framework or resolve advisories appropriately; do not disable production security policy. |

## Files that must remain developer-owned

The application source tree, existing tests, existing mocks, `composer.json`, `phpunit.xml`, and `tests/matma-verification.yaml` remain developer-authored. MATMA owns only its generated YAML files and will never overwrite an existing PHP test or mock. Native Laravel conventions take precedence over blueprint naming examples.
