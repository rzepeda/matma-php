# MATMA PHP — Implementation Tasks

Source of truth: `matma_architecture_implementation_blueprint.md`; accepted PHP-specific decisions: [ADR-001](ARCHITECTURE_DECISIONS.md). Detailed unresolved decisions and Phase 1 handoff: [PHASE_0_1_PLAN.md](PHASE_0_1_PLAN.md).

## Status (2026-11-07)

- **Architectural decisions accepted and owner-verified:** The sole maintainer has explicitly accepted and verified the Phase 0 architecture decisions. No separate reviewer sign-off is required. This does **not** replace automated tests or Composer/CI compatibility checks.
- **Phase 0 implementation tasks completed and verified:** **4/5**. P0-02 through P0-05 have source/mirror and Windows/Linux CI evidence. P0-01 remains open for an explicit Pest compatibility/version matrix; PHPUnit 11 is the verified baseline.
- **Phase 1 implementation tasks completed and verified:** **5/5**. [CI run 42277378877](https://github.com/rzepeda/matma-php/actions/runs/42277378877) passed all six jobs.
- **Phase 2 implementation tasks completed and verified:** **6/6**. [CI run 42285897847](https://github.com/rzepeda/matma-php/actions/runs/42285897847) passed all six jobs.
- **Phase 3 implementation tasks completed and verified:** **6/6**. [CI run 42291922018](https://github.com/rzepeda/matma-php/actions/runs/42291922018) passed all six jobs.
- **Phase 4 implementation tasks completed and verified:** **5/5**. [CI run 42294621231](https://github.com/rzepeda/matma-php/actions/runs/42294621231) passed all six jobs, including deliberately failing generated-test verification.
- **Phase 5 implementation tasks completed and verified:** **5/5**. [CI run 42299323812](https://github.com/rzepeda/matma-php/actions/runs/42299323812) passed all six jobs, including a Laravel consumer lookup by PSR-4 class name.
- **Phase 6 implementation tasks completed and verified:** **6/6**. [CI run 42336822288](https://github.com/rzepeda/matma-php/actions/runs/42336822288) passed seven jobs including Windows; Linux and Windows package suites passed 116 tests/502 assertions, Laravel consumer passed 6 tests/59 assertions, quality gates and benchmark artifact were produced.
- **Implemented today:** All four MATMA commands, Laravel end-to-end walkthrough, manifest-only impact, Windows/Linux compatibility tests, focused static/style checks, snapshot tests and benchmark. A v0.1.0 release is **prepared, not tagged or published**; P0-01 Pest-version certification remains open.
- **Legend:** `[x]` means the task's full **Done when** condition is satisfied; `[ ]` means incomplete even when its architectural direction is accepted.

## Phase 0 — Package contract and compatibility

- [ ] **P0-01** Define supported PHP/Laravel versions, Composer identity/type, extension requirements and PHPUnit/Pest compatibility. **Implemented:** package `rzepeda/matma-php` (`type: library`, proprietary), PHP ^8.2, ext-json, Laravel 11–13 version constraints, parser/YAML dependencies, PHPUnit ^11.5.50, and five PHP/Laravel CI combinations plus Windows and a Laravel consumer. **Still pending:** an explicitly tested Pest-version compatibility matrix and final extension/support certification beyond the current matrix. **Done when:** the full PHPUnit/Pest compatibility claim is documented and verified without implying native Pest DSL support.
- [x] **P0-02** Preserve native Laravel PascalCase filenames, PSR-4 namespaces and source-relative mirror paths. Deterministic `ClassNameTest.php`, `ClassNameInteractionTest.php`, `MockClassName.php` and YAML mappings are implemented; valid, invalid-casing and namespace fixtures verify accepted/rejected names. **Evidence:** Phases 2 and 4 tests and documentation.
- [x] **P0-03** Classes, abstract classes, interfaces and anonymous classes are test targets; traits and enums are dependency-only graph symbols; multi-primary declaration violations are diagnosed; anonymous classes use source-file identity with ordered AST identifiers. **Evidence:** Phases 2, 3 and 5 tests/docs.
- [x] **P0-04** Deterministic path/namespace mapping, exclusions, symlink and case policies are defined and tested. Windows/PHP 8.3 and Linux CI validate equivalent project-relative identities, including short-vs-canonical Windows paths, plus Composer PSR-4 and stable traversal. **Evidence:** Phase 6 CI run 42336822288.
- [x] **P0-05** Only missing test/mock files are generated, with real failing PHPUnit-compatible stubs, idempotent no-clobber behavior, diagnostics for unmockable symbols, JSON/YAML output and stable exit codes, and explicit reindex requirements for stale impact data. The host still uses `php artisan test`; Pest-native DSL generation is not claimed. **Evidence:** Phases 4–6 tests.

### Agreed cross-cutting behavior (not new completed tasks)

- Index real AST references rather than unused imports; materialize reverse dependencies.
- `--level` is an inclusive shortest-path reverse-dependency radius, with levels 0, 1, and 2+ as in ADR-001.
- Use deterministic full index rebuilds initially; generated `tests/mirror/**/*.auto.yaml` files are **not Git-versioned**.
- Business and regression suites are explicitly registered and are not automatically mirrored or always executed.
- `matma:impact` never silently runs tests or reparses source ASTs.
- The outstanding choices are tracked individually in [PHASE_0_1_PLAN.md](PHASE_0_1_PLAN.md).

## Phase 1 — Composer/Laravel package foundation

**Status: 5/5 complete, CI verified on 2026-11-06.** All five tasks have been implemented and tested. See [Phase 1 implementation](PHASE_1_IMPLEMENTATION.md) and [Phase 0 / Phase 1 record](PHASE_0_1_PLAN.md#phase-1--implemented-and-ci-verified-tasks).

- [x] **P1-01** Create `composer.json`, PSR-4 autoload mappings, licensing, minimal source tree, and package metadata; depend on `nikic/php-parser` and `symfony/yaml`.
- [x] **P1-02** Register a Laravel service provider using package discovery and an optional explicit provider registration path for supported versions.
- [x] **P1-03** Create reusable CLI configuration/argument parsing for `--source-root/-s`, `--tests-root/-t`, `--scaffold/-S`, `--level/-l`, `--target/-f`, `--check/-c`, and `--format/-o`.
- [x] **P1-04** Add Artisan command shells: `matma:index`, `matma:scaffold`, `matma:impact`, and `matma:validate`; validate arguments and return consistent nonzero error codes.
- [x] **P1-05** Create a fixture Laravel project and automated tests for package discovery, Artisan registration, and CLI help/invalid options.

## Phase 2 — Deterministic symbol discovery and validation

**Status: 6/6 complete and CI-verified.** See [Phase 2 implementation](PHASE_2_IMPLEMENTATION.md).

- [x] **P2-01** Implement recursive PHP file inventory for the configurable source root; exclude vendor, tests, caches, and irrelevant directories.
- [x] **P2-02** Use `nikic/php-parser` AST nodes to extract namespace, primary class, imports/aliases, and relevant class references without using regex as an AST substitute.
- [x] **P2-03** Build source-symbol and symbol-source maps, detect duplicate or ambiguous symbols, and report parser errors with file/line context.
- [x] **P2-04** Implement source-to-mirror mapping for unit, interaction, mock, and `.auto.yaml` paths; assert deterministic resolution without project-wide searches at query time.
- [x] **P2-05** Implement `matma:validate` with structural checks (one primary class per file and filename-class naming); `--check` must fail on violations and provide text/JSON/YAML diagnostics.
- [x] **P2-06** Add fixtures for imports and aliases, nested namespaces, multiple classes, invalid syntax, case mismatches, and nondefault roots.

## Phase 3 — AST dependency indexer

**Status: 6/6 complete and CI-verified.** See [Phase 3 implementation](PHASE_3_IMPLEMENTATION.md). Indexing never generates test or mock files; `--scaffold` is explicitly deferred to Phase 4 with nonzero exit and no writes.

- [x] **P3-01** Define dependency edge rules (imports versus actual usages; type hints, constructors, static references, inheritance, attributes, and PHP-specific constructs) and explicit handling of unresolved/external classes.
- [x] **P3-02** Build directed class dependency graph from AST, with stable deduplicated edges and source-path pointers; distinguish internal from external dependencies.
- [x] **P3-03** Invert edges to direct dependents; compute shortest-path transitive reverse-dependency levels, safely handling cycles.
- [x] **P3-04** Serialize manifests with Symfony YAML at `tests/mirror/<sub_path>/<stem>.auto.yaml` including `class_name`, `source_file`, `mirrors`, `dependencies`, and `dependents.level_N`.
- [x] **P3-05** Implement `matma:index`, optional `--target` behavior, `--scaffold` interoperability, stable sorted outputs, atomic writes, and safe stale-manifest handling.
- [x] **P3-06** Add deterministic graph fixtures (chain, diamond, cycle, isolated node, external dependency); assert byte-stable regeneration and level grouping.

## Phase 4 — Mirrored test and mock scaffolding

**Status: 5/5 complete and CI-verified.** See [Phase 4 implementation](PHASE_4_IMPLEMENTATION.md). New test stubs fail deliberately; non-mockable final/readonly/anonymous classes return explicit diagnostics rather than fabricated mocks.

- [x] **P4-01** Implement `matma:scaffold` for every indexed target to create missing unit tests under `tests/unit/mirror/`.
- [x] **P4-02** Generate missing interaction tests under `tests/interaction/mirror/` and centralized mock classes under `tests/mocks/mirror/`.
- [x] **P4-03** Ensure manifests are generated/refreshed with the mirrors; create missing folders recursively without writing into production source.
- [x] **P4-04** Use runnable PHPUnit-compatible (or explicitly supported Pest-compatible) stubs with autoload and namespace conventions; avoid falsely passing placeholder assertions.
- [x] **P4-05** Make scaffolding idempotent, non-destructive, and safe on partially existing mirror trees; test repeated runs and conflicts.

## Phase 5 — Impact queries and verification tier selection

**Status: 5/5 complete and CI-verified.** See [Phase 5 implementation](PHASE_5_IMPLEMENTATION.md). The query reads the target manifest plus generated index freshness metadata, with no AST reparse or automatic test execution.

- [x] **P5-01** Implement `matma:impact` by reading the target's persisted manifest only for the initial lookup, without runtime AST traversal; document traversal/data assumptions.
- [x] **P5-02** Implement `--level 0` as target unit test; `--level 1` as interaction impact; `--level 2+` as expanding reverse-dependent radius.
- [x] **P5-03** Produce deduplicated, stable lists of affected source classes and test paths with `--format=text|json|yaml`; return actionable errors for missing/stale manifests.
- [x] **P5-04** Specify how `tests/business/` and `tests/regression/` are selected (separate workflow/explicit runner, not automatic mirroring).
- [x] **P5-05** Prove index-first design: AST parsing occurs during indexing only, not in impact queries; add tests for level filtering and cycles.

## Phase 6 — Integration, CI, documentation, release

**Status: 6/6 tasks implemented and CI-verified.** [Phase 6 report](PHASE_6_IMPLEMENTATION.md), [adoption guide](ADOPTION_GUIDE.md), [release checklist](RELEASE_CHECKLIST.md); [seven-job CI run 42336822288](https://github.com/rzepeda/matma-php/actions/runs/42336822288). A release was prepared but **not created**.

- [x] **P6-01** Build PHPUnit test suite covering all four commands, failure modes, deterministic path mapping, YAML schema, graph correctness, scaffolding safety, and output snapshots.
- [x] **P6-02** Add CI matrix for supported PHP/Laravel versions: Composer validation, style/static analysis, unit/integration tests, and package install smoke test.
- [x] **P6-03** Add a sample Laravel app and complete documented walkthrough for `index → scaffold → validate → impact`.
- [x] **P6-04** Document package installation via Composer, Artisan registration, supported naming policy, flags, test execution, migration/adoption, and troubleshooting.
- [x] **P6-05** Benchmark manifest lookup vs AST indexing separately; verify lookup depends on persisted data rather than parsing the source tree.
- [x] **P6-06** Prepare release tagging/versioning and package publication steps (if publishing to Packagist); update README status after tests pass.

## Milestone acceptance criteria

- **M1 — Installable:** package installs into an example Laravel application and exposes all commands.
- **M2 — Auditable:** validation deterministically diagnoses naming and class-file invariant violations.
- **M3 — Indexed:** a single index pass writes correct stable reverse-dependency manifests.
- **M4 — Scaffoldable:** new classes obtain mirrored unit/interaction/mock files without overwriting existing work.
- **M5 — Queryable:** each `--level` selects the expected scoped tests using persisted manifests without AST reparse.
- **M6 — Release candidate prepared:** Phase 6 documentation, integration tests, verified PHP/Laravel/PHPUnit baseline CI and benchmarks are complete. A public/private release still requires maintainer approval and resolution or explicit scoping of P0-01 Pest certification and security/license gates.

## Implementation order

Phases 1–6 are implemented and the Phase 6 seven-job Linux/Windows/consumer matrix is green. The proposed v0.1.0 release is not tagged or published. P0-01 remains open pending explicit Pest-version compatibility certification; before publication, confirm proprietary licensing/visibility, dependency advisories and all release gates. Generated failing stubs are not completed test coverage; business/regression workflows are selected separately and run explicitly with `php artisan test`.
