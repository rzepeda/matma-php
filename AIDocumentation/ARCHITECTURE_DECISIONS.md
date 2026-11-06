# MATMA PHP — Architecture Decision Record (ADR-001)

**Status:** Architectural decisions accepted and verified by the sole maintainer; implementation contracts listed below remain open.  
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
- Example (Phase 2's actual PHP test filename mapping):

```text
app/Services/DocumentParser.php
tests/unit/mirror/Services/DocumentParserTest.php
tests/interaction/mirror/Services/DocumentParserInteractionTest.php
tests/mocks/mirror/Services/MockDocumentParser.php
tests/mirror/Services/DocumentParser.auto.yaml
```

- Phase 2 implemented this naming and mirror policy using `MirrorMapper` with real AST validation. No automatic renaming of application classes or source files. Case handling, symlink behavior, namespace resolution, exclusions, and traversal order must be specified and tested across supported platforms before declaring P0-04 complete.

### ADR-03 — Testable symbol scope (P0-03)
- In scope: **named classes, abstract classes, interfaces, and anonymous classes**.
- Anonymous classes **inherit the identity of their containing source file for test selection**; do not assign artificial standalone PSR-4 class identities. Multiple anonymous classes in one file may need internal AST identifiers for indexing, without changing the file-level test identity.
- **Implemented Phase 2 rule:** traits and enums are indexed as named symbols for future dependency analysis, not independent test targets. More than one named class/interface in a source file is a structural error. Anonymous classes are numbered by AST order within their containing source file. Helper-only PHP files need not declare testable classes.

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
- **Implemented in Phase 4:** PHPUnit-compatible class tests (also runnable via the Laravel runner used by Pest projects) use a guaranteed failing assertion; namespace resolution uses the project's Composer `autoload-dev.psr-4` where available. Centralized `Mock<Class>` factory classes wrap caller-supplied PHPUnit `createMock` closures, never clone real production behavior. `scaffold_incomplete` (exit 1) reports final/readonly/anonymous non-mockability. Existing test/mocks remain untouched.
- **Not claimed:** native Pest DSL generation or a separate Pest CI compatibility matrix; those are optional future enhancements.

### ADR-07 — Index persistence and regeneration (D05, D09)
- Use deterministic, **regenerable YAML manifests** as the persisted dependency index, located at `tests/mirror/**/*.auto.yaml`.
- **Do not version generated YAML in Git**. Add a targeted ignore rule, leaving hand-written files trackable.
- Indexing performs a deterministic **full rebuild initially**; use sorted output and safe atomic writes. Incremental indexing is not a first-release requirement.
- `matma:impact` **now reads persisted YAML without reparsing PHP ASTs**. Phase 3 implemented full AST index rebuild, schema/fingerprint and safe ownership-aware regeneration. Phase 5 added a generated `.index.auto.yaml` freshness inventory and exact file/FQCN manifest lookup; no source-tree glob/search is performed at selection time.
- Missing or stale manifests must never silently produce apparently valid impact results.
- **Resolved for indexing:** YAML manifests remain unversioned in Git; generated files carry a schema version and project-wide input fingerprint. Only MATMA-owned stale manifests are deleted. **Resolved in Phase 5:** missing or stale manifests cause an actionable nonzero error instructing explicit `php artisan matma:index` regeneration, never automatic AST reparsing or silent selection. Known file contents are rehashed, and known directories are watched for changes.

### ADR-08 — Business and regression suites (D06)
- Business/workflow and regression/E2E tests are **not source-file mirrored**.
- Associate them with affected components through **explicit registration/metadata**, not filename inference.
- Preserve tier isolation; do not automatically execute all business or regression tests merely because an impact level increases.
- Phase 5 implements a user-owned `tests/matma-verification.yaml` registry, keyed by explicit source paths/FQCNs and workflow test paths. Impact reports matching `business` and `regression` entries separately; it never adds them to mirrored test lists or executes them.

### ADR-09 — CLI and agent integration (D08, D09)
- Retain blueprint commands: `matma:index`, `matma:scaffold`, `matma:impact`, `matma:validate`.
- Preserve blueprint flags: `--source-root/-s`, `--tests-root/-t`, `--scaffold/-S`, `--level/-l`, `--target/-f`, `--check/-c`, `--format/-o` (text/json/yaml).
- **Implemented:** `matma:impact` reports deterministic affected source files, unit/interaction tests, missing test paths, and registered workflows; it never executes tests. Only the host runs tests through `php artisan test`.
- JSON is the preferred machine-readable output for agents; YAML remains the on-disk manifest format.

## Remaining implementation contracts

These are **not accepted choices yet**; do not mistake recommendations for decisions:

1. **Manifest freshness behavior:** **resolved by Phase 5**: fail with actionable diagnostics rather than automatically reindexing; conservative SHA-256 checks of known files plus indexed directory modification times. The command itself performs no AST parsing.
2. **Framework-aware scaffolding:** auto-detect Pest/PHPUnit (recommended), default to PHPUnit, or use explicit configuration.
3. **Non-mockable dependency handling:** detailed diagnostic format, failing test behavior, and supported fallback patterns (principle of no production rewrites is already accepted).
4. **Generated-file ownership:** completed for index and impact (targeted ignore, schema+fingerprint, ownership header, safe stale cleanup, and a generated inventory used to reject stale data).
5. **Compatibility details:** Composer package identifier/type, extension requirements, supported version ranges for PHPUnit/Pest, and CI enforcement.
6. **PHP symbol edge cases:** enum/trait treatment, multi-declaration files, anonymous-class identifiers, and stable cross-platform path rules.
7. **CLI contracts:** complete for all four current commands. Impact requires `--target`, uses inclusive `--level`, reports selected/missing tests and registered workflows, exits 1 on missing/stale/unsafe data, and rejects `--scaffold` as an invalid read-only operation.

## Acceptance and traceability

Architectural decisions have been **accepted and owner-verified**; no separate human reviewer is required. This sign-off is distinct from engineering acceptance. The Phase 1 Composer/Artisan package is implemented and passed CI on 2026-11-06 (see `PHASE_1_IMPLEMENTATION.md`). P0 tasks with outstanding AST-specific contracts remain open until their corresponding technical acceptance criteria are demonstrated.

This ADR supersedes the earlier open-recommendations text in `AIDocumentation/ARCHITECTURE_DECISIONS.md`.
