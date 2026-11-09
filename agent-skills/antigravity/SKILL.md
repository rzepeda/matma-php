---
name: matma-php
description: Use in Antigravity Laravel workspaces to run MATMA, validate/index dependencies, scaffold unit/interaction tests, write real assertions, and select business/regression workflows.
---

# MATMA PHP skill for Google Antigravity

Invoke for MATMA setup, validation, dependency indexing, unit/interaction test scaffolding, real behavioral test authoring or four-tier test-selection tasks in an accessible Laravel workspace. MATMA is not directly supported in non-Laravel PHP projects.

Read references/commands.md, references/test-tiers.md and optional verification-registry.example.yaml. Use actual project paths and explicit user-owned fixtures.

1. Verify the active workspace contains the user's Laravel artisan and composer.json, check real source/test structure, PHP/Laravel versions and execution permissions. Confirm MATMA Composer installation through private VCS if needed; do not circumvent advisories.
2. Run php artisan matma:validate --check --format=json. Fix only substantiated structural errors and preserve native PSR-4 source layout.
3. Run php artisan matma:index --format=json. Run php artisan matma:scaffold --target=app/Services/ActualClass.php --format=json for an existing source path, not an invented class. Respect no-clobber; report final/readonly/anonymous non-mockability.
4. Replace intentionally failing generated unit and interaction stubs with real assertions from inspected source and domain contracts. Use genuine Laravel fixtures and appropriate PHPUnit doubles. Author business and regression tests manually on request; add an explicit registry if relevant.
5. Reindex after changing sources. Run matma:impact --target=<real-source-path> --level=N --format=json. It selects tests, does not run them. Execute selected tests with php artisan test and summarize actual results.
6. Review edits/permissions, report real passing and failing tests, missing paths, unfinished scaffolds and unresolved domain requirements. Never pretend a self::fail stub is coverage.

Antigravity's workspace default is .agents/skills/matma-php (legacy .agent/skills also works). Invoke /matma-php or rely on matching the skill description; only use tools actually available in the workspace.
