# MATMA PHP agent skills

These three folders are separate installable Agent Skills for the MATMA Laravel package. Each has its own SKILL.md and reference files. The folders under agent-skills are distribution copies, not automatically active in the MATMA repository.

## Laravel project setup

MATMA currently requires Laravel/Artisan; it is not directly executable on plain PHP projects. Supported package baseline: PHP 8.2+ with Laravel 11, 12 or 13. The repository is private and there is no published Packagist release or approved version tag. From your intended Laravel project root, authenticate Composer with GitHub and run:

~~~bash
composer config repositories.matma-php vcs https://github.com/rzepeda/matma-php
composer require --dev rzepeda/matma-php:dev-main
php artisan list --raw
php artisan matma:validate --check --format=json
php artisan matma:index --format=json
php artisan matma:scaffold --target=app/Services/OrderService.php --format=json
php artisan matma:impact --target=app/Services/OrderService.php --level=2 --format=json
php artisan test tests/unit/mirror/Services/OrderServiceTest.php
~~~

Replace the illustrative OrderService path with a real file. The generated unit and interaction tests deliberately fail until an author supplies meaningful assertions. MATMA does not auto-generate completed business or regression tests, and matma:impact selects tests without running them. Use php artisan test to run test files.

In the consuming project, Git-ignore the generated tests/mirror/**/*.auto.yaml files; preserve developer-authored tests/matma-verification.yaml. MATMA never overwrites existing PHP test and mock files.

## Installation: Claude Code

Copy everything in this repository's agent-skills/claude folder into YOUR_LARAVEL_REPO/.claude/skills/matma-php/ (so that SKILL.md is immediately within matma-php). Start Claude Code in the Laravel repository and invoke /matma-php. Claude.ai custom-skill upload can use the separate ZIP skill bundle when that feature is enabled.

## Installation: ChatGPT / Codex

Copy everything in agent-skills/chatgpt into YOUR_LARAVEL_REPO/.agents/skills/matma-php/. Codex can invoke $matma-php or pick the skill automatically. Where available, ChatGPT skill installation/upload can be performed through its Plugins > Skills interface. Where skills aren't enabled, attach the skill files to a ChatGPT Project/Work context and use them as project instructions. An instruction file does not itself provide terminal or repository access.

## Installation: Google Antigravity

Copy everything in agent-skills/antigravity into YOUR_LARAVEL_REPO/.agents/skills/matma-php/. Antigravity also understands the older .agent/skills path, but .agents/skills is the current documented default. Invoke /matma-php in Antigravity or ask for MATMA testing.

ChatGPT/Codex and Antigravity both use .agents/skills/matma-php: install ONE compatible variant at that shared path if you use both in the same repository. Do not overwrite the first variant with the second assuming both were merged.

## Agent responsibilities

The package's own unit, interaction, and business suites contain genuine assertions. Consumer app unit and interaction files are scaffolds until an agent/developer reads real behavior and writes assertions. Consumer business and regression cases must be authored manually and can be explicitly registered in tests/matma-verification.yaml. Agents must report which tests actually executed and passed, which are unfinished, and any stale index or unmockable-class diagnostics. Never invent test results.

Platform references:
- Claude: https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview
- ChatGPT/Codex: https://learn.chatgpt.com/docs/build-skills
- Antigravity: https://antigravity.google/docs/skills
- MATMA adoption: https://github.com/rzepeda/matma-php/blob/main/AIDocumentation/ADOPTION_GUIDE.md
