# SDK for the Solic Service
An SDK for the Solic service. The Solic service is a form spam protection service that analysis form content to determine if it's spam.

To install the dependency:

```
composer require justawebdev/solic-sdk-php
```

Look at `examples/index.php` for an example implementation.

## Compatibility

The SDK requires PHP 8.1 or newer. Its offline suite has been tested on PHP
8.1, 8.2 and 8.3 using the committed lockfile, minimum allowed stable production
dependencies, and newer allowed production dependencies. These are representative
solutions, not a guarantee for every version combination permitted by Composer.
The minimum profile passes but includes legacy dependency deprecation notices
and an abandoned `symfony/debug` package; it is not recommended for new projects.

Rendering currently requires `jenssegers/blade` 1.x and its older Illuminate
components. Composer consumer checks reject installation alongside Laravel 10
and 12. Do not assume compatibility with newer Laravel applications merely
because the PHP version is supported.

Consumers resolve dependencies from `composer.json`; they do not inherit this
library's lockfile. The committed development lockfile requires PHP's `iconv`
extension through a transitive dependency. Other compatible dependency solutions
can resolve without that requirement. Runtime operation without `iconv` has not
been tested. Composer checks the requirements of the versions it installs.

## Running the tests

Install the development dependencies and run PHPUnit:

```sh
composer install
composer test
```

The suite requires PHP 8.1 or newer and the PHP extensions required by PHPUnit
10, including `dom`, `mbstring`, `xml`, and `xmlwriter`. To see each test's name:

```sh
composer test -- --testdox
```

The tests use Guzzle's mock HTTP handler rather than contacting Solic. No API
key, live account, web server, or database is required. They exercise the real
SDK request-building code and render the actual Blade template.

The suite covers:

- Default/custom service URLs, endpoints, authentication headers, and response streams.
- Submission JSON, explicit IP/user-agent values, and fallbacks to `$_SERVER`.
- Preservation of caller data and propagation of HTTP/connection exceptions.
- JSON/object form responses, field types, CSRF fields, and honeypot markup.
- HTML escaping and the explicit opt-in for trusted, unescaped content.

Rendering uses the SDK's existing `src/cache` directory, which must be writable
and is ignored by Git. These are offline behavior tests, not live API integration
or browser tests. They do not establish how the API handles invalid credentials
or submissions, or guarantee that honeypots stop spam.
