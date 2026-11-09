# MATMA PHP: mandatory enforcement and acceptance checklist

Read the authoritative MATMA_Seven_Architectural_Rules.md in this same folder first. All seven rules are binding. This checklist applies them to the current Laravel adapter, whose CLI does NOT itself enforce every real-object/mocking rule in authored test bodies.

## Native PHP source and mirrored mapping (Rules 1 and 7)

Preserve the consumer application's existing Composer PSR-4 namespace structure and PHP PascalCase file naming. Never rename production source to match language-agnostic snake_case examples. For an actual production file defining OrderService:

~~~text
app/Services/OrderService.php
tests/unit/mirror/Services/OrderServiceTest.php
tests/interaction/mirror/Services/OrderServiceInteractionTest.php
tests/mocks/mirror/Services/MockOrderService.php
tests/mirror/Services/OrderService.auto.yaml
~~~

Each PHP file declares one primary named class with a matching filename under the Laravel adapter; existing source naming is audited by matma:validate, not rewritten by the skill. The AST engine can also record traits/enums/interfaces/anonymous classes; some non-mockable final/readonly/anonymous classes generate diagnostics. Do not bypass those diagnostics with fabricated doubles.

## Test authors must enforce exact boundaries (Rules 2–6)

| Tier | Real | Mocked | Prohibited |
| --- | --- | --- | --- |
| Unit | Only the target class A | **Every** collaborator and external interaction of A, using centralized mirrored mocks | Instantiating real collaborator B inside an A unit test |
| Interaction | Exactly the selected pair A and B | **All** remaining collaborators of A or B, via the centralized mirror registry | Allowing real C or unrestricted integration graphs |
| Business | Participating internal domain/business classes | **Only** external/infrastructure services at their boundary: databases, HTTP clients, queues, LLM providers, vector stores, etc. | Mocking domain behavior or treating a real external service as a business-tier collaborator |
| Regression | Entire participating application, representative real datasets and real/live/containerized services | **None** of the participating services | Replacing services with mocks, describing a stubbed test as end-to-end, or claiming a skipped gateway passed |

Unit/interaction real-object counts concern participating collaborating classes; passing primitive values is not an extra real collaborator. Record the identity of the chosen real classes and of every mocked boundary in the authored test plan.

Rule 3: central mock factories live in tests/mocks/mirror/... and are imported into tests. No inline mock class definitions or duplicated factories. The existing MATMA scaffolder creates PHPUnit mock factories; verify that each factory actually produces a correctly configured double of the relevant source class/interface.

Rule 6 is a **separate pre-merge/deployment regression gateway**, not a matma:impact selection level. Use only authorized, non-production real/live/containerized services and sanitized representative real datasets. Never connect to production merely to satisfy the rule. If a safe real service or dataset is unavailable, state that the regression gate is BLOCKED and do not quietly substitute a mock. Keep business/regression tests process-oriented, not source-path mirrors.

## Agent workflow

1. Establish a real Laravel project, inspect composer.json PSR-4 mappings and existing tests, and identify the class/workflow under test. A plain non-Laravel PHP project cannot directly run this Artisan integration.
2. Read the production class constructors/dependencies and relevant business contract before inventing tests. Classify the intended tier and explicitly list real classes and mocked boundaries.
3. Run php artisan matma:validate --check --format=json; correct genuine naming violations without rewriting production solely to conform to generic examples.
4. Run php artisan matma:index --format=json. Optionally scaffold a real target with php artisan matma:scaffold --target=app/Services/RealClass.php --format=json. Only missing test/mock files are created; manually authored files must not be clobbered.
5. Replace intentional self::fail('MATMA: implement ...') placeholders with meaningful assertions while satisfying each tier's real/mock boundary. Never replace failing scaffolds with tautological or fake-passing assertions.
6. For business and regression tests, author tests manually under their non-mirrored directories from known requirements or documented bugs. Register existing tests in user-owned tests/matma-verification.yaml only if desired; the registry is advisory, not execution.
7. Reindex after source changes; run php artisan matma:impact --target=app/Services/RealClass.php --level=N --format=json for read-only persisted-graph test selection. This does not parse production AST during query or run tests; freshness checks hash indexed sources.
8. Run selected tests with php artisan test <actual-test-path>. Run the separate real-service regression gateway before merge/deployment where permission and environment are available.
9. Before completion, check Rules 1–7 against every authored test and report actual command results, failed/blocked checks, unimplemented scaffolds, missing tests, mockability diagnostics and assumptions.

## Disqualifying examples

- A unit test instantiates real A and real B: Rule 2 violation.
- A pairwise interaction test instantiates real A, real B and real C: Rule 4 violation.
- An inline mock class defined inside a test rather than imported centrally: Rule 3 violation.
- A business test mocks its own domain service: Rule 5 violation.
- A regression test mocks its database/HTTP provider, or runs without real test infrastructure but claims the gateway passed: Rule 6 violation.
- A workflow test placed inside a mirrored per-class directory or a unit test placed in process-oriented business: Rule 7 violation.

**MATMA's implemented PHP CLI validates naming/mapping and generates/selects files; it does not automatically validate real-versus-mock boundaries within every authored test. A compliant agent must enforce these rules by reading and reviewing its tests and be explicit about incomplete enforcement.**
