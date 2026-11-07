# MATMA PHP — Phase 6: integration, quality gates, benchmarks and release preparation

**Implementation date:** 2026-11-07  
**Scope:** P6-01 through P6-06 (see [TASKS.md](TASKS.md)).  
**Acceptance status:** All six Phase 6 tasks implemented and verified. [Seven-job CI run 42336822288](https://github.com/rzepeda/matma-php/actions/runs/42336822288) passed Linux, Windows, and real Laravel consumer checks.  
**Phase 6 workflow:** [MATMA package CI](../.github/workflows/ci.yml).

## Implementation and evidence

| Task | Delivered | Verification |
| --- | --- | --- |
| P6-01 | Existing real command/AST/index/scaffold/impact tests supplemented with four-command output snapshots and failure-mode assertions | `tests/unit/mirror/Console/MatmaOutputSnapshotTest.php`, `tests/fixtures/snapshots/four_commands.txt`; existing unit, interaction and business tiers |
| P6-02 | Five PHP/Laravel Linux package combinations, Laravel 11 consumer, Windows 8.3/Laravel 11, Composer validation, PHP syntax, PHPStan baseline, focused PHP_CodeSniffer, benchmark artifact | GitHub Actions `.github/workflows/ci.yml` |
| P6-03 | Real Laravel 11 app with `SmokeService` and constructor-injected `SmokeConsumer`; full `index → scaffold → validate → impact` test; deliberate failing generated test checked through `php artisan test` | `tests/fixtures/laravel-app/tests/Feature/MatmaInstalledTest.php` |
| P6-04 | VCS Composer installation, provider auto-discovery, native naming, adoption, migration, PHPUnit/Pest compatibility caveats, test execution, troubleshooting | [ADOPTION_GUIDE.md](ADOPTION_GUIDE.md) |
| P6-05 | Bounded synthetic benchmark measuring AST indexing+YAML regeneration separately from persisted YAML impact+freshness checks | `benchmarks/benchmark.php`, `tests/business/BenchmarkHarnessTest.php`, benchmark artifact in CI |
| P6-06 | SemVer proposal, approval gates, changelog, tagging/release steps, Packagist and proprietary licensing caveats | [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md), [CHANGELOG.md](../CHANGELOG.md) |

## Compatibility and quality checks

Supported package policy: PHP **8.2+** and Laravel **11 onward**, constrained by each Laravel release's PHP requirements. The Linux matrix checks PHP 8.2/Laravel 11, PHP 8.3/Laravel 11, PHP 8.3/Laravel 12, PHP 8.4/Laravel 12, and PHP 8.4/Laravel 13, plus a native Laravel 11 `php artisan test` consumer. A separate Windows PHP 8.3/Laravel 11 job checks Composer validation, PHP syntax, and the complete package PHPUnit suite.

Quality commands from the package repository:

```bash
composer validate --strict
composer analyse
composer style:check
composer test
php benchmarks/benchmark.php --classes=100 --iterations=5
```

`composer analyse` runs **PHPStan level 0** on `src/`. `composer style:check` runs a focused PHPCS baseline (lower-case constants, final newline, and superfluous whitespace), **not** a claim of complete PSR-12 adoption. Package developers use `composer test`; the **only documented application test execution interface** remains `php artisan test`. Pest projects remain free to run existing Pest suites, but a separate Pest compatibility matrix and native Pest DSL generation are not part of this release.

## Sample app: complete workflow

Run inside `tests/fixtures/laravel-app` after Composer installation:

```bash
php artisan matma:index --format=json
php artisan matma:scaffold --target='App\Services\SmokeConsumer' --format=json
php artisan matma:validate --check --format=json
php artisan matma:impact --target='App\Services\SmokeService' --level=1 --format=json
php artisan test tests/unit/mirror/Services/SmokeConsumerTest.php
```

The last command **fails intentionally** because the new test contains `self::fail(...)`. This is not evidence of a broken generator: it is MATMA's explicit contract for unimplemented tests. The fixture's own feature test confirms that failure is genuine and that a repeated scaffold preserves a developer-replaced test file. Level 1 impact for `SmokeService` selects `SmokeConsumer` as a direct dependent and lists any missing test paths. MATMA itself never executes selected tests.

## Measured performance, with bounds

The benchmark generates an isolated 100-class chain with Composer PSR-4 naming, performs the real Phase 2–3 AST graph+manifest pass and the real Phase 5 manifest impact query, and reports both medians after warm-up. It uses no fake AST responses or passing mock assertions. A business-tier test verifies the benchmark produces machine-readable measurements and rejects oversized workloads. The CI job stores `benchmark-result.json` as `matma-phase6-benchmark`.

**Observed example** on GitHub Actions PHP 8.3/Laravel 12 (2026-11-07; five samples): AST index + YAML write median **122.537 ms**, manifest impact + source-hash freshness median **7.321 ms**, measured **16.737×** difference on that specific runner. This is **not** a guaranteed speedup. Impact lookup reads target YAML plus generated freshness metadata and hashes the known indexed source files, so it avoids PHP AST parsing but still performs O(N) source-file hashing and directory timestamp checks.

## Release readiness boundaries

Phase 6 prepares a candidate **`v0.1.0`** package; **it does not tag, publish a GitHub Release, or publish to Packagist**. A human maintainer must separately approve publication, choose licensing/visibility and review security advisories. **P0-01 Pest-specific compatibility certification remains open**, so this preparation must not be misrepresented as blanket PHPUnit/Pest version certification. Laravel 11's Composer advisory override is CI-only, not an installation recommendation.

Refer to [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) before any release. The development backlog's Phase 0 compatibility contracts should not be misrepresented as fully closed if Pest-specific versions and all edge-case extensions have not been independently verified.

## Verification record

- Linux CI package matrix and Laravel 11 consumer pass with real, assertion-bearing tests.
- Example checked package job before cross-platform normalization: **116 tests, 502 assertions**; Laravel 11 consumer **6 tests, 59 assertions**.
- Windows path normalization: the repository's source-path helpers handle short aliases and canonical paths consistently; Windows-dependent fixture snapshot and symlink cleanup behavior are checked by the dedicated runner.
- **Final seven-job verification:** [run 42336822288](https://github.com/rzepeda/matma-php/actions/runs/42336822288) succeeded after repairing Windows PowerShell Composer range quoting, short-vs-canonical path aliases, CRLF output snapshots, and directory-symlink cleanup. Windows/PHP 8.3/Laravel 11 passed **116 tests and 502 assertions**.
