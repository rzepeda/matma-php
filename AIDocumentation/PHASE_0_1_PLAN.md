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
- **Resolved for generated files — DEC-03 / P0-02 — Mirror naming:** Phase 4 implements `ClassNameTest.php`, `ClassNameInteractionTest.php`, `MockClassName.php`, and `.auto.yaml` mappings and generates namespaces from host `autoload-dev.psr-4` (with a safe fallback). Nondefault test-root behavior is verified.
- **Open — DEC-04 / P0-03 — PHP symbols:** traits/enums are indexed but not standalone test targets; multiple named primary class/interface declarations fail validation; anonymous classes have file-local ordinal IDs and share source-file test identity.
- **Open — DEC-05 / P0-04 — Filesystem policy:** Phase 2 normalizes slashes, preserves case, skips symlinks and standard excluded directories, loads Composer PSR-4 roots and sorts files deterministically. Windows runner verification and unusual multi-root mapping edge cases remain.
- **Resolved for baseline — DEC-06 / P0-05 — Framework-aware scaffolding:** Phase 4 generates executable PHPUnit-style tests with explicit `self::fail(...)`. These are compatible with Laravel's `php artisan test` entry point and may be used within Pest projects; native Pest DSL generation and a Pest-specific CI matrix are **not** implemented or claimed.
- **Resolved for implemented commands — DEC-07 / P0-05 — CLI contracts:** impact now reports selected tests/registrations and rejects missing targets/unsafe flags; index/scaffold/validate retain stable machine-readable data with zero/nonzero exit contracts. See PHASE_5_IMPLEMENTATION.md.
- **Resolved — DEC-08 / P0-05 — Mock failure mode:** final/readonly/anonymous classes skip unsafe mock creation, leave their failing test scaffolds, report `non_mockable` diagnostics, and cause `scaffold_incomplete` with exit code 1. No production code is modified.
- **Resolved in Phase 5 — DEC-09 / P0-05 — Manifest freshness:** impact explicitly errors on missing, malformed or stale manifests, requires a separate `matma:index` refresh, and uses generated `.index.auto.yaml` metadata to verify known source contents/directories without reparsing AST.
- **Resolved through Phase 5 — DEC-10 / P0-05 — Generated-file ownership:** generated YAML remains ignored; source fingerprints and directory watch metadata now detect stale indexes in impact queries, with explicit reindex errors.
- **Resolved for Phase 4 — DEC-11 / P0-05 — Acceptance fixtures:** unit and Laravel interaction/consumer tests cover byte-stable regeneration, idempotent exclusive creation, no-clobber, symlink/path restrictions, failing generated stubs, mock factory behavior, final/readonly warnings and machine-readable output.

**Important:** DEC-06 uses PHPUnit-compatible test classes without claiming native Pest DSL generation. DEC-09 is implemented and verified; source hashing at impact-query time adds O(N) I/O even though no AST parse is performed. Additional performance benchmarking belongs to Phase 6.

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
