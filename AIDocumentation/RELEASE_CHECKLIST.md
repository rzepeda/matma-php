# MATMA PHP — Release preparation (no release has been published)

**Proposed first version:** `v0.1.0` (SemVer pre-1.0). **Status:** preparatory checklist only; no tag, GitHub Release or Packagist publication has been created by this phase.

## Package identity and constraints

- Composer package: `rzepeda/matma-php`, `type: library`.
- PHP minimum: 8.2. Current advertised framework compatibility is Laravel 11 onward, with a tested matrix of PHP 8.2/Laravel 11, PHP 8.3/Laravel 11/12, PHP 8.4/Laravel 12/13; additional versions should be verified before claiming support.
- Composer distribution for now uses a GitHub VCS repository and `--dev`. Laravel auto-discovery registers the package service provider.
- The repository is **private** and its package license is **`proprietary`**. Public distribution or Packagist registration requires an explicit maintainer decision on visibility and licensing. Do not assume an open-source license or permission to publish.
- Generated YAML is not version-controlled. Only source, test fixtures, documentation and benchmark code belong in the release artifact.
- The documented application test execution command is `php artisan test`; MATMA itself does not execute selected tests.

## Mandatory release gates

- [ ] Maintainer confirms package release intent, proprietary license, repository visibility and version. No tag or publication has been requested.
- [x] All six Phase 6 checklist tasks have CI-verified acceptance evidence, including [Windows and six Linux/consumer CI jobs](https://github.com/rzepeda/matma-php/actions/runs/42336822288).
- [x] `composer validate --strict` passes in Phase 6 CI.
- [x] Package PHP syntax, focused PHPCS checks and PHPStan level-0 baseline analysis pass.
- [x] PHPUnit unit/interaction/business tests pass on the specified five Linux PHP/Laravel matrix combinations and Windows PHP 8.3/Laravel 11.
- [x] Laravel 11 consumer `php artisan test` passes with full `index → scaffold → validate → impact` workflow and expected failing generated stub check.
- [x] Benchmark artifact `matma-phase6-benchmark` records AST index/write and persisted manifest/hash lookup separately; measurements are descriptive, not guaranteed.
- [ ] Maintainer security review of Composer dependencies and supported Laravel versions, including Laravel 11's existing advisories. **Never apply the CI-only advisory bypass in production.**
- [x] Review error statuses, path/symlink safety and generated artifacts on Linux and Windows; the Windows suite passed 116 tests/502 assertions after portability fixes.
- [x] Prepare release notes and propose SemVer `v0.1.0`; avoid promising stable public API at a pre-1.0 version.

- [ ] Resolve or explicitly scope the remaining **P0-01** Pest-specific version compatibility matrix before making broad PHPUnit/Pest support claims.

## Versioning and publication procedure (only after approval)

1. Confirm the exact commit on `main` and rerun all GitHub Actions jobs. Review open issues, CI audit details, and benchmark artifact.
2. Update [CHANGELOG.md](../CHANGELOG.md) and this checklist with verified results and version dates.
3. Create an annotated tag `v0.1.0` on that reviewed commit, e.g. `git tag -a v0.1.0 -m "MATMA PHP v0.1.0"`, and push it **only after explicit approval**.
4. Optionally create a GitHub Release using the reviewed change log, for example `gh release create v0.1.0 --notes-file CHANGELOG.md`. A release must not be represented as created until GitHub confirms it.
5. If deciding to publish to Packagist, determine licensing, access, webhook configuration and Composer auto-update authorization. Packagist exposure is a separate explicit decision.
6. In consumer applications, pin the reviewed release tag/commit rather than `dev-main`; run `composer update rzepeda/matma-php --with-dependencies` in the appropriate controlled environment and validate Artisan discovery.

**No automated job in Phase 6 publishes tags or releases.** CI also verifies proposed future `v*` tags, but publication authority remains with the maintainer.
