# Laravel installation smoke fixture

This minimal Laravel 11 application installs MATMA through a local Composer **path repository**, exercising package discovery as an actual consumer app. It intentionally includes no MATMA service provider in `bootstrap/providers.php`, because discovery must register it.

From this directory, with Composer available:

```bash
composer install
php artisan list --raw
php artisan test
```

The feature tests launch real `php artisan` subprocesses, inspect exit status and parse JSON diagnostics. Successful smoke tests **do not** mean indexing or scaffolding is implemented; the shells correctly return code 2.
