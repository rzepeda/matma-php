# MATMA PHP — Multi-Tiered Agentic Testing & Mock Architecture

MATMA is an agent-oriented testing architecture for PHP/Laravel. It preserves the host project's PSR-4 conventions and places mirrored tests and centralized mocks under the project's `tests/` directory, with dependency manifests generated on demand rather than committed.

## The MATMA Methodology

### 1. What is MATMA?

**MATMA (Multi-Tiered Agentic Testing & Mock Architecture)** is a software testing methodology and architectural framework designed to optimize Test-Driven Development (TDD), refactoring, and verification workflows performed by autonomous AI coding agents.

MATMA establishes **seven architectural rules** governing source organization, test isolation, mock management, dependency relationships, and verification boundaries. It addresses constraints specific to LLM-based coding agents: finite context windows, token consumption, repeated filesystem exploration, and the need for focused, deterministic feedback when modifying software.

An agent working on a single class may need to find its tests, inspect dependencies, locate appropriate mocks, and determine which consumers could be affected by a change. MATMA combines two mechanisms to make that work predictable:

- **A rule-based testing and mocking methodology:** precise definitions of which classes are real and which are mocked at each testing tier.
- **A deterministic indexing and discovery architecture:** mirrored tests and mock helpers, precomputed reverse dependencies, and persisted manifests for selecting affected verification.

MATMA complements existing frameworks such as pytest and PHPUnit; **it does not replace the host project's test runner**.

### 2. Five optimization objectives

#### 2.1 Context-window and token efficiency

Autonomous coding agents repeatedly load source files, tests, mocks, and dependency information into their working context. Centralized mocks, predictable test paths, and disk-persisted manifests help agents retrieve only the files and metadata relevant to the current task, reducing redundant inspection and token consumption.

#### 2.2 Deterministic file and symbol discovery

MATMA defines source-to-test, source-to-mock, and source-to-manifest mappings that can be derived from the source identity. This is intended to avoid repeated repository-wide `find`, `grep`, and `glob` searches merely to locate verification artifacts.

#### 2.3 Disk-materialized reverse dependencies

A change to a class or module can affect its consumers. MATMA analyzes source dependencies ahead of time, computes the reverse relationships, and persists them in generated `.auto.yaml` manifests. Impact queries then use persisted information instead of reconstructing the source dependency graph on every request.

#### 2.4 Signal-to-noise isolation

Tests with too many real collaborators can fail for unrelated reasons. MATMA's testing rules prescribe which participants are real and which are mocked, giving each tier a bounded diagnostic purpose.

#### 2.5 Tiered verification scaling

MATMA separates isolated unit tests, pairwise interactions, business workflows, and real-system regressions. Class-centric tests can be selected using a reverse-dependency impact radius (`--level`); business and regression tests remain distinct verification tiers.

### 3. The seven architectural rules

These are MATMA's **accepted normative testing rules**. Language-specific Architecture Decision Records (ADRs) define the implementation conventions, including the explicitly accepted Python adaptation to Rule 1.

#### Rule 1 — One Class Per File with Coinciding Names

The original structural rule requires each source, test, and mock file to encapsulate one class whose identifier deterministically corresponds to its filename under the target language's naming convention.

**Why:** Aligning file and class identities makes source and verification artifacts predictable. Agents can derive paths instead of discovering them through repeated searches.

**Accepted Python adaptation — PY-ADR-01: Atomic identity is the source module, not the class.** Python conventionally allows multiple classes, functions, asynchronous functions, and methods in one source module. Enforcing a class-per-file constraint would impose an artificial organization on existing Python projects. MATMA Python therefore treats the **source module/file** as the atomic mirror and impact target:

- A Python module can contain multiple classes and functions.
- One testable module maps to one unit test file, one interaction test file, one centralized mock helper, and one generated manifest.
- Tests must address the module's testable functions and classes.
- The existing source layout is preserved; MATMA does not rename or split production modules.

