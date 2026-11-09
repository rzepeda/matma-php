---
name: matma-php
description: Use in Laravel PHP projects to run MATMA validation/indexing, scaffold unit and interaction tests, author real assertions, and register or select business and regression workflows.
---

# MATMA PHP skill for Claude Code

Use this skill when the user requests MATMA installation, validation, indexing, unit/interaction scaffolding, actual behavioral test implementation, or business/regression registration in a Laravel project. Do not assume MATMA works without Laravel/Artisan or that a generated stub is completed test coverage.

Read references/commands.md and references/test-tiers.md before proceeding. Use references/verification-registry.example.yaml only for an explicit workflow registration.

1. Work in the intended consumer Laravel repo root. Inspect composer.json, artisan, existing test/fixture conventions, source classes and git changes. Verify PHP, Laravel and MATMA availability. If installing MATMA, use the private Composer VCS dev dependency without suppressing security advisories.
2. Run php artisan matma:validate --check --format=json. Investigate structural errors before indexing; respect native PSR-4/PascalCase source conventions.
3. Run php artisan matma:index --format=json; generated manifests are re-created safely. If PHP sources are later changed, reindex before impact lookup.
4. Run php artisan matma:scaffold --target=app/Services/ActualClass.php --format=json for a real requested class. It creates only missing unit/interaction and appropriate mock factory files; do not overwrite existing files or rewrite production to make a mock possible.
5. Read actual implementation and contracts. Replace intentional self::fail stubs with real assertions of public behavior, success/failure/edge cases and interaction boundaries, using genuine fixtures and PHPUnit doubles.
6. If business or regression tests are requested, author them manually from confirmed requirements or known defects in their separate tiers, and explicitly register them in the user-owned YAML file.
7. Run matma:impact at the requested inclusive level, inspect missing_tests and registered_workflows, then run selected files through php artisan test. Impact only selects; it does not run tests.
8. Report file changes, tests actually executed, observed statuses, remaining intentional failures and unknown requirements.

Invoke /matma-php in Claude Code. On Claude.ai upload, the skill does not automatically grant access to the user's local repository or terminal.
