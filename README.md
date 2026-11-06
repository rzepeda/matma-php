# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

> **Status: specification and implementation planning.** This repository does not yet contain a working Composer package, Artisan commands, or an AST indexer. The files under [AIDocumentation](AIDocumentation/) define the work required to implement them.

MATMA is a testing and mock architecture for PHP/Laravel projects designed for deterministic class discovery, small agent context windows, fast impact analysis, and precisely scoped automated verification.

## Core principles

1. **One primary class per source file.** Enforce deterministic class-to-filename mapping.
2. **Mirrored paths.** Production classes map directly to unit tests, interaction tests, centralized mocks, and generated dependency manifests.
3. **Precomputed reverse dependencies.** An offline AST indexer writes `.auto.yaml` files; impact queries read those files instead of reparsing a project.
4. **Explicit testing tiers.** Isolated unit tests, pairwise interaction tests, process/business tests, and live-data/service regression tests remain distinct.
5. **Unobtrusive installation.** Distribute tooling as a Composer package under `vendor/`, avoiding application-specific tooling clutter.

## PHP/Laravel implementation

- **AST:** `nikic/php-parser`, including `Stmt\\Class_` and `Stmt\\Use_` analysis.
- **YAML:** `symfony/yaml`.
- **Integration:** a Laravel service provider and Artisan commands, beginning with `php artisan matma:index`.
- **Source root:** `app/` by default; configurable with `--source-root`.
- **Test root:** `tests/` by default; configurable with `--tests-root`.

### Proposed command interface (not yet implemented)

```bash
php artisan matma:index --source-root=app --tests-root=tests
php artisan matma:scaffold --target=app/Services/DocumentParser.php
php artisan matma:impact --target=app/Services/DocumentParser.php --level=2 --format=json
php artisan matma:validate --check
```

Each command should accept the common options applicable to it:

| Flag | Short | Default | Purpose |
| --- | --- | --- | --- |
| `--source-root` | `-s` | `app/` | Production classes |
| `--tests-root` | `-t` | `tests/` | Generated test and manifest paths |
| `--scaffold` | `-S` | false | Create missing mirrors during an applicable operation |
| `--level` | `-l` | 0 | Impact radius: 0 unit; 1 interaction; 2+ transitive dependents |
| `--target` | `-f` | none | A source file or class |
| `--check` | `-c` | false | Nonzero exit on violations |
| `--format` | `-o` | `text` | `text`, `json`, or `yaml` |

### Structural mapping

MATMA's blueprint uses snake_case source stems; for PHP the intended example is:

```text
app/Services/document_parser.php                    # DocumentParser
tests/unit/mirror/Services/test_document_parser.php
tests/interaction/mirror/Services/test_document_parser_interaction.php
tests/mocks/mirror/Services/mock_document_parser.php
tests/mirror/Services/document_parser.auto.yaml
tests/business/                                      # workflow tests (not mirrored)
tests/regression/                                    # E2E tests (not mirrored)
```

**Laravel compatibility decision pending:** Laravel applications conventionally use PascalCase PSR-4 class files (e.g. `app/Services/DocumentParser.php`). The implementation must reconcile that convention with the blueprint's snake_case example before enforcing naming rules; do not silently rename application classes or break Composer autoloading. See [Architecture decisions](AIDocumentation/ARCHITECTURE_DECISIONS.md).

## Expected manifest contents

Each `tests/mirror/<sub_path>/<class_stem>.auto.yaml` must include the class name, source path, unit/interaction/mock mirror pointers, direct dependencies (including each dependency's mock pointer), and reverse dependents grouped by shortest-path levels. The AST indexer creates these manifests; agents and test tools must not edit them manually.

## Verification scope

- **Level 0:** the target class's unit test.
- **Level 1:** interaction tests associated with the target and immediate impacted classes.
- **Level 2+:** progressively deeper reverse dependents, using the on-disk index.

Business and regression test suites are not mirrored; selection and execution policy for these tiers must be made explicit. The initial implementation should emit or enumerate the affected tests, not execute tests implicitly unless a separate, documented runner is added.

## Implementation plan

Start with [AIDocumentation/TASKS.md](AIDocumentation/TASKS.md) for the phased, checkable backlog and acceptance criteria. [Architecture decisions](AIDocumentation/ARCHITECTURE_DECISIONS.md) records PHP-specific design choices requiring resolution.

### Current state

This README and planning documentation establish requirements only. No working commands, scaffolding, tests, or release artifacts are claimed. Installation and usage commands above are **future interface examples**, not runnable instructions today.
