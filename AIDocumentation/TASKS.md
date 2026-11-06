# MATMA PHP — Implementation Tasks

Source of truth: `matma_architecture_implementation_blueprint.md`; accepted PHP-specific decisions: [ADR-001](ARCHITECTURE_DECISIONS.md). Detailed unresolved decisions and Phase 1 handoff: [PHASE_0_1_PLAN.md](PHASE_0_1_PLAN.md).

## Status (2026-11-06)

- **Architectural decisions accepted and owner-verified:** The sole maintainer has explicitly accepted and verified the Phase 0 architecture decisions. No separate reviewer sign-off is required. This does **not** replace automated tests or Composer/CI compatibility checks.
- **Phase 0 implementation tasks completed:** **0/5**. Final contracts, fixture verification, Composer constraints and CI are outstanding.
- **Phase 1 implementation tasks completed and verified:** **5/5**. [CI run 42277378877](https://github.com/rzepeda/matma-php/actions/runs/42277378877) passed all six jobs.
- **Phase 2 implementation tasks completed and verified:** **6/6**. [CI run 42285897847](https://github.com/rzepeda/matma-php/actions/runs/42285897847) passed all six jobs.
- **Phase 3 implementation tasks completed and verified:** **6/6**. [CI run 42291922018](https://github.com/rzepeda/matma-php/actions/runs/42291922018) passed all six jobs.
- **Phase 4 implementation tasks completed and verified:** **5/5**. [CI run 42294621231](https://github.com/rzepeda/matma-php/actions/runs/42294621231) passed all six jobs, including deliberately failing generated-test verification.
- **Phase 5 implementation tasks completed and verified:** **5/5**. [CI run 42299323812](https://github.com/rzepeda/matma-php/actions/runs/42299323812) passed all six jobs, including a Laravel consumer lookup by PSR-4 class name.
- **Implemented today:** All four MATMA Artisan commands. Impact selection reads persisted YAML and explicit workflow registrations without AST parsing or test execution; generated tests remain explicitly failing until authored. YAML is ignored by Git; production source is untouched.
- **Legend:** `[x]` means the task's full **Done when** condition is satisfied; `[ ]` means incomplete even when its architectural direction is accepted.

## Phase 0 — Package contract and compatibility

- [ ] **P0-01** Define supported PHP and Laravel versions, Composer package name/type, extension requirements, and PHPUnit/Pest compatibility matrix. **Accepted:** PHP **8.2+**, Laravel **11 onward**, Composer dev dependency, Laravel package discovery, `php artisan test`, preserve host PHPUnit/Pest choice; `nikic/php-parser` + `symfony/yaml`. **Pending:** Composer identity/type, extension list, framework/test-library version constraints, CI compatibility matrix. **Done when:** compatibility is documented and enforced in Composer/CI.
- [ ] **P0-02** Resolve blueprint snake_case filename convention vs Laravel's PSR-4 PascalCase files; choose an explicit strict/compatibility policy and deterministic mapping. **Accepted:** preserve native Laravel/PSR-4 names and project paths, mirror source-relative structure in `tests/`, never rename source code; anonymous classes inherit their containing source file identity for test selection. **Pending:** precise mirrored test/mock class filenames and namespaces, allowed/disallowed mapping fixtures. **Done when:** examples and tests cover both allowed and disallowed names.
- [ ] **P0-03** Define source symbol scope: classes, abstract classes, enums, interfaces, traits, anonymous classes, and multi-declaration files. **Accepted:** named classes, abstract classes, interfaces and anonymous classes are testable targets; anonymous classes have no standalone PSR-4 test identity. **Pending:** treatment of traits/enums as dependencies or targets; multi-declaration validation and stable anonymous-node identifiers. **Done when:** each construct has a documented audit/indexing rule.
- [ ] **P0-04** Define deterministic file/path normalization, Composer namespace resolution, case sensitivity, symlinks, excluded directories, and file traversal order. **Accepted:** preserve source-relative path and directory casing beneath `tests/`, resolve without runtime filesystem searches at impact-query time. **Pending:** cross-platform normalization, PSR-4 autoload map precedence, case mismatch behavior, symlink policy, exclusions, deterministic traversal and fixtures. **Done when:** mappings behave identically across supported platforms.
- [ ] **P0-05** Decide test stub strategy and command policy (generate only missing files; never overwrite user edits); define JSON/YAML output schema and exit codes. **Accepted:** only missing files are generated; never overwrite edits or production code; generated tests fail explicitly; centralized mocks; non-mockable dependencies receive diagnostics; `php artisan test` executes tests; `impact` only selects; JSON CLI output and YAML on-disk manifests. **Pending:** PHPUnit/Pest generator selection, exact CLI JSON/YAML schemas and exit codes, stale-index behavior, fixture tests for safety/idempotence. **Done when:** fixtures assert contract and idempotence.

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

- [ ] **P6-01** Build PHPUnit test suite covering all four commands, failure modes, deterministic path mapping, YAML schema, graph correctness, scaffolding safety, and output snapshots.
- [ ] **P6-02** Add CI matrix for supported PHP/Laravel versions: Composer validation, style/static analysis, unit/integration tests, and package install smoke test.
- [ ] **P6-03** Add a sample Laravel app and complete documented walkthrough for `index → scaffold → validate → impact`.
- [ ] **P6-04** Document package installation via Composer, Artisan registration, supported naming policy, flags, test execution, migration/adoption, and troubleshooting.
- [ ] **P6-05** Benchmark manifest lookup vs AST indexing separately; verify lookup depends on persisted data rather than parsing the source tree.
- [ ] **P6-06** Prepare release tagging/versioning and package publication steps (if publishing to Packagist); update README status after tests pass.

## Milestone acceptance criteria

- **M1 — Installable:** package installs into an example Laravel application and exposes all commands.
- **M2 — Auditable:** validation deterministically diagnoses naming and class-file invariant violations.
- **M3 — Indexed:** a single index pass writes correct stable reverse-dependency manifests.
- **M4 — Scaffoldable:** new classes obtain mirrored unit/interaction/mock files without overwriting existing work.
- **M5 — Queryable:** each `--level` selects the expected scoped tests using persisted manifests without AST reparse.
- **M6 — Releasable:** documentation, integration tests, CI, and compatibility matrix are complete.

## Implementation order

Phases 1–5 are implemented and CI-verified. Proceed to Phase 6 end-to-end integration, Windows/other platform hardening, benchmarking, documentation and release readiness. Generated failing stubs never count as completed test coverage; business/regression workflows are explicitly registered and run only through the host's `php artisan test`.