For example:

~~~text
src/acme/pricing.py
tests/unit/mirror/src/acme/test_pricing.py
tests/interaction/mirror/src/acme/test_pricing_interaction.py
tests/mocks/mirror/src/acme/mock_pricing.py
tests/mirror/src/acme/pricing.auto.yaml
~~~

This changes the **granularity of structural mirroring**, not the intent of deterministic discovery or the real-versus-mock testing boundaries. MATMA PHP retains native PascalCase class filenames and PSR-4 mapping.

#### Rule 2 — Isolated Unit Testing with Mocks

When performing TDD on `ClassA`, instantiate **`ClassA` as real** and replace **every external interaction and collaborator** with a mock.

~~~text
Unit test: ClassA
ClassA  REAL
  ├── ClassB  MOCK
  └── ClassC  MOCK
~~~

**Why:** The unit test checks the target class independently of its collaborators. If a failure occurs, the testing boundary limits the possible causes to the target's behavior and its test configuration, rather than an unrestricted dependency graph.

For MATMA Python, module-level mirroring determines the **test file's location**; individual tests inside it still isolate the class or function being verified.

#### Rule 3 — Centralized Mirrored Mock Registries

Reusable mock classes/helpers live under `tests/mocks/mirror/`, following the production source structure. Tests import them from that registry instead of defining equivalent inline mocks.

**Why:** A central registry provides a single source of truth for test doubles, reduces duplicate mock implementations, and helps agents load the relevant dependency substitute directly. Public interface changes require corresponding updates to mirrored mocks and associated interaction tests.

Mocks must use the host language's supported mocking mechanisms; an unsafe or unmockable dependency must not be represented as a working fake without evidence.

#### Rule 4 — Strict Pairwise Interaction Isolation

An interaction test checks a selected call from `ClassA` into `ClassB`. **Both `ClassA` and `ClassB` are real**; **every other downstream collaborator** needed by either class is mocked.

~~~text
Interaction test: ClassA + ClassB
ClassA  REAL
  ├── ClassB  REAL
  │    └── ClassC  MOCK
  └── ClassD  MOCK
~~~

**Why:** Two classes may pass independent unit tests yet fail to exchange parameters, results, or errors correctly. Pairwise verification checks their real contract without accidentally involving a third real dependency and expanding the diagnostic scope into a broader integration graph.

#### Rule 5 — Service-Level Boundary Mocking for Workflows

Business tests under `tests/business/` exercise **real internal domain models, business classes, and workflow logic**. Only **external or infrastructure services** are mocked at their service boundaries—such as databases, LLM providers, vector stores, and HTTP clients.

**Why:** Mocking internal business logic would stop the test from validating the actual workflow. Mocking its infrastructure boundary keeps the process testable while exercising the real internal sequence. These tests are **process-oriented**, not mirrored to a single source class.

#### Rule 6 — Integrated Regression Gateways

MATMA's regression tier, `tests/regression/`, must verify **end-to-end application behavior with real datasets and real, live, or containerized participating services**. Participating services are **not replaced with mocks**. The methodology requires this regression suite as a gateway before merge or deployment.

**Why:** Unit, pairwise, and business tests deliberately isolate parts of the system. They cannot alone establish that all participating components and services work together with real data. The regression tier provides that separate full-system evidence.

**Implementation boundary:** The current MATMA Python and PHP tools select and report verification paths but **do not themselves run tests or enforce the regression gateway**. The application's runner and CI/deployment workflow must carry out that requirement.

#### Rule 7 — Bifurcated Directory Topology (Mirrored vs. Process-Oriented)

MATMA divides test organization into two categories.

**Source-mirrored, class-centric artifacts:**

~~~text
tests/unit/mirror/
tests/interaction/mirror/
tests/mocks/mirror/
tests/mirror/**/*.auto.yaml
~~~

**Process-oriented, non-mirrored tests:**

~~~text
tests/business/
tests/regression/
~~~

