---
name: matma-php
description: Use in Antigravity Laravel workspaces to run MATMA, validate/index dependencies, scaffold unit/interaction tests, write real assertions, and select business/regression workflows.
---

# MATMA PHP skill for Google Antigravity

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


Invoke for MATMA setup, validation, dependency indexing, unit/interaction test scaffolding, real behavioral test authoring or four-tier test-selection tasks in an accessible Laravel workspace. MATMA is not directly supported in non-Laravel PHP projects.

Read references/commands.md after the mandatory methodology references above, and optional verification-registry.example.yaml only for user-owned workflow registration. Use actual project paths and explicit user-owned fixtures.

1. Verify the active workspace contains the user's Laravel artisan and composer.json, check real source/test structure, PHP/Laravel versions and execution permissions. Confirm MATMA Composer installation through private VCS if needed; do not circumvent advisories.
2. Run php artisan matma:validate --check --format=json. Fix only substantiated structural errors and preserve native PSR-4 source layout.
3. Run php artisan matma:index --format=json. Run php artisan matma:scaffold --target=app/Services/ActualClass.php --format=json for an existing source path, not an invented class. Respect no-clobber; report final/readonly/anonymous non-mockability.
4. Replace intentionally failing generated unit and interaction stubs with real assertions from inspected source and domain contracts. Use genuine Laravel fixtures and appropriate PHPUnit doubles. Author business and regression tests manually on request; add an explicit registry if relevant.
5. Reindex after changing sources. Run matma:impact --target=<real-source-path> --level=N --format=json. It selects tests, does not run them. Execute selected tests with php artisan test and summarize actual results.
6. Review edits/permissions, report real passing and failing tests, missing paths, unfinished scaffolds and unresolved domain requirements. Never pretend a self::fail stub is coverage.

Antigravity's workspace default is .agents/skills/matma-php (legacy .agent/skills also works). Invoke /matma-php or rely on matching the skill description; only use tools actually available in the workspace.
