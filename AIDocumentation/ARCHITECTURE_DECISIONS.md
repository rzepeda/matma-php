# PHP/Laravel Architecture Decisions to Resolve

The accepted MATMA blueprint establishes architectural invariants. The following items are **implementation decisions**, not exceptions silently granted by the blueprint.

## D-01 — Filename mapping vs PSR-4 (open)

The blueprint illustrates `document_parser.php` → `DocumentParser`; standard Laravel code typically uses `DocumentParser.php` → `DocumentParser`. Enforcing snake_case for every existing Laravel class could break Composer's PSR-4 loading.

**Recommended:** make PSR-4-compatible PascalCase mapping the default for Laravel, while allowing an explicitly opted-in strict snake_case mode where autoloading is configured accordingly. Preserve deterministic 1:1 mapping and mirrored stem generation under either policy. Require tests of both modes and document any divergence from the accepted blueprint before release.

## D-02 — What constitutes a class and a dependency? (open)

Define whether to index PHP classes only, or also anonymous classes, enums, interfaces, and traits; how to handle files with auxiliary declarations; and whether `use` imports count as dependencies absent actual references. Use `nikic/php-parser` and resolver-aware symbol matching, not string guesses. Track unresolved symbols explicitly.

## D-03 — Manifest impact semantics (open)

The blueprint persists direct dependencies and reverse dependents grouped by `level_1`, `level_2`, and beyond. Define **shortest-path depth**, uniqueness, cycle handling, deterministic ordering, and whether a level means exactly that depth or an inclusive radius. Proposed: an inclusive radius, with level 0 selecting the target unit and level 1 adding immediate interaction tests.

## D-04 — Mock generation behavior (open)

A centralized mock registry is required. Define whether stub classes extend source classes or implement contracts, how to handle `final`/readonly classes and constructor requirements, and how to keep generated stubs test-runner compatible. Never generate a mock that falsely claims to faithfully implement behavior.

## D-05 — Output format and incremental indexing (open)

Use stable sorting and safe atomic writes; specify whether `index --target` can safely update reverse-dependency manifests for all affected classes or should force a full rebuild. Treat stale manifests as errors or refresh them explicitly; never silently return outdated impact results.

## D-06 — Business and regression suites (open)

The blueprint explicitly does **not** mirror business/workflow and regression/E2E tests. Define how independent suites are registered and invoked, and what `impact` returns for each tier. Avoid implying that these can be inferred from mirrored source filenames.

## D-07 — Package distribution and CI (open)

The accepted ADR calls for native Composer installation and Artisan entry points. Decide exact PHP/Laravel version bounds, package discovery configuration, namespace, package identifier, PHPUnit/Pest versions, and supported operating systems before implementation.
