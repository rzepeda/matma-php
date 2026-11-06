# MATMA PHP — Phase 0 Decision Register and Phase 1 Completion Record

**Status:** Phase 0 architectural decisions are owner-approved and owner-verified; detailed technical contracts remain open. **Phase 1 completed and passed CI on 2026-11-06.**  
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

### Phase 0 technical contract register (updated after Phase 1)

- **Resolved in Phase 1 — DEC-01 / P0-01 — Composer identity:** selected `rzepeda/matma-php`, Composer `type: library`, namespace `Matma\Php`, dev dependency installation and Laravel provider auto-discovery. Licensing is explicitly `proprietary`.
- **Partially resolved — DEC-02 / P0-01 — Compatibility matrix:** PHP 8.2+, explicit `ext-json`, parser ^5, Symfony YAML ^7/^8, PHPUnit ^11.5.50 and Testbench ^9/^10/^11 are represented in Composer and five PHP/Laravel CI combinations. Pest-specific compatibility, a complete extension matrix and future framework versions still require verification; Laravel 13 needs newer PHP.
- **Partially resolved — DEC-03 / P0-02 — Mirror naming:** Phase 2 uses deterministic `ClassNameTest.php`, `ClassNameInteractionTest.php`, `MockClassName.php` and `.auto.yaml` paths, with tests for nondefault roots and separator normalization. Namespace generation remains for Phase 4.
- **Open — DEC-04 / P0-03 — PHP symbols:** traits/enums are indexed but not standalone test targets; multiple named primary class/interface declarations fail validation; anonymous classes have file-local ordinal IDs and share source-file test identity.
- **Open — DEC-05 / P0-04 — Filesystem policy:** Phase 2 normalizes slashes, preserves case, skips symlinks and standard excluded directories, loads Composer PSR-4 roots and sorts files deterministically. Windows runner verification and unusual multi-root mapping edge cases remain.
- **Open — DEC-06 / P0-05 — Framework-aware scaffolding:** automatically detect PHPUnit or Pest (previously recommended), default to PHPUnit, or require explicit configuration; define how an explicit failing test is generated in each case.
- **Partially resolved — DEC-07 / P0-05 — CLI contracts:** Phase 1 shells use exit 2 for not implemented and 64 for invalid MATMA options; structured output includes command/status/message/exit_code/options. Define final success/engine error codes and `--target` full-index behavior in later phases.
- **Open — DEC-08 / P0-05 — Mock failure mode:** specify exact diagnostic and test behavior for unmockable final/readonly classes; do not alter production code.
- **Open — DEC-09 / P0-05 — Manifest freshness:** decide explicit error on missing/stale manifests versus regeneration; keep impact queries free of runtime AST parsing.
- **Partially resolved — DEC-10 / P0-05 — Generated-file ownership:** the `.gitignore` rule is `/tests/mirror/**/*.auto.yaml`; Phase 3 implements schema version, project fingerprint, atomic per-file writes, an ownership header, byte-stable regenerations and MATMA-owned stale cleanup. Phase 5 still needs stale-index detection.
- **Partially resolved — DEC-11 / P0-05 — Acceptance fixtures:** Phase 1 covers common-flag validation, machine-readable outputs, package installation and CLI not-implemented errors; scaffold idempotence/no-clobber/failing-stub fixtures remain for Phase 4.

**Important:** The architectural recommendations for DEC-06 and DEC-09 in ADR-001 are not yet accepted as final contracts. Do not silently mark them accepted or build behavior on them without recording the decision.

### Phase 0 completion gate

P0-01 through P0-05 have **owner-accepted and owner-verified architectural decisions** (no second human reviewer is required). Engineering acceptance remains a separate question: some details depend on future AST/scaffold work, whereas package foundation and its compatibility matrix are now CI-tested. Do not claim unfinished engine behavior based on owner sign-off.

## Phase 1 — Implemented and CI-verified tasks

### P1-01 — Composer package skeleton

- [x] Create `composer.json` with agreed package identity, PHP 8.2+ and supported Laravel requirements.
- [x] Add PSR-4 mappings, license and package metadata; declare `nikic/php-parser` and `symfony/yaml` dependencies.
- [x] Set up minimal namespace/source layout and development tooling without placing package internals in the host app.
- **Acceptance:** Composer metadata validates, package can be installed in a sample supported Laravel project, and autoloading resolves.

### P1-02 — Laravel service provider

- [x] Implement service provider registration/discovery; make commands discoverable in Laravel 11+.
- [x] Support explicit provider registration for cases where package auto-discovery is disabled.
- **Acceptance:** install fixture demonstrates automatic and explicit registration without changing production application classes.

### P1-03 — CLI argument/configuration layer

- [x] Implement `--source-root/-s`, `--tests-root/-t`, `--scaffold/-S`, `--level/-l`, `--target/-f`, `--check/-c`, `--format/-o` where applicable.
- [x] Normalize paths, validate option combinations and honor machine-readable output/error contracts from Phase 0.
- **Acceptance:** help and argument-validation fixtures cover defaults, overrides, invalid input and consistent exit statuses.

### P1-04 — Artisan command shells

- [x] Register `matma:index`, `matma:scaffold`, `matma:impact`, and `matma:validate` with clear help and consistent error handling.
- [x] Command shells must **not claim functionality** before the AST/scaffolding engines exist.
- **Acceptance:** command discovery, option parsing and unsupported/unimplemented behavior have tested exit outcomes.

### P1-05 — Laravel fixture and CI smoke coverage

- [x] Build a minimal Laravel test fixture and add tests for Composer package discovery, explicit provider registration, Artisan command help and invalid flags.
- [x] Run `php artisan test` using compatible PHPUnit and/or Pest project configurations, according to the accepted matrix.
- [x] Introduce an initial GitHub Actions matrix for supported PHP/Laravel combinations; expand in Phase 6.
- **Acceptance:** reproducible tests pass and CI exercises install + command registration on the declared compatibility baseline.

## Dependencies and sequencing

**Phase 1 is complete:** P1-01 → P1-05 were implemented and passed [CI run 42277378877](https://github.com/rzepeda/matma-php/actions/runs/42277378877). Phase 0's detailed unresolved contracts DEC-01 through DEC-11 should be evaluated against the working package; some are partially settled by implementation, others remain for later AST/scaffold phases. Phase 2 symbol analysis, Phase 3 indexing, Phase 4 scaffolding, and Phase 5 impact querying remain separate future milestones.
