# Tests

PHPUnit tests with the WordPress test suite ([wp-phpunit](https://github.com/wp-phpunit/wp-phpunit)): the settings and their sanitizing, the Google embed URL from an address or embed code, the markup of every size and cover option, the edit screen, saving and the preview's request.

GitHub Actions runs them on every push to `main` and pull request (`.github/workflows/tests.yml`: PHP 7.4 with WordPress 6.0 and 7.1, PHP 8.3 with WordPress 7.1), and before every release: a tag whose tests fail gets no release.

## Running them locally

Needs PHP 7.4+ with `mysqli`, Composer, WP-CLI and a MySQL database **for the tests only** (they drop and recreate its tables).

```
composer install
wp core download --version=7.1 --path=/tmp/wordpress --skip-content
WP_CORE_DIR=/tmp/wordpress WP_TESTS_DB_HOST=localhost tests/vendor/bin/phpunit
```

The environment variables `tests/wp-tests-config.php` reads:

| Variable | Default |
|---|---|
| `WP_CORE_DIR` | `/tmp/wordpress` |
| `WP_TESTS_DB_NAME` | `wp_map_embed_tests` |
| `WP_TESTS_DB_USER` | `root` |
| `WP_TESTS_DB_PASSWORD` | (empty) |
| `WP_TESTS_DB_HOST` | `localhost` |

## Writing tests

Files named `test-*.php` in this folder. `WP_Map_Embed_TestCase` (`class-wp-map-embed-testcase.php`) creates maps and reads the classes, style and frame URL of the markup a map renders.
