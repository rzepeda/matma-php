# MATMA PHP — Phase 2 implementation

**Status:** Implemented and verified in GitHub Actions on 2026-11-06.
**CI:** https://github.com/rzepeda/matma-php/actions/runs/42285897847
**Scope:** P2-01 through P2-06; Phase 3–5 engines remain unimplemented.

## Delivered components

| Class | Responsibility |
| --- | --- |
| SourceInventory | Recursively inventories PHP files with deterministic sorting, skip rules, path normalization and symlink exclusion |
| PhpAstAnalyzer | Parses PHP with nikic/php-parser v5; returns syntax and file-read diagnostics |
| DiscoveryVisitor | Extracts named/abstract/anonymous classes, interfaces, traits, enums, aliases and actual symbol references |
| Psr4Resolver | Resolves expected namespaces from the host project's Composer autoload.psr-4 and autoload-dev.psr-4 |
| MirrorMapper | Computes unit, interaction, mock and .auto.yaml mirror paths without filesystem search |
| StructureValidator | Checks primary declaration count, filename case, namespaces and duplicate names |
| ProjectInspector | Builds in-memory symbol-to-source maps and target-specific, deterministic diagnostic results |
| ValidateCommand | Runs the actual read-only AST audit through Artisan with text, JSON or YAML output |

## Usage

~~~bash
php artisan matma:validate --source-root=app --tests-root=tests --check --format=json
php artisan matma:validate --target=App\\Services\\DocumentParser --format=text
php artisan matma:validate --source-root=app --format=yaml
~~~

Validation exit code 0 means clean or non-strict completion; a non-strict report may still have status `violations`. Exit code 1 indicates strict `--check` errors, missing source root, or missing target. Exit code 64 signals invalid CLI values. Index, scaffold and impact still exit 2 (not implemented).

The machine response includes command, status, message, exit_code, options, summary, files, symbol_to_source, and diagnostics. Diagnostics record severity, code, source file, line and message. File records include declarations, imported aliases, actual AST reference names and deterministic mirror pointers.

Targets may be source-root-relative paths, project-relative paths, absolute file paths, or named fully-qualified symbols. A target query scans the configured tree before filtering, so duplicate symbol errors are still detected. Validation never executes tests or writes production, test, mock or manifest files.

## PHP symbol and structural rules

- Testable scope: named classes, abstract classes, interfaces and anonymous classes.
- Traits and enums are discovered as named symbols for future dependency resolution, but are not standalone test targets.
- Anonymous classes use file-local AST-order IDs such as anonymous#1; their containing source file supplies the test-selection identity.
- Multiple named testable declarations in one file are errors. Helper-only files, trait/enum-only files and multiple anonymous classes are not forced into artificial PSR-4 classes.
- Named primary declarations must match the PHP filename stem exactly, including capitalization. Composer PSR-4 paths must match declared namespaces if an autoload mapping applies.
- Imports (including grouped imports and aliases) are distinct from actual AST reference sites. Actual references are recorded from type declarations, parent classes, interfaces, trait uses, instantiation, static references, catches and attributes; computing dependency graph edges is deferred to Phase 3.

## Directory and mapping policy

File inventory is source-root configurable; paths are slash-normalized, case-preserving and lexically sorted. Symlinks are skipped. Excluded directory names include .git, .tools, .cache, vendor, node_modules, tests, storage, bootstrap and cache.

~~~text
app/Services/DocumentParser.php
tests/unit/mirror/Services/DocumentParserTest.php
tests/interaction/mirror/Services/DocumentParserInteractionTest.php
tests/mocks/mirror/Services/MockDocumentParser.php
tests/mirror/Services/DocumentParser.auto.yaml
~~~

These are **computed paths**, not generated files. Generated YAML remains ignored by Git and will first be produced in Phase 3.

## Manually authored tests and mocks

Unit coverage lives in tests/unit/mirror/Analysis; integration coverage lives in tests/interaction/mirror/Analysis and tests/interaction/mirror/Console/Commands. Business tests exercise the actual validation workflow. The test tree includes real PHP-parser fixture projects and intentionally invalid PHP sources for negative scenarios (multiple classes, parse error, duplicates, case and namespace mismatch). Intentionally invalid fixture files are excluded from CI syntax lint, but tested through the AST parser.

Phase 2 uses the real parser and file fixtures; there is no need for fabricated AST mocks. The existing centralized tests/mocks/mirror/Console/MockMatmaCommand.php covers the shared Artisan command double. No fake passing test or generated scaffold has been added.

## Verified CI and remaining phases

Six jobs passed in https://github.com/rzepeda/matma-php/actions/runs/42285897847 (PHP 8.2/Laravel 11; PHP 8.3/Laravel 11; PHP 8.3/Laravel 12; PHP 8.4/Laravel 12; PHP 8.4/Laravel 13; and Laravel 11 consumer php artisan test).

Phase 3 must implement the graph, reverse dependencies, stable YAML manifests and freshness. Phase 4 must generate only missing tests/mocks with explicitly failing test stubs. Phase 5 must query manifests without AST parsing. Further Windows CI and unusual multi-root Composer mappings remain compatibility hardening work.