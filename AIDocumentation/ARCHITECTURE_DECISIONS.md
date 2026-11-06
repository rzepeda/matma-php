# MATMA PHP — Architecture Decision Record (ADR-001)

**Status:** Accepted architectural direction; implementation contracts listed below remain open.  
**Date:** 2026-11-06  
**Scope:** MATMA implementation for PHP 8.2+ and Laravel 11 onward.  
**Basis:** `matma_architecture_implementation_blueprint.md` and subsequent project decisions.

## Guiding rule

MATMA is a testing methodology and development tool. **It must adapt to the host PHP/Laravel application's conventions and must not require renaming, restructuring, or rewriting production code.** The original MATMA blueprint's snake_case examples are illustrative, not mandatory PHP naming rules.

## Accepted decisions

### ADR-01 — Compatibility and distribution (P0-01, D07)
- Minimum PHP version: **8.2**. Supported framework baseline: **Laravel 11 onward**, subject to per-release compatibility verification.
- Install MATMA as a **Composer development dependency**; register Artisan commands through Laravel package discovery.
- The documented test execution entry point is **`php artisan test`**. Preserve compatibility with a project's existing **PHPUnit or Pest** setup; MATMA does not replace its runner.
- AST engine: `nikic/php-parser`; YAML serialization: `symfony/yaml`.
- **Not yet specified:** final Composer package identifier/type, required PHP extensions, precise PHPUnit/Pest version constraints, and verified Laravel-version CI matrix. Do not mark P0-01 implementation acceptance complete until these are enforced and tested.

### ADR-02 — Native naming and structural mirroring (P0-02, P0-04, D01)
- Preserve Laravel's native **PascalCase class filenames**, PSR-4 namespaces, and the project's existing directory layout.
- Mirror source-relative directories inside the MATMA tiers under `tests/` for unit tests, interaction tests, mocks, and manifests.
- Derive paths deterministically from the configured source root and source-relative path; do not search the repository at impact-query time.
- Example (illustrative mirror naming; final naming/namespace contract must be tested):

```text
app/Services/DocumentParser.php
tests/unit/mirror/Services/TestDocumentParser.php
tests/interaction/mirror/Services/TestDocumentParserInteraction.php
tests/mocks/mirror/Services/MockDocumentParser.php
tests/mirror/Services/DocumentParser.auto.yaml
```

- No automatic renaming of application classes or source files. Case handling, symlink behavior, namespace resolution, exclusions, and traversal order must be specified and tested across supported platforms before declaring P0-04 complete.

### ADR-03 — Testable symbol scope (P0-03)
- In scope: **named classes, abstract classes, interfaces, and anonymous classes**.
- Anonymous classes **inherit the identity of their containing source file for test selection**; do not assign artificial standalone PSR-4 class identities. Multiple anonymous classes in one file may need internal AST identifiers for indexing, without changing the file-level test identity.
- **Open detailed contract:** whether enums and traits are indexed only as dependencies or receive standalone test mirrors, and how multi-declaration files are validated. The scope above does not imply they are standalone test targets.

### ADR-04 — AST dependency graph (D02)
- Index **actual AST references**, not unused `use` imports.
- Include relevant type declarations, inheritance, interface implementations, constructor injection, object creation, static references, and other resolvable class references.
- Use namespace/alias-aware resolution, deduplicate edges, and report unresolved/dynamic references rather than inventing relationships.
- Preserve dependency direction (class → dependencies) and materialize its reverse (class → dependents) during indexing.

### ADR-05 — Impact depth (D03)
- `--level` is an **inclusive reverse-dependency radius** based on shortest-path depth.
- Level 0: target's unit tests.
- Level 1: level 0 plus immediate dependents and relevant interaction tests.
- Level 2+: include progressively deeper transitive dependents, retaining all lower-level selections.
- Handle cycles safely, deduplicate test paths, and produce stable sorted results.