**Why:** Unit, interaction, mock, and manifest artifacts have a deterministic relationship with source targets. Business workflows and end-to-end regressions span multiple components and should instead be organized by capability, process, feature, or user journey.

### 4. Four verification tiers and their boundaries

The central distinction is **which components are real and where mocking is permitted**.

| Tier | Real components | Mocked components | Purpose |
| --- | --- | --- | --- |
| **Unit** | One class under test | Every collaborator and external interaction | Isolate class behavior |
| **Interaction** | Exactly two selected collaborating classes | All other downstream collaborators | Verify the contract between the real pair |
| **Business** | Internal domain and workflow classes | External/infrastructure services at the boundary | Verify real internal business processes |
| **Regression** | Full application and participating services | None | Verify real-system behavior and integrity |

Unit testing supplies the narrowest diagnostic boundary. Pairwise interaction testing checks the contract between two real collaborators. Business testing verifies multi-step internal behavior while controlling infrastructure. Regression testing verifies complete behavior with real data and participating services. **These tiers provide complementary evidence, not interchangeable forms of testing.**

### 5. Deterministic structural mirroring

The original class-based blueprint illustrates the relationship between source files and testing artifacts:

~~~text
<source_root>/
└── core/
    └── document_parser.<ext>

tests/
├── unit/mirror/core/
│   └── test_document_parser.<ext>
├── interaction/mirror/core/
│   └── test_document_parser_interaction.<ext>
├── mocks/mirror/core/
│   └── mock_document_parser.<ext>
├── mirror/core/
│   └── document_parser.auto.yaml
├── business/
│   └── test_document_ingestion_workflow.<ext>
└── regression/
    └── test_end_to_end_pipeline.<ext>
~~~

The first four verification artifacts are resolved from source identity. Business and regression tests are deliberately independent of source mirroring. **Actual filenames follow each implementation's accepted naming convention:** the Python module example in Rule 1 and the PHP PSR-4 example in Section 9 illustrate the native forms.

### 6. Persisted dependency and reverse-dependency analysis

MATMA provides an ahead-of-time static indexer. It inspects source Abstract Syntax Trees (ASTs), identifies supported outbound dependencies, builds a dependency graph, reverses those relationships, and writes generated YAML manifests under `tests/mirror/`.

Consider this dependency chain:

~~~text
RagController
     |
     v
SemanticChunker
     |
     v
DocumentParser
~~~

If `DocumentParser` changes, `SemanticChunker` is a direct dependent and `RagController` is an indirect dependent. The source target's manifest records references to its unit, interaction, and mock artifacts, as well as dependencies and precomputed reverse dependents grouped by impact depth.

**Why persist the graph?** Coding agents frequently revisit impact questions while implementing and debugging changes. Indexing once and reading the stored relationships avoids reparsing source ASTs and reconstructing the graph for every impact query.

The optimization concerns **dependency selection**, not a guarantee that the entire command is constant-time. Current implementations read a target manifest and shared index metadata and also hash/stat indexed source files to validate freshness. A missing or stale index requires explicit regeneration.

Generated manifests must not be hand-edited. The existing implementations protect MATMA-owned generated YAML, preserve user-authored files, and report unsafe or stale metadata rather than silently returning misleading selections. Static analysis cannot fully capture dynamic imports, reflection, runtime injection, or other unresolved relationships; these limits need diagnostics and additional workflow verification.

### 7. Progressive verification through impact levels

The `--level` option selects an **inclusive reverse-dependency radius** for class-centric verification.

| Level | Intended scope |
| --- | --- |
| `0` | Unit verification for the target |
| `1` | Level 0 plus relevant interaction tests and directly dependent targets |
| `2+` | Previous levels plus additional transitive dependents within the requested radius |

Selections are deterministic, sorted, and deduplicated. The meaning of dependency edges follows each implementation's accepted ADR: Python distinguishes proven symbol usage from import-only relationships, conservatively including import-only dependents at Level 2; PHP uses its accepted actual-reference rules.

