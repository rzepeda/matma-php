# MATMA's four test tiers: normative authoring semantics

Before authoring or revising tests read the complete [Seven Architectural Rules](MATMA_Seven_Architectural_Rules.md) and [PHP enforcement checklist](php-enforcement.md). Rules 2–6 determine real-vs-mock boundaries; Rule 7 defines topology. A generic Laravel integration test is not necessarily a MATMA pairwise interaction test.

## Unit — tests/unit/mirror/<path>/ClassNameTest.php

**Real:** only the class under test A. **Mock:** every collaborator of A, including internal classes and external dependencies. Use centrally defined, reusable factories from tests/mocks/mirror. Do not define inline mock classes or allow a second real collaborating class. Assert actual positive, negative, edge and error behavior. MATMA-generated unit files deliberately contain self::fail(...) until replaced with real assertions.

## Interaction — tests/interaction/mirror/<path>/ClassNameInteractionTest.php

**Real:** precisely the selected collaborating pair A calling B. **Mock:** all other downstream collaborators of either A or B using centralized mirrored mock factories. Explicitly identify the real pair in the test plan. Avoid broad framework integration graphs and real DB/queue/HTTP collaborators that would introduce a third real class. Generated files are placeholders until behavioral assertions and the pairwise boundary are verified.

## Business — tests/business/WorkflowNameTest.php

**Real:** participating internal domain and business-logic objects through the whole multi-step workflow. **Mock:** only external or infrastructure services at the service boundary, such as DB, LLM provider, HTTP client, vector store, queue adapter. Do not mock the workflow's internal logic. Process-oriented directory organization by feature/journey, never mirrored from an app class path. MATMA does not automatically generate business tests. Author from validated requirements and representative inputs.

## Regression — tests/regression/EndToEndNameTest.php

**Real:** complete participating application, representative real datasets and real/live/containerized services. **Mock:** none of the participating services. Run as an explicitly planned pre-merge/deploy gateway, using authorized non-production services only. Do not silently downgrade to mocked E2E, or say a blocked gateway passed. MATMA does not automatically generate regression tests; author meaningful end-to-end cases from verified contracts/bugs.

## Manifest, registration and execution

Class-centric mirrors are tests/unit/mirror, tests/interaction/mirror, tests/mocks/mirror and tests/mirror/**/*.auto.yaml. Process-oriented workflows in tests/business and tests/regression do not mirror source paths.

Developer-owned tests/matma-verification.yaml can explicitly register business/regression tests by source path/FQCN using the example YAML in this skill. Registry matches are advisory. matma:impact returns selected paths, not executed tests. Run actual paths via php artisan test; separately require Rule 6's real-data real-service regression gate before merge/deployment.

Report generated scaffolds vs authored assertions vs executed test results, the exact real/mock boundary in each tier, missing tests, registered workflows and any blocked real-service regression environment. Never count self::fail(...) or assertTrue(true) as completed coverage.
