---
name: matma-php
description: Use for MATMA testing in Laravel PHP projects to install, validate, index dependencies, scaffold unit/interaction tests, author assertions, and register or select business/regression tests.
---

# MATMA PHP skill for ChatGPT Work and Codex

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


Use when working on a Laravel PHP codebase to deploy MATMA testing, audit/index source, scaffold missing unit/interaction files, implement real assertions and select registered workflows. Plain PHP without Laravel/Artisan needs an integration layer; do not pretend commands ran when filesystem/terminal access is absent.

Read references/commands.md after the mandatory methodology references above; the sample verification-registry.example.yaml shows optional user-owned business/regression registration.

1. Confirm the user's Laravel repository root, actual files, Composer PSR-4 mapping, test conventions and Git state. Verify PHP 8.2+, supported Laravel and MATMA package availability. Do not bypass dependency advisories or publish private credentials.
2. Run php artisan matma:validate --check --format=json; resolve actual structural problems before indexing. Run php artisan matma:index --format=json.
3. Target an existing file: php artisan matma:scaffold --target=app/Services/ActualClass.php --format=json. Preserve existing tests, mocks and production PHP. Handle scaffold_incomplete diagnostics explicitly.
4. Read source implementation and existing fixtures; author real unit/interaction behavioral assertions and valid/invalid/edge cases. Do not replace self::fail with vacuous assertions. For business/regression testing, manually implement verified workflows/bug reproductions, and register separately only when requested.
5. After source changes, reindex. Run php artisan matma:impact --target=app/Services/ActualClass.php --level=2 --format=json to select affected tests. Execute existing selected files via php artisan test; report missing paths and registered workflows separately.
6. Summarize which test files were merely generated, which were authored, which commands actually ran/passed, and any unverified behavior or blockers.

Codex uses the repo's .agents/skills/matma-php path and can invoke $matma-php. Eligible ChatGPT surfaces may upload this skill or invoke @matma-php; otherwise place instructions in a ChatGPT Project. The skill does not itself grant local code execution.
