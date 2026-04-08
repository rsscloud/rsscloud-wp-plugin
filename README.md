# RSSCloud Plugin Development

Development, testing, and linting tooling for the [RSSCloud](http://rsscloud.co/) WordPress plugin. The plugin adds RSSCloud hub capabilities to WordPress RSS feeds, notifying registered subscribers via HTTP POST when new posts are published.

## Prerequisites

- [Docker](https://www.docker.com/) (running)
- Node.js and npm
- [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (`npm install -g @wordpress/env`)

## Getting Started

```bash
npm install
npx wp-env start
```

## Scripts

### `npm run lint:php`

Checks PHP files against WordPress coding standards using PHPCS. Starts the wp-env container automatically if needed.

### `npm run format:php`

Auto-fixes PHP coding standard violations using PHPCBF.

### `npm run test:php`

Runs the full test suite: linting followed by unit tests.

### `npm run test:unit:php`

Runs only the PHPUnit unit tests (skips linting). Starts the test environment automatically.

### `npm run test:unit:php:coverage`

Runs unit tests with code coverage enabled. Generates an HTML report in `coverage/` and a Clover XML report at `coverage/clover.xml`.

## Project Structure

```
rsscloud/          # Plugin source (deployed to WordPress)
tests/             # PHPUnit test files
composer.json      # PHP dev dependencies (PHPCS, PHPUnit, etc.)
.wp-env.json       # wp-env dev environment config
.wp-env.test.json  # wp-env test environment config
```

The plugin source lives in the `rsscloud/` subdirectory. wp-env mounts it as the active plugin and maps the repo root to `wp-content/plugins/rsscloud-dev` so dev tools (Composer, PHPCS, PHPUnit) can run inside the container.
