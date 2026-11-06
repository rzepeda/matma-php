# Laravel installation smoke fixture

This minimal Laravel 11 application installs MATMA through a local Composer **path repository**, exercising package discovery as an actual consumer app. It intentionally includes no MATMA service provider in `bootstrap/providers.php`, because discovery must register it.

From this directory, with Composer available:

```bash
COMPOSER_POLICY_ADVISORIES_BLOCK=0 composer install # Laravel 11 legacy fixture only
php artisan list --raw
php artisan test
```

The feature tests launch real `php artisan` subprocesses, inspect exit status and parse JSON diagnostics. Successful smoke tests **do not** mean indexing or scaffolding is implemented; the shells correctly return code 2.

The Composer override above is **only for this historical Laravel 11 CI fixture**: its published security advisories otherwise block dependency resolution. Do not use the override to suppress checks in a production application. The MATMA package itself does not change Composer security policy.
