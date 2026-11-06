# MATMA PHP — Phase 4: Mirrored Tests and Centralized Mock Scaffolding

**Status:** Implemented; verified by GitHub Actions on 2026-11-06.
**Evidence:** https://github.com/rzepeda/matma-php/actions/runs/42294621231
**Acceptance tasks:** P4-01 through P4-05 in [TASKS.md](TASKS.md).

## Commands

~~~bash
php artisan matma:scaffold --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target='App\Services\DocumentParser' --format=yaml
php artisan matma:index --scaffold --target='App\Services\DocumentParser' --format=json
php artisan test tests/unit/mirror/Services/DocumentParserTest.php
php artisan test tests/interaction/mirror/Services/DocumentParserInteractionTest.php
~~~

The scaffolder runs the actual Phase 2 AST validator and Phase 3 full dependency graph before writing. It regenerates all `*.auto.yaml` reverse-dependency manifests even when a single `--target` is provided, and creates missing test/mock files only for the selected target and its direct internal dependency mocks. Without `--target`, it provisions all testable class-file nodes. `matma:index --scaffold` and `matma:scaffold` use the same implementation; `matma:validate` remains read-only. No operation rewrites application PHP source.

## Directory contract

An `app/Services/DocumentParser.php` class deterministically maps to:

~~~text
tests/unit/mirror/Services/DocumentParserTest.php
tests/interaction/mirror/Services/DocumentParserInteractionTest.php
tests/mocks/mirror/Services/MockDocumentParser.php
tests/mirror/Services/DocumentParser.auto.yaml
~~~

Production Laravel PascalCase and Composer PSR-4 conventions are preserved. The source-root-relative directory structure is mirrored verbatim under the testing tiers. Classes, abstract classes, interfaces and anonymous-class-containing files are test targets. Traits and enums participate in dependency graphs but never receive standalone tests or mocks. Anonymous classes use their containing file as the test identity.

## Explicitly failing test stubs

Generated unit and interaction PHP files extend `PHPUnit\Framework\TestCase` and contain an actual failing assertion:

~~~php
public function test_implementation_is_required(): void
{
    self::fail('MATMA: implement unit assertions for App\Services\DocumentParser (app/Services/DocumentParser.php).');
}
~~~

These are real executable tests, not skipped or fake passing assertions. The repository's Laravel 11 consumer fixture invokes an actual generated stub via **`php artisan test <path>`** and confirms that it exits unsuccessfully with the MATMA failure message. Pest projects can run PHPUnit-style test cases through the underlying Laravel test runner, but Phase 4 does not generate Pest DSL tests or claim a separate Pest version compatibility matrix.

The generator uses the consuming application's `composer.json` `autoload-dev.psr-4` mapping (longest applicable directory prefix) to derive namespaces, retaining the exact mirrored folder capitalization. When a custom external tests root has no matching autoload-dev mapping, a `MatmaGenerated\...` namespace is used for directly discovered PHPUnit files. To include the lower-case `tests/unit/mirror` and `tests/interaction/mirror` tiers in `php artisan test` without explicit path arguments, the host application's PHPUnit test-suite configuration may need to list those directories. MATMA does **not** edit `phpunit.xml`, `composer.json`, or application source automatically.

## Centralized PHPUnit mock factories

Mocks are classes under `tests/mocks/mirror`, one for each mockable internal symbol, with deterministic filenames and namespaces. A generated mock class is a **factory/registry for a real PHPUnit MockObject**, not a concrete subclass with invented behavior. Example usage from an authored PHPUnit test:

~~~php
$double = MockDocumentParser::create(
    fn (string $type) => $this->createMock($type),
);
~~~

The callback runs `TestCase::createMock()` from the test's own visibility scope, avoiding unauthorized direct calls to PHPUnit's test case internals. The factory verifies that the result is a PHPUnit MockObject implementing the intended class/interface. No default return values are invented by MATMA. Unit tests exercise a generated interface mock with real PHPUnit mocking methods.

Final classes, readonly classes, and anonymous classes **do not receive falsely mockable subclasses**. Their unit/interaction tests still scaffold as failing stubs, while the command returns `scaffold_incomplete` with exit code 1 and a `non_mockable` diagnostic identifying the affected source. Traits/enums are intentionally skipped without mock warnings because they are dependency-only symbols. Existing production classes and DI seams are never rewritten.

## Non-destructive file safety

- Create only missing `*.php` test/mock files using an exclusive-create (`fopen(..., 'x')`) operation; no existing file is overwritten, even if MATMA generated it previously.
- Preflight all planned file paths before writing any manifest or scaffold: constrain destinations to unit/interaction/mocks mirrored tiers, reject traversal and source-root overlap, reject symlink files/directories and directory-as-file conflicts.
- Create missing tier directories recursively. Re-running scaffolding returns `scaffolds.created: []` and lists preserved files in `scaffolds.existing`, even after a developer edits them.
- Use Phase 3's separate manifest ownership-header rules to refresh only MATMA-generated YAML; preserve byte-identical manifests and remove only stale MATMA-owned manifests. User-authored YAML is never overwritten.
- Invalid PHP/structural sources, unknown targets, and unsafe paths fail before attempting test generation. Mid-operation filesystem failures remain explicit nonzero errors; the phase does not promise a fully transactional multi-file filesystem commit.
- An explicit `--target` creates that class's unit/interaction tests, its own mock (when mockable), and mocks for directly referenced mockable internal classes. It does not create unrelated classes' tests.

## CLI output

Both commands return `summary`, `index_fingerprint`, `target`, `manifests` (`written`, `unchanged`, `removed`), `scaffolds` (`created`, `existing`), `scaffold_targets`, and `diagnostics` in JSON/YAML output, alongside the shared command/status/message/exit_code/options fields.

| Code | Result |
| --- | --- |
| 0 | `scaffolded` (test and mock requirements generated or already present) / `indexed` without `--scaffold` |
| 1 | `scaffold_incomplete` for non-mockable symbols, validation/target errors, or an unsafe filesystem destination |
| 2 | Reserved for the still-unimplemented `matma:impact` command |
| 64 | Invalid MATMA CLI argument |

## Testing

The Phase 4 unit tier contains `tests/unit/mirror/Scaffolding/ScaffoldGeneratorTest.php` and `ScaffoldWriterTest.php` using real AST graphs and isolated temporary directories. Tests exercise named classes, interfaces, final/readonly dependencies, anonymous files, trait restrictions, namespace mapping, actual PHPUnit double creation, real failing stub execution, byte-stable manifests, and preservation of edited existing files. Interaction tests exercise real `php artisan matma:scaffold`, `matma:index --scaffold`, target selection, partial mirror trees, invalid projects and incomplete mockability; the Laravel consumer fixture runs `php artisan test` and verifies deliberately failing test stubs.

**CI:** [Run 42294621231](https://github.com/rzepeda/matma-php/actions/runs/42294621231) passed all six jobs: five PHP/Laravel package combinations (88 tests, 373 assertions in the checked job) and the Laravel 11 native consumer test suite (4 tests, 24 assertions).

## Deferred work

Phase 5 subsequently implemented `matma:impact`, inclusive reverse-dependency selections, missing/stale manifest checks, and explicitly registered business/regression suggestions; see [PHASE_5_IMPLEMENTATION.md](PHASE_5_IMPLEMENTATION.md). Phase 4 still does not execute generated tests automatically or assume that a generated failing stub counts as coverage.
