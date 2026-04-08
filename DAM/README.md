# PHP DAM Application (DDD & TDD)

This project is built using strict Domain-Driven Design (DDD) principles and tested strictly via Test-Driven Development (TDD). It utilizes the Slim framework for routing API HTTP requests and PHP-DI for dependency injection.

## Project Architecture
- **Domain**: Contains pristine domain definitions (`Entities`, `Value Objects`, `Enums`, `Repository Interfaces`). It has zero external framework dependencies.
- **Application**: Contains the Use Cases (`Services`).
- **Infrastructure**: Contains the persistence implementation (`InMemoryRepositories`) and the delivery mechanisms (`Http/Controllers`).

## Using Composer

[Composer](https://getcomposer.org/) handles all the PHP third-party dependencies (Slim, PHPUnit, PHP-DI) and automatically generates the PSR-4 autoloader to link all our files together.

When you grab this project for the first time, or if you modify dependencies in `composer.json`, you must run:
```bash
composer install
```

If you add new classes and namespaces but run into "Class not found" errors, regenerate the autoloader map with:
```bash
composer dump-autoload
```

## Running Tests

We use [PHPUnit](https://phpunit.de/) for running the TDD suites. Since we mapped `phpunit/phpunit` in our `composer.json`, the binary lives inside the `vendor/bin/` folder.

**Run All Tests:**
```bash
./vendor/bin/phpunit
```

**Run Only User Context Tests:**
```bash
./vendor/bin/phpunit tests/User
```
*(Alternatively, you can target the test suite specifically defined in `phpunit.xml`: `./vendor/bin/phpunit --testsuite "User Suite"`)*

**Run Only Asset Context Tests:**
```bash
./vendor/bin/phpunit tests/Asset
```

## Running the Application Locally

You can spin up PHP's built-in web server and interact with the application via API requests. 
The public directory must be chosen as the document root (`-t public`):

```bash
php -S localhost:8080 -t public
```

You can then test hitting an endpoint:
```bash
curl -X POST http://localhost:8080/api/users \
     -H "Content-Type: application/json" \
     -d '{"name": "Admin", "email": "admin@test.com", "tenantId": "t1"}'
```
