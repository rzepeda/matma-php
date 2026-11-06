# MATMA PHP — Phase 1 Package Foundation

**Implementation:** First Composer/Laravel package baseline — **complete and CI-verified**.  
**Source of requirements:** `matma_architecture_implementation_blueprint.md`, [ADR-001](ARCHITECTURE_DECISIONS.md) and [TASKS.md](TASKS.md).

## Architecture delivered by source

| Task | Files | What exists |
| --- | --- | --- |
| P1-01 | `composer.json`, `src/`, `.gitignore` | Composer library, PSR-4 autoload, PHP 8.2+, Laravel 11/12/13 constraints, parser/YAML dependencies, explicit proprietary licensing, dev/Testbench dependencies |
| P1-02 | `src/MatmaServiceProvider.php`, `composer.json:extra.laravel.providers` | Laravel package auto-discovery and explicit provider registration |
| P1-03 | `src/Console/MatmaOptions.php`, `src/Console/MatmaOutput.php` | Shared long/short flags, defaults, parameter validation, JSON/YAML/text diagnostics, deterministic options representation |
| P1-04 | `src/Console/MatmaCommand.php`, `src/Console/Commands/*.php` | Four registered Artisan shells that explicitly exit 2 until real engines exist; invalid parameters exit 64 |
| P1-05 | `tests/unit/mirror/`, `tests/interaction/mirror/`, `tests/mocks/mirror/`, `tests/business/`, `tests/fixtures/laravel-app/`, `.github/workflows/ci.yml` | Manually authored isolated, interaction and business tests, centralized command test double, consumer fixture, and CI matrix |

## Manual testing architecture

The shell classes are represented by PHPUnit `*Test.php` files in source-relative mirror paths, and integration tests use real Laravel Artisan services rather than simulated command responses. Central `MockMatmaCommand` isolates shared command construction and option definition. No dummy passing tests or inferred workflows are introduced.

```text
src/
├── MatmaServiceProvider.php
└── Console/
    ├── MatmaCommand.php
    ├── MatmaOptions.php
    ├── MatmaOutput.php
    └── Commands/
        ├── IndexCommand.php
        ├── ScaffoldCommand.php
        ├── ImpactCommand.php
        └── ValidateCommand.php
tests/
├── unit/mirror/           # class-isolated tests, source-relative
├── interaction/mirror/    # real class interactions, source-relative
├── mocks/mirror/          # centralized test doubles
├── mirror/                # reserved, generated *.auto.yaml ignored
├── business/              # non-mirrored workflow verification
├── regression/            # non-mirrored E2E, not yet implemented
└── fixtures/laravel-app/  # real consumer install + php artisan test
```

Generated manifests will be excluded from Git using `/tests/mirror/**/*.auto.yaml`. No generated manifest exists in Phase 1; it would incorrectly claim a working AST index.

## Important non-goals of Phase 1

- The commands are **shells**, not working `index`, `scaffold`, `impact` or `validate` engines.
- `php artisan test` in a consuming Laravel app remains the agreed runner. The package itself uses PHPUnit/Testbench because it is not a standalone Laravel application.
- No application source code is renamed or modified.
- PHP 8.2 is MATMA's *minimum*; Laravel 13 requires a newer PHP version; CI selects valid combinations.
- The CI matrix tests selected combinations, not every future version. **Verification passed** in [workflow run 42277378877](https://github.com/rzepeda/matma-php/actions/runs/42277378877): 5/5 package jobs passed (24 PHPUnit tests, 98 assertions per run), plus 1/1 Laravel 11 consumer smoke job (`php artisan test`: 3 tests, 10 assertions). Laravel 11 compatibility jobs disable Composer advisory **blocking only during CI dependency resolution** because legacy Laravel 11 versions have published security advisories; MATMA's distributed package does not disable Composer security checks.

## Next work

Phase 2: AST symbol discovery and structural validation, followed by indexing, scaffolding and impact selection. Once those are implemented, future generated tests must start failing explicitly rather than declaring fake coverage.
