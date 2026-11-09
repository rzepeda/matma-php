---
name: matma-php
description: Use for MATMA testing in Laravel PHP projects to install, validate, index dependencies, scaffold unit/interaction tests, author assertions, and register or select business/regression tests.
---

# MATMA PHP skill for ChatGPT Work and Codex

Use when working on a Laravel PHP codebase to deploy MATMA testing, audit/index source, scaffold missing unit/interaction files, implement real assertions and select registered workflows. Plain PHP without Laravel/Artisan needs an integration layer; do not pretend commands ran when filesystem/terminal access is absent.

Read references/commands.md and references/test-tiers.md; the sample verification-registry.example.yaml shows optional user-owned business/regression registration.

1. Confirm the user's Laravel repository root, actual files, Composer PSR-4 mapping, test conventions and Git state. Verify PHP 8.2+, supported Laravel and MATMA package availability. Do not bypass dependency advisories or publish private credentials.
2. Run php artisan matma:validate --check --format=json; resolve actual structural problems before indexing. Run php artisan matma:index --format=json.
3. Target an existing file: php artisan matma:scaffold --target=app/Services/ActualClass.php --format=json. Preserve existing tests, mocks and production PHP. Handle scaffold_incomplete diagnostics explicitly.
4. Read source implementation and existing fixtures; author real unit/interaction behavioral assertions and valid/invalid/edge cases. Do not replace self::fail with vacuous assertions. For business/regression testing, manually implement verified workflows/bug reproductions, and register separately only when requested.
5. After source changes, reindex. Run php artisan matma:impact --target=app/Services/ActualClass.php --level=2 --format=json to select affected tests. Execute existing selected files via php artisan test; report missing paths and registered workflows separately.
6. Summarize which test files were merely generated, which were authored, which commands actually ran/passed, and any unverified behavior or blockers.

Codex uses the repo's .agents/skills/matma-php path and can invoke $matma-php. Eligible ChatGPT surfaces may upload this skill or invoke @matma-php; otherwise place instructions in a ChatGPT Project. The skill does not itself grant local code execution.
