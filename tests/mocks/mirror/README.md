# Centralized test doubles

Tests in this repository use real classes for their target and centralize the required doubles here. `Console/MockMatmaCommand.php` is a deliberately named command double used to exercise the shared Artisan shell independently of any future indexing/scaffolding engine. Mocks do not make assertions pass by inventing success behavior.
