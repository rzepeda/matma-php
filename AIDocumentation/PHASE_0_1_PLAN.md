# MATMA PHP — Phase 0 Decisions and Phase 1 Implementation Handoff

**Status:** Planning only; no application/package code or CI has been implemented.  
**Updated:** 2026-11-06  
**References:** [Accepted ADR-001](ARCHITECTURE_DECISIONS.md), [task checklist](TASKS.md).

## Phase 0 — Accepted direction vs pending decisions

| Task | Accepted decisions | Remaining decisions / verification |
| --- | --- | --- |
| P0-01 | PHP 8.2+, Laravel 11+, Composer dev dependency, Artisan integration, `php artisan test`, PHPUnit/Pest coexistence | Package identifier/type; PHP extension requirements; precise PHPUnit/Pest constraints and Laravel matrix; CI validation |
| P0-02 | Preserve Laravel PSR-4 naming and mirror project paths in tests | Canonical test/mock naming and namespaces; positive/negative mapping fixtures |
| P0-03 | Target named/abstract/anonymous classes and interfaces; anonymous classes inherit container-file test identity | Trait/enum policy; multi-declaration files; stable internal identities for multiple anonymous classes |
| P0-04 | Mirror source-relative directory layout; deterministic resolution without impact-time search | Composer namespace mapping, separator normalization, case policy, symlinks, exclusions, sort order and cross-platform fixtures |
| P0-05 | Create only missing files; failing stubs; never overwrite; centralized mocks; output JSON and generated YAML; `php artisan test` | Generator framework selection; mocks for non-mockable cases; CLI schemas/exit codes; stale-manifest behavior; idempotence tests |

### Pending decision register (not yet accepted)

- **DEC-01 / P0-01 — Composer identity:** choose exact `vendor/package` name (the repository name does not automatically establish a published package name), `type` (e.g. `library`), package namespace, and install/discovery metadata.
- **DEC-02 / P0-01 — Compatibility matrix:** establish required PHP extensions, version bounds for `nikic/php-parser`, Symfony YAML, PHPUnit/Pest and Testbench; determine CI test matrix for Laravel 11 and newer versions compatible with each PHP runtime. **PHP 8.2+** does not mean every future Laravel version runs on PHP 8.2.
- **DEC-03 / P0-02 — Mirror naming:** select canonical test/mock filenames and generated PHP namespaces while keeping production PSR-4 conventions; specify rules for nonstandard source roots and namespace prefixes.
- **DEC-04 / P0-03 — PHP symbols:** decide trait/enum handling (dependency-only or standalone tests), multi-class PHP files and their validation outcome, and reproducible identifiers for anonymous classes within one source file.
- **DEC-05 / P0-04 — Filesystem policy:** decide canonical slash/case normalization, filesystem symlink behavior, exclusion patterns, Composer namespace resolution/aliases and deterministic traversal order. Explicitly cover Windows versus Linux and case-sensitive autoloading.
- **DEC-06 / P0-05 — Framework-aware scaffolding:** automatically detect PHPUnit or Pest (previously recommended), default to PHPUnit, or require explicit configuration; define how an explicit failing test is generated in each case.
- **DEC-07 / P0-05 — CLI contracts:** specify success/error exit codes and stable `text`/`json`/`yaml` schemas for `index`, `scaffold`, `impact`, and `validate`; define `--target` behavior for full rebuilds.
- **DEC-08 / P0-05 — Mock failure mode:** specify exact diagnostic and test behavior for unmockable final/readonly classes; do not alter production code.
- **DEC-09 / P0-05 — Manifest freshness:** decide explicit error on missing/stale manifests versus regeneration; keep impact queries free of runtime AST parsing.
- **DEC-10 / P0-05 — Generated-file ownership:** finalize `.gitignore` rule for `tests/mirror/**/*.auto.yaml`, fingerprints/index metadata, atomic write and selective deletion behavior (only MATMA-owned artifacts).
- **DEC-11 / P0-05 — Acceptance fixtures:** agree expected idempotence, no-clobber, failing stub, valid/invalid mapping and machine-readable output fixtures so Phase 0 decisions can be tested.

**Important:** The architectural recommendations for DEC-06 and DEC-09 in ADR-001 are not yet accepted as final contracts. Do not silently mark them accepted or build behavior on them without recording the decision.

### Phase 0 completion gate

P0-01 through P0-05 stay unchecked until the decisions above are specified in documentation **and** the original task's validation requirement is satisfied (Composer/CI or test fixtures as applicable). Architectural agreement alone is insufficient.

## Phase 1 — Pending implementation tasks

### P1-01 — Composer package skeleton

- [ ] Create `composer.json` with agreed package identity, PHP 8.2+ and supported Laravel requirements.
- [ ] Add PSR-4 mappings, license and package metadata; declare `nikic/php-parser` and `symfony/yaml` dependencies.
- [ ] Set up minimal namespace/source layout and development tooling without placing package internals in the host app.
- **Acceptance:** Composer metadata validates, package can be installed in a sample supported Laravel project, and autoloading resolves.

### P1-02 — Laravel service provider

- [ ] Implement service provider registration/discovery; make commands discoverable in Laravel 11+.
- [ ] Support explicit provider registration for cases where package auto-discovery is disabled.
- **Acceptance:** install fixture demonstrates automatic and explicit registration without changing production application classes.

### P1-03 — CLI argument/configuration layer

- [ ] Implement `--source-root/-s`, `--tests-root/-t`, `--scaffold/-S`, `--level/-l`, `--target/-f`, `--check/-c`, `--format/-o` where applicable.
- [ ] Normalize paths, validate option combinations and honor machine-readable output/error contracts from Phase 0.
- **Acceptance:** help and argument-validation fixtures cover defaults, overrides, invalid input and consistent exit statuses.

### P1-04 — Artisan command shells

- [ ] Register `matma:index`, `matma:scaffold`, `matma:impact`, and `matma:validate` with clear help and consistent error handling.
- [ ] Command shells must **not claim functionality** before the AST/scaffolding engines exist.
- **Acceptance:** command discovery, option parsing and unsupported/unimplemented behavior have tested exit outcomes.

### P1-05 — Laravel fixture and CI smoke coverage

- [ ] Build a minimal Laravel test fixture and add tests for Composer package discovery, explicit provider registration, Artisan command help and invalid flags.
- [ ] Run `php artisan test` using compatible PHPUnit and/or Pest project configurations, according to the accepted matrix.
- [ ] Introduce an initial GitHub Actions matrix for supported PHP/Laravel combinations; expand in Phase 6.
- **Acceptance:** reproducible tests pass and CI exercises install + command registration on the declared compatibility baseline.

## Dependencies and sequencing

Finalize decisions DEC-01 through DEC-11 as applicable; implement P1-01 → P1-02 → P1-03/P1-04 → P1-05. Do not mark a checkbox complete from documentation alone. Phase 2 AST implementation, Phase 3 indexing, Phase 4 scaffolding and Phase 5 impact querying remain separate future milestones.