### ADR-06 — Scaffolding and safety (P0-05, D04)
- Generate **only missing** mirrored test and mock files; **never overwrite existing developer-authored files** or rewrite production code.
- Every generated test must **fail explicitly until implemented**, e.g. a PHPUnit `self::fail('Test not implemented')` assertion. Never generate a fake passing or skipped test as proof of coverage.
- Keep mocks centralized under `tests/mocks/mirror/`, respecting existing dependency-injection seams.
- When a dependency cannot be safely mocked (e.g. incompatible final/readonly classes), report the limitation and leave production untouched; do not create a misleading substitute.
- **Open detailed contract:** automatic PHPUnit/Pest stub detection, namespace/autoload behavior, precise mock generation mechanics, and standardized command output/exit codes.

### ADR-07 — Index persistence and regeneration (D05, D09)
- Use deterministic, **regenerable YAML manifests** as the persisted dependency index, located at `tests/mirror/**/*.auto.yaml`.
- **Do not version generated YAML in Git**. Add a targeted ignore rule, leaving hand-written files trackable.
- Indexing performs a deterministic **full rebuild initially**; use sorted output and safe atomic writes. Incremental indexing is not a first-release requirement.
- An impact query consumes persisted manifests and **must not reparse production ASTs**.
- Missing or stale manifests must never silently produce apparently valid impact results.
- **Open detailed contract:** whether `impact` errors on stale/missing manifests or triggers explicit regeneration, fingerprint strategy, and precise stale-file cleanup policy.

### ADR-08 — Business and regression suites (D06)
- Business/workflow and regression/E2E tests are **not source-file mirrored**.
- Associate them with affected components through **explicit registration/metadata**, not filename inference.
- Preserve tier isolation; do not automatically execute all business or regression tests merely because an impact level increases.
- The command's selection output must distinguish mirrored test impact from explicitly registered workflows.

### ADR-09 — CLI and agent integration (D08, D09)
- Retain blueprint commands: `matma:index`, `matma:scaffold`, `matma:impact`, `matma:validate`.
- Preserve blueprint flags: `--source-root/-s`, `--tests-root/-t`, `--scaffold/-S`, `--level/-l`, `--target/-f`, `--check/-c`, `--format/-o` (text/json/yaml).
- **Impact discovery is separate from execution**: `matma:impact` reports deterministic affected tests, not automatic test runs. The host runs tests through `php artisan test`.
- JSON is the preferred machine-readable output for agents; YAML remains the on-disk manifest format.

## Implementation contracts still requiring specification

These are **not accepted choices yet**; do not mistake recommendations for decisions:

1. **Manifest freshness behavior:** fail with an actionable diagnostic (recommended) versus automatically rebuilding when `impact` finds missing/stale data.
2. **Framework-aware scaffolding:** auto-detect Pest/PHPUnit (recommended), default to PHPUnit, or use explicit configuration.
3. **Non-mockable dependency handling:** detailed diagnostic format, failing test behavior, and supported fallback patterns (principle of no production rewrites is already accepted).
4. **Generated-file ownership:** exact `.gitignore` rule, index metadata/fingerprint strategy, and safe cleanup of only MATMA-owned manifests.
5. **Compatibility details:** Composer package identifier/type, extension requirements, supported version ranges for PHPUnit/Pest, and CI enforcement.
6. **PHP symbol edge cases:** enum/trait treatment, multi-declaration files, anonymous-class identifiers, and stable cross-platform path rules.
7. **CLI contracts:** error codes, machine-readable schemas, and how `--target` behaves during a full index rebuild.

## Acceptance and traceability

Architectural decisions are accepted, **but implementation and verification tasks remain open**. P0-01 through P0-05 should only be marked done in `TASKS.md` once their documented acceptance criteria (including Composer constraints, fixtures, and CI where specified) are actually met. No package code, runtime tests, or CI checks are implied by this ADR.

This ADR supersedes the earlier open-recommendations text in `AIDocumentation/ARCHITECTURE_DECISIONS.md`.
