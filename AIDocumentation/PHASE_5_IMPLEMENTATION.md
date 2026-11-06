# MATMA PHP — Phase 5: Manifest-Only Impact Queries

**Status:** Implemented; CI-verified on 2026-11-06.
**Acceptance tasks:** P5-01 through P5-05 in [TASKS.md](TASKS.md).
**CI evidence:** https://github.com/rzepeda/matma-php/actions/runs/42299323812

## Goal and index-first architecture

`matma:impact` selects source files and mirrored tests using the **precomputed reverse-dependent levels** of a single class-file YAML manifest. It never calls the PHP AST parser, discovers classes, regenerates manifests, or executes a test. Querying a class does not require reading every dependent's manifest or traversing a graph.

The Phase 3 indexer and Phase 4 scaffolder now also generate a small companion `tests/mirror/.index.auto.yaml` file with the shared fingerprint, source-file SHA-256 hashes, and indexed directory modification times. An impact query reads **two generated YAML files** (target manifest plus companion metadata), plus an optional user-authored registry. It checks known source-file hashes and indexed directory timestamps for freshness; **no AST traversal or recursive filesystem search** occurs during impact selection.

**Performance tradeoff:** The selection itself is a bounded lookup in one target manifest. Freshness verification hashes all known indexed PHP files and stats recorded directories, so the full command is not strictly O(1) in filesystem I/O. Directory timestamps may fail to detect a new file if an external process preserves/rewrites timestamps within the filesystem's time resolution. Run `matma:index` after adding or removing classes; Phase 6 should benchmark index and impact separately. MATMA deliberately prefers refusing stale data over silently selecting wrong tests.

## Commands

~~~bash
php artisan matma:index --source-root=app --tests-root=tests
php artisan matma:impact --target='App\Services\DocumentParser' --level=0 --format=json
php artisan matma:impact --target='App\Services\DocumentParser' --level=1 --format=text
php artisan matma:impact --target='app/Services/DocumentParser.php' --level=2 --format=yaml
php artisan test tests/unit/mirror/Services/DocumentParserTest.php
~~~

**`--target` is mandatory**. It accepts a source file (source-root-relative, project-relative, or absolute) or a Composer PSR-4 FQCN. FQCNs are converted to source file paths mathematically using the host project's `composer.json`; ambiguous/unmapped classes require a file target instead. `--source-root` and `--tests-root` must correspond to those used during indexing.

`--level` is a nonnegative integer and specifies the **inclusive shortest-path reverse-dependency radius**:

| Level | Mirrored test selection |
| --- | --- |
| `0` | Target's unit test only (when target is testable) |
| `1` | Level 0 plus direct dependents' unit tests, and target/direct-dependent interaction tests |
| `2+` | All lower levels plus unit and interaction tests of dependents at each greater depth |

Traits/enums remain dependency graph nodes but have no standalone mirrored tests. For anonymous classes, the containing source filename supplies the test identity. Cycles are deduplicated, each dependent appears at its shortest depth, and selected paths are sorted.

## Stable output contract

`--format=json|yaml|text` returns the same selection. JSON/YAML contain:

- `command`, `status`, `message`, `exit_code`, `options` (existing MATMA command contract).
- `target`: class_name, source_file, kind, manifest.
- `level`, `index_fingerprint`, `affected_sources` (ordered by depth and source path).
- `tests.unit`, `tests.interaction`: deduplicated paths, never executed by the command.
- `missing_tests`: selected mirrored test files not present yet; run `matma:scaffold` to create failing stubs.
- `registered_workflows.business`, `.regression`: separately registered, matched workflows for possible **explicit** test execution. These are *not* in the mirrored test lists.
- `summary`: source/test counts, missing test count, and registered workflow counts.

Successful selection returns `status: selected`, exit code `0`. Missing test files do not make impact analysis fail: missing files are separately reported rather than falsely claimed to have passed. Missing/invalid/stale manifests and invalid registry data fail with exit `1` and actionable statuses: `missing_manifest`, `missing_index`, `invalid_manifest`, `stale_index`, `invalid_target`, or `invalid_registry`. Missing `--target` and a forbidden `--scaffold` flag fail with exit `64` (`invalid_options`). Impact freshness checks run regardless of `--check`.

## Explicit business/regression workflow registry

Create the following **user-authored** file; it is not generated or overwritten by MATMA:

~~~text
tests/matma-verification.yaml
~~~

~~~yaml
schema_version: 1
business:
  - id: document-processing-flow
    sources:
      - app/Services/DocumentParser.php
      - 'App\Services\DocumentParser'
    tests:
      - tests/business/DocumentProcessingWorkflowTest.php
regression:
  - id: external-parser-e2e
    sources:
      - app/Services/DocumentParser.php
    tests:
      - tests/regression/ExternalParserRegressionTest.php
~~~

A registry entry becomes visible when one of its explicit `sources` matches an affected source file or FQCN within the chosen impact level. Each `tests` path must stay under the matching `tests/business/` or `tests/regression/` tier and end in `Test.php`. Duplicate/unsafe registrations are errors. Registry paths and IDs are sorted deterministically. Registered workflows are **advisory selections** only: increasing `--level` does not automatically pull every business/regression test into the mirrored unit/interaction list, nor does MATMA execute them. The host uses `php artisan test <workflow-test.php>` to run a selected workflow explicitly.

## Implementation and test evidence

| File | Responsibility |
| --- | --- |
| `src/Impact/ManifestLocator.php` | Exact file/FQCN to persisted YAML mapping with Composer PSR-4, no class loading or glob |
| `src/Impact/ManifestReader.php` | Safe YAML reading, schema/ownership validation, fingerprint/known-source freshness checks |
| `src/Impact/ImpactResolver.php` | Inclusive shortest-path selection, stable sorting/deduplication, missing-test reporting |
| `src/Impact/VerificationRegistry.php` | Validates separate user-authored workflow metadata and reports matched registrations |
| `src/Impact/ImpactException.php` | Structured and actionable CLI error statuses |
| `src/Indexing/IndexMetadata.php` | Generates shared index freshness metadata during the AST indexing phase |
| `src/Console/Commands/ImpactCommand.php` | Read-only Artisan entry point and machine-readable responses |

Unit coverage in `tests/unit/mirror/Impact/` checks levels 0/1/2+, cycles, traits/enums, anonymous files, FQCN paths, malformed/absent/stale indexes, untrusted workflow registry paths, and that malformed PHP source is **not parsed** during a trusted manifest lookup. Interaction coverage in `tests/interaction/mirror/Console/Commands/ImpactCommandInteractionTest.php` indexes real fixtures first and queries real YAML using Artisan. A Laravel 11 consumer test resolves `App\Services\SmokeService` through its real Composer PSR-4 mapping, then verifies selection through `php artisan test`.

CI: [run 42299323812](https://github.com/rzepeda/matma-php/actions/runs/42299323812) passed **all six jobs**: PHP 8.2/Laravel 11, PHP 8.3/Laravel 11, PHP 8.3/Laravel 12, PHP 8.4/Laravel 12, PHP 8.4/Laravel 13, and the Laravel 11 native consumer. The checked package job passed **112 tests and 484 assertions**; the consumer passed **5 tests and 35 assertions**.

## Next phase

Phase 6 covers release readiness: cross-platform compatibility hardening, end-to-end `index → scaffold → validate → impact` walkthroughs, performance measurement of indexing versus impact lookup, and release packaging. Native Pest DSL generation is not claimed and is a separate optional enhancement.