**Business and regression tests are separate tiers**, not class-mirrored tests inferred from a higher `--level`. In the current implementations, developers can register relevant business and regression workflows explicitly in `tests/matma-verification.yaml`. Impact analysis reports these registrations separately without silently executing them.

### 8. MATMA development operations

The architecture defines four CLI operations:

| Operation | Responsibility |
| --- | --- |
| `validate` | Analyze source structure and the applicable language-specific mapping conventions |
| `index` | Parse ASTs, build the dependency graph, calculate reverse dependencies, and persist manifests |
| `scaffold` | Generate missing mirrored unit/interaction test and mock starters, plus metadata |
| `impact` | Read persisted impact metadata to select affected class-centric tests |

**Validation** checks the rules applicable to the implementation. Python uses module-based source identity and does not reject valid multi-class modules; PHP validates its own native class-file and PSR-4 conventions.

**Indexing** refreshes the graph after relevant source or structural changes. The initial implementations rebuild the full indexed graph to keep reverse dependents consistent.

**Scaffolding** creates only missing testing artifacts. The current Python and PHP implementations deliberately generate **failing test placeholders** until developers or agents author meaningful assertions; existing test and mock files are not overwritten.

**Impact analysis** selects existing test paths and reports missing tests and stale index errors. It does not reparse ASTs or execute the tests. The project's native runner—such as `python -m pytest` or `php artisan test`—performs execution.

### 9. Language-specific architecture decisions

MATMA defines the shared testing methodology and verification boundaries. Its language implementations preserve their accepted source naming and testing-framework conventions rather than imposing the same file structure on every ecosystem.

#### MATMA Python — module-based identity

**Accepted PY-ADR-01: the source module is the atomic target, not the class.** A Python file may declare multiple classes, synchronous and asynchronous functions, and methods. MATMA Python maps each testable module to one unit file, one interaction file, one centralized mock helper, and one manifest. It retains the existing `src/`, flat, or configured multi-root layout.

~~~text
src/acme/pricing.py
tests/unit/mirror/src/acme/test_pricing.py
tests/interaction/mirror/src/acme/test_pricing_interaction.py
tests/mocks/mirror/src/acme/mock_pricing.py
tests/mirror/src/acme/pricing.auto.yaml
~~~

The implementation uses Python's `ast`, PyYAML, pytest, and `unittest.mock`. The exception to Rule 1 concerns **structural mapping granularity**, not an exception to unit, pairwise, business, or regression isolation requirements.

#### MATMA PHP — native class-file and PSR-4 mapping

MATMA PHP follows Laravel's PascalCase filenames, PSR-4 namespaces, and existing source structure.

~~~text
app/Services/DocumentParser.php
tests/unit/mirror/Services/DocumentParserTest.php
tests/interaction/mirror/Services/DocumentParserInteractionTest.php
tests/mocks/mirror/Services/MockDocumentParser.php
tests/mirror/Services/DocumentParser.auto.yaml
~~~

The implementation uses `nikic/php-parser`, Symfony YAML, Laravel Artisan commands, and PHPUnit-compatible scaffolding. It does not rename or reorganize application source to follow the blueprint's illustrative snake_case filenames.

| Property | MATMA Python | MATMA PHP |
| --- | --- | --- |
| Atomic mirror identity | Source module/file | Class/file |
| Multiple classes in one source file | Permitted | Restricted by accepted PHP structural rules |
| Mirroring convention | Native Python module paths | Native PascalCase / PSR-4 |
| AST analysis | Python `ast` | `nikic/php-parser` |
| Mock helpers | `unittest.mock` | PHPUnit-compatible mocks |
| Native test runner | pytest | Laravel / PHPUnit |
| Dependency metadata | Generated YAML | Generated YAML |

