# Verified MATMA Laravel commands

Always operate from the consumer Laravel root (containing artisan and composer.json). PHP 8.2+ and a compatible Laravel 11/12/13 project are required.

~~~bash
php -v
composer show rzepeda/matma-php
php artisan list --raw
php artisan matma:validate --source-root=app --tests-root=tests --check --format=json
php artisan matma:index --source-root=app --tests-root=tests --format=json
php artisan matma:scaffold --source-root=app --tests-root=tests --target=app/Services/OrderService.php --format=json
php artisan matma:impact --source-root=app --tests-root=tests --target=app/Services/OrderService.php --level=2 --format=json
php artisan test tests/unit/mirror/Services/OrderServiceTest.php
~~~

OrderService.php is a sample, not an asserted existing class. Source files are mirrored under tests/unit/mirror, tests/interaction/mirror and tests/mocks/mirror. Generated manifest files are under tests/mirror, including .index.auto.yaml, and should be ignored in Git. Do not hand-edit them.

Validation is read-only. The --check flag makes structural errors fail. Index rebuilds all reverse-dependency manifests even for one target. Scaffolding creates missing tests and PHPUnit mock factories without overwriting user-authored PHP. A generated self::fail('MATMA: implement ...') must be replaced with real assertions before the test is complete; unmockable final/readonly/anonymous classes produce diagnostics. For a targeted scaffold, use a real class path under app or a Composer PSR-4 FQCN.

Impact is read-only and requires --target. Level 0 selects only the target unit test; level 1 adds immediate dependents and interaction tests; higher levels include deeper reverse dependents. JSON includes affected_sources, tests.unit, tests.interaction, missing_tests and registered_workflows. Impact does not execute any tests, and does not reparse PHP ASTs, although it hashes known source files to check index freshness.

Use php artisan test <actual-test-path> to execute selected tests. For entire suites ensure the consuming project's phpunit.xml or Pest suite configuration includes the mirrored directories; MATMA does not rewrite suite config.

Statuses: exit 0 = operation successful; exit 1 = errors, unsafe/stale/missing manifest or unmockable scaffold requirements; exit 64 = invalid option. Inspect JSON status rather than inventing results. On missing_manifest, missing_index or stale_index run matma:index again. Never bypass Composer security advisories to force installation. The private GitHub repository must be accessible to Composer.
