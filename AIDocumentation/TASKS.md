# MATMA PHP — Implementation Tasks

Source of truth: `matma_architecture_implementation_blueprint.md` (accepted MATMA ADR). This backlog distinguishes **required blueprint behavior** from **PHP-specific design choices** that need resolution.

## Phase 0 — Package contract and compatibility

- [ ] **P0-01** Define supported PHP and Laravel versions, Composer package name/type, extension requirements, and PHPUnit/Pest compatibility matrix. **Done when:** compatibility is documented and enforced in Composer/CI.
- [ ] **P0-02** Resolve blueprint snake_case filename convention vs Laravel's PSR-4 PascalCase files; choose an explicit strict/compatibility policy and deterministic mapping. **Done when:** examples and tests cover both allowed and disallowed names.
- [ ] **P0-03** Define source symbol scope: classes, abstract classes, enums, interfaces, traits, anonymous classes, and multi-declaration files. **Done when:** each construct has a documented audit/indexing rule.
- [ ] **P0-04** Define deterministic file/path normalization, Composer namespace resolution, case sensitivity, symlinks, excluded directories, and file traversal order. **Done when:** mappings behave identically across supported platforms.
- [ ] **P0-05** Decide test stub strategy and command policy (generate only missing files; never overwrite user edits); define JSON/YAML output schema and exit codes. **Done when:** fixtures assert contract and idempotence.

## Phase 1 — Composer/Laravel package foundation

- [ ] **P1-01** Create `composer.json`, PSR-4 autoload mappings, licensing, minimal source tree, and package metadata; depend on `nikic/php-parser` and `symfony/yaml`.
- [ ] **P1-02** Register a Laravel service provider using package discovery and an optional explicit provider registration path for supported versions.
- [ ] **P1-03** Create reusable CLI configuration/argument parsing for `--source-root/-s`, `--tests-root/-t`, `--scaffold/-S`, `--level/-l`, `--target/-f`, `--check/-c`, and `--format/-o`.
- [ ] **P1-04** Add Artisan command shells: `matma:index`, `matma:scaffold`, `matma:impact`, and `matma:validate`; validate arguments and return consistent nonzero error codes.
- [ ] **P1-05** Create a fixture Laravel project and automated tests for package discovery, Artisan registration, and CLI help/invalid options.

## Phase 2 — Deterministic symbol discovery and validation

- [ ] **P2-01** Implement recursive PHP file inventory for the configurable source root; exclude vendor, tests, caches, and irrelevant directories.
- [ ] **P2-02** Use `nikic/php-parser` AST nodes to extract namespace, primary class, imports/aliases, and relevant class references without using regex as an AST substitute.
- [ ] **P2-03** Build source-symbol and symbol-source maps, detect duplicate or ambiguous symbols, and report parser errors with file/line context.
- [ ] **P2-04** Implement source-to-mirror mapping for unit, interaction, mock, and `.auto.yaml` paths; assert deterministic resolution without project-wide searches at query time.
- [ ] **P2-05** Implement `matma:validate` with structural checks (one primary class per file and filename-class naming); `--check` must fail on violations and provide text/JSON/YAML diagnostics.
- [ ] **P2-06** Add fixtures for imports and aliases, nested namespaces, multiple classes, invalid syntax, case mismatches, and nondefault roots.

## Phase 3 — AST dependency indexer

- [ ] **P3-01** Define dependency edge rules (imports versus actual usages; type hints, constructors, static references, inheritance, attributes, and PHP-specific constructs) and explicit handling of unresolved/external classes.
- [ ] **P3-02** Build directed class dependency graph from AST, with stable deduplicated edges and source-path pointers; distinguish internal from external dependencies.
- [ ] **P3-03** Invert edges to direct dependents; compute shortest-path transitive reverse-dependency levels, safely handling cycles.
- [ ] **P3-04** Serialize manifests with Symfony YAML at `tests/mirror/<sub_path>/<stem>.auto.yaml` including `class_name`, `source_file`, `mirrors`, `dependencies`, and `dependents.level_N`.
- [ ] **P3-05** Implement `matma:index`, optional `--target` behavior, `--scaffold` interoperability, stable sorted outputs, atomic writes, and safe stale-manifest handling.
- [ ] **P3-06** Add deterministic graph fixtures (chain, diamond, cycle, isolated node, external dependency); assert byte-stable regeneration and level grouping.

## Phase 4 — Mirrored test and mock scaffolding

- [ ] **P4-01** Implement `matma:scaffold` for every indexed target to create missing unit tests under `tests/unit/mirror/`.
- [ ] **P4-02** Generate missing interaction tests under `tests/interaction/mirror/` and centralized mock classes under `tests/mocks/mirror/`.
- [ ] **P4-03** Ensure manifests are generated/refreshed with the mirrors; create missing folders recursively without writing into production source.
- [ ] **P4-04** Use runnable PHPUnit-compatible (or explicitly supported Pest-compatible) stubs with autoload and namespace conventions; avoid falsely passing placeholder assertions.
- [ ] **P4-05** Make scaffolding idempotent, non-destructive, and safe on partially existing mirror trees; test repeated runs and conflicts.

## Phase 5 — Impact queries and verification tier selection

- [ ] **P5-01** Implement `matma:impact` by reading the target's persisted manifest only for the initial lookup, without runtime AST traversal; document traversal/data assumptions.
- [ ] **P5-02** Implement `--level 0` as target unit test; `--level 1` as interaction impact; `--level 2+` as expanding reverse-dependent radius.
- [ ] **P5-03** Produce deduplicated, stable lists of affected source classes and test paths with `--format=text|json|yaml`; return actionable errors for missing/stale manifests.
- [ ] **P5-04** Specify how `tests/business/` and `tests/regression/` are selected (separate workflow/explicit runner, not automatic mirroring).
- [ ] **P5-05** Prove index-first design: AST parsing occurs during indexing only, not in impact queries; add tests for level filtering and cycles.

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

Implement P0 before P1, then P2 → P3 → P4 → P5 → P6. Use one coherent commit per completed feature/milestone when implementation begins; keep the docs updated as tasks are finished.