See the [Python architecture decisions](https://github.com/rzepeda/matma-py/blob/main/AIDocumentation/ARCHITECTURE_DECISIONS.md) and [PHP architecture decisions](https://github.com/rzepeda/matma-php/blob/main/AIDocumentation/ARCHITECTURE_DECISIONS.md) for the language-specific contracts.

### 10. Benefits for autonomous coding agents

The architectural decisions are intended to provide:

- **Lower context consumption:** load relevant source, mock, test, and dependency artifacts rather than repeatedly exploring the repository.
- **Reduced discovery overhead:** derive mirrored artifact paths from source identity instead of searching for companions.
- **Efficient impact queries:** reuse precomputed reverse dependencies rather than rebuilding the source dependency graph during each selection.
- **Precise failure diagnosis:** keep unit and pairwise tests bounded by the exact real-versus-mock rules; distinguish those failures from workflow and full-system failures.
- **Verification proportional to a change:** grow class-centric test selection according to dependency impact while preserving distinct business and regression responsibilities.

These are optimization objectives and expected benefits, **not guaranteed token savings, fixed performance ratios, or proof that selected tests passed**. Actual results depend on the project, dependency graph, test quality, and execution environment.

### 11. Responsibilities and limitations

Adopting MATMA requires maintaining the accepted source/test mappings and test-isolation boundaries:

- **Mock synchronization:** public-interface changes require corresponding updates to centralized mocks and relevant pairwise tests.
- **Tier integrity:** unit tests isolate every collaborator; pairwise tests keep exactly two selected real collaborators; business tests retain real internal logic with mocked service boundaries; regression tests use real participating services.
- **Index freshness:** regenerate manifests after relevant source or dependency changes; do not manually edit generated YAML.
- **Honest diagnostics:** static analysis has limits, and missing tests, unresolved dependencies, and stale data must not be concealed.
- **Real verification:** scaffolding is not test coverage; selecting paths is not executing tests. The native runner executes assertions.
- **Regression gateway:** the methodology requires real-data, real-service verification before merge or deployment; enforcement belongs to the application's CI/deployment process, not the present index/impact commands.

### 12. Conclusion

MATMA's seven rules define a controlled progression of verification: **one real class in unit testing, two selected real collaborators in interaction testing, real internal business classes with mocked infrastructure in workflow testing, and complete real-service, real-data verification in regression testing**.

Deterministic mirroring makes class-centric test and mock artifacts discoverable. Centralized mock registries reduce duplication and context overhead. Persisted reverse dependencies support focused, change-aware selection without repeated AST graph construction.

Together, these decisions aim to make software testing and change verification **deterministic, context-efficient, and diagnostically precise** for autonomous coding agents while preserving the system-level evidence required before release.

## Compatibility

- PHP **8.2+**.
- Laravel **11 onward**, with per-version CI matrices (the Laravel version's own minimum PHP requirement still applies).
- Composer development dependency with Laravel package discovery. No production file changes.
- PHPUnit **or** Pest projects can continue to use Laravel's native `php artisan test`. MATMA generates PHPUnit-compatible tests; its tested baseline is PHPUnit 11, not a separately certified Pest version matrix.

## Installation

From a Laravel application with GitHub access to the repository:

```bash
composer config repositories.matma-php vcs https://github.com/rzepeda/matma-php
composer require --dev rzepeda/matma-php:dev-main
php artisan list --raw
```

Laravel's Composer discovery registers `Matma\Php\MatmaServiceProvider`. If package discovery is disabled, add `Matma\Php\MatmaServiceProvider::class` manually to the application's `bootstrap/providers.php`.

No public distribution or license grant is implied: package metadata currently uses `proprietary`.

## Artisan CLI: index, scaffold, validate and impact

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --target=App\\Services\\DocumentParser
php artisan matma:impact --target=App\\Services\\DocumentParser --level=2 --format=yaml
php artisan matma:validate --source-root=app --check --format=json
```

**`matma:index --scaffold` and `matma:scaffold` create only missing mirrored tests/mocks** and regenerate the index. `matma:validate` audits source without changing files. **`matma:impact` reads persisted YAML and reports selected tests without running them.** New test templates explicitly fail until authored, and scaffolding never overwrites existing PHP test/mock files.

| Command | Current status / purpose |
| --- | --- |
| `matma:index` | **Implemented:** AST graph, reverse dependents, safe YAML regeneration, optional `--scaffold` |
| `matma:scaffold` | **Implemented:** missing failing unit/interaction tests and centralized PHPUnit mock factories |
| `matma:impact` | **Implemented:** manifest-only reverse-dependency selection, inclusive levels, missing/stale diagnostics, registered workflows |
| `matma:validate` | **Implemented:** read-only AST discovery, PSR-4 audit and source-mirror mapping |

Shared flags: `--source-root/-s` (default `app/`), `--tests-root/-t` (`tests/`), `--scaffold/-S` (boolean), `--level/-l` (nonnegative integer, default 0), `--target/-f`, `--check/-c` (boolean), and `--format/-o` (`text`, `json`, or `yaml`).

| Exit code | Meaning |
| --- | --- |
| `0` | Successful indexing, scaffolding, impact selection, or validation (non-strict may still report violations) |
| `1` | Invalid/stale/missing index, unsafe output, invalid target, strict validation failure, or incomplete scaffolding |
| `2` | Reserved for unimplemented future operations; all four current commands have engines |
| `64` | Invalid MATMA flag value (for example negative `--level`) |

The response includes `command`, `status`, `message`, `exit_code`, and valid `options`. Validation adds `summary`, `files`, `symbol_to_source`, and line-level `diagnostics`. Index output adds `summary`, `index_fingerprint`, `target`, and `manifests` (written, unchanged and removed). JSON is intended for agents; `--format=text|json|yaml` works. `validate --check` fails on violations; indexing always refuses invalid source graphs to avoid storing misleading data.

```bash
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:index --target='App\\Services\\DocumentParser' --format=yaml
```

Indexing or scaffolding always rebuilds the **whole** reverse-dependency graph, even with `--target`, to avoid inconsistent dependents. `--target` identifies the requested source in the command response. Generated `tests/mirror/**/*.auto.yaml` are ignored by Git. MATMA never overwrites a YAML file lacking its generated ownership header, and removes stale YAML only when MATMA owns it. Re-running an unchanged index does not rewrite byte-identical manifests. External/unresolved references and dynamic references are explicitly identified; they are not invented as internal dependencies. See [Phase 3](AIDocumentation/PHASE_3_IMPLEMENTATION.md).

### Impact query and registered workflows

```bash
php artisan matma:index --source-root=app --tests-root=tests
php artisan matma:impact --target='App\Services\DocumentParser' --level=2 --format=json
php artisan test tests/unit/mirror/Services/DocumentParserTest.php
```

`--level 0` selects target unit verification; `--level 1` adds direct dependents and interaction verification; `--level 2+` adds deeper reverse dependents inclusively. The command returns sorted `affected_sources`, `tests.unit`, `tests.interaction`, `missing_tests`, and separately `registered_workflows`. It does **not** execute `php artisan test`.

A generated `tests/mirror/.index.auto.yaml` companion enables stale/missing checks without AST reparsing. The query hashes known indexed source files for freshness, so its total filesystem I/O is not constant-time even though reverse-dependency selection uses the single target manifest. To register non-mirrored workflows explicitly, create a user-authored `tests/matma-verification.yaml` containing `business` and `regression` registrations. See [Phase 5](AIDocumentation/PHASE_5_IMPLEMENTATION.md) for the schema, level selection and error codes.

## Manual test and mock topology

MATMA generates missing mirrored **test and mock** files. The package's own unit, interaction and business tests were authored manually to validate the scaffolder; generated consumer test files intentionally fail until authored. Production PHP classes retain Laravel's PascalCase names; tests use PHPUnit's `*Test.php` suffix:

```text
src/Console/MatmaOptions.php
tests/unit/mirror/Console/MatmaOptionsTest.php
tests/interaction/mirror/Console/MatmaCommandInteractionTest.php
tests/mocks/mirror/Console/MockMatmaCommand.php
tests/mirror/                           # generated .auto.yaml outputs, ignored by Git
tests/business/                         # non-mirrored workflow tests
tests/regression/                       # non-mirrored real-service E2E tier
```

The package's authored unit, interaction, and business tests verify its own CLI and scaffolding behavior. The normative real-versus-mock boundaries for consumer testing are described above; generated starters do not by themselves enforce those boundaries. Regression fixtures using real datasets and services remain a separate application/CI responsibility.

Generated tests contain `self::fail('MATMA: implement ...')` and can be executed explicitly with `php artisan test tests/unit/mirror/<path>/ClassTest.php`. The scaffolder does not modify a Laravel project's existing PHPUnit suite configuration; default `php artisan test` discovery may need the generated lower-case mirror directories explicitly configured. Mock factories require real PHPUnit doubles instead of fake passing implementations. See [Phase 4](AIDocumentation/PHASE_4_IMPLEMENTATION.md).

## Full Laravel consumer walkthrough

The fixture application now includes `App\Services\SmokeService` and the dependent `App\Services\SmokeConsumer`. From `tests/fixtures/laravel-app` after Composer installation:

```bash
php artisan matma:index --format=json
php artisan matma:scaffold --target='App\Services\SmokeConsumer' --format=json
php artisan matma:validate --check --format=json
php artisan matma:impact --target='App\Services\SmokeService' --level=1 --format=json
php artisan test tests/unit/mirror/Services/SmokeConsumerTest.php
```

The last command is **expected to fail until assertions are authored**. MATMA-generated stubs never report false success. The fixture's own feature test verifies this deliberate failure and confirms that rerunning scaffolding preserves developer edits.

## Running tests

In a Laravel application: `php artisan test` (the documented execution interface).

To develop MATMA itself, install Composer dependencies and run `composer test`, `composer analyse` (PHPStan level 0) and `composer style:check` (focused PHPCS rules, not full PSR-12). The real Laravel 11 consumer in `tests/fixtures/laravel-app/` installs MATMA via Composer path repository and runs `php artisan test`. [Phase 6 CI run 42336822288](https://github.com/rzepeda/matma-php/actions/runs/42336822288) passed **all seven jobs**: five PHP/Laravel package combinations, the Laravel consumer and Windows/PHP 8.3/Laravel 11. The checked package and Windows jobs each passed **116 tests with 502 assertions**; the Laravel consumer passed **6 tests with 59 assertions**.

## Benchmark and release preparation

The reproducible `benchmarks/benchmark.php` measures AST indexing + YAML writes separately from manifest-based impact selection + indexed-source hash checks. The Phase 6 CI job retains a JSON benchmark artifact. A representative 100-class, five-sample Linux run measured **122.537 ms** indexing versus **7.321 ms** impact lookup (16.737× on that runner only; not a performance guarantee). Run `php benchmarks/benchmark.php --classes=100 --iterations=5` locally to compare your environment. Impact still hashes known source files and does not provide constant-time end-to-end I/O.

[ADOPTION_GUIDE.md](AIDocumentation/ADOPTION_GUIDE.md) covers migrating existing suites, native Laravel naming and troubleshooting. [RELEASE_CHECKLIST.md](AIDocumentation/RELEASE_CHECKLIST.md) proposes **v0.1.0** and documents approval gates, tag creation and optional Packagist publication. **No tag or distribution has been published**; the package currently declares a proprietary license in its metadata. Repository visibility does not grant usage or distribution rights. Pest-specific version certification remains a separate compatibility item.

See [TASKS.md](AIDocumentation/TASKS.md) for Phase 6 acceptance and remaining Phase 0 compatibility details.
