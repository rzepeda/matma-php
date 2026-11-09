---
name: matma-php
description: Use in Laravel PHP projects to run MATMA validation/indexing, scaffold unit and interaction tests, author real assertions, and register or select business and regression workflows.
---

# MATMA PHP skill for Claude Code

## Mandatory governing rules (not optional)

**Before writing or reviewing any MATMA test, read [the full accepted seven rules](references/MATMA_Seven_Architectural_Rules.md), [the PHP enforcement checklist](references/php-enforcement.md), and [the four-tier authoring guide](references/test-tiers.md). Do not substitute generic testing conventions for MATMA isolation.**

1. **One class per file:** match the source, test or mock class identifier to its PHP filename using native Composer PSR-4/PascalCase. Never rename production to copy a generic snake_case example.
2. **Unit tests:** the target class A is the sole real class; **every** collaborator and external interaction is mocked.
3. **Centralized mock registry:** share reusable mirrored factories in tests/mocks/mirror; import them into tests instead of defining inline or duplicate mocks.
4. **Pairwise interaction tests:** instantiate exactly the collaborating classes A and B as real; mock all other downstream collaborators required by either.
5. **Business tests:** run real internal business/domain classes; mock only infrastructure and external service boundaries.
6. **Regression tests:** run complete workflows on real representative datasets with real/live/containerized participating services, **without participating-service mocks**; this is a separate pre-merge/pre-deployment gateway.
7. **Split topology:** class-centric unit/interaction/mocks/manifests mirror production source paths; business and regression tests are process-oriented, never source-mirrored.

**Mandatory verification before reporting success:** classify each test's tier, explicitly identify real classes and mocked collaborators, check the relevant rule, and report any violation. Never claim that MATMA's CLI automatically checks all real-versus-mock boundaries: agents must inspect them. If a safe real-service regression environment is unavailable, report the regression gateway as blocked/not run instead of weakening Rule 6.


Use this skill when the user requests MATMA installation, validation, indexing, unit/interaction scaffolding, actual behavioral test implementation, or business/regression registration in a Laravel project. Do not assume MATMA works without Laravel/Artisan or that a generated stub is completed test coverage.

Read references/commands.md for the exact CLI behavior after the mandatory methodology references above. Use references/verification-registry.example.yaml only for an explicit workflow registration.

1. Work in the intended consumer Laravel repo root. Inspect composer.json, artisan, existing test/fixture conventions, source classes and git changes. Verify PHP, Laravel and MATMA availability. If installing MATMA, use the private Composer VCS dev dependency without suppressing security advisories.
2. Run php artisan matma:validate --check --format=json. Investigate structural errors before indexing; respect native PSR-4/PascalCase source conventions.
3. Run php artisan matma:index --format=json; generated manifests are re-created safely. If PHP sources are later changed, reindex before impact lookup.
4. Run php artisan matma:scaffold --target=app/Services/ActualClass.php --format=json for a real requested class. It creates only missing unit/interaction and appropriate mock factory files; do not overwrite existing files or rewrite production to make a mock possible.
5. Read actual implementation and contracts. Replace intentional self::fail stubs with real assertions of public behavior, success/failure/edge cases and interaction boundaries, using genuine fixtures and PHPUnit doubles.
6. If business or regression tests are requested, author them manually from confirmed requirements or known defects in their separate tiers, and explicitly register them in the user-owned YAML file.
7. Run matma:impact at the requested inclusive level, inspect missing_tests and registered_workflows, then run selected files through php artisan test. Impact only selects; it does not run tests.
8. Report file changes, tests actually executed, observed statuses, remaining intentional failures and unknown requirements.

Invoke /matma-php in Claude Code. On Claude.ai upload, the skill does not automatically grant access to the user's local repository or terminal.
