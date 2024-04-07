# Composer plugin for syncing your shared composer scripts between projects.

> **Not ready, this is PoC and I need to re-implement it - it needs to do file discovery in vendors, like phpstan/extension-installer**

## Installation

First install the plugin.

```bash
composer require strictphp/sync-composer-plugin --dev
```

Then add the plugin to your `composer.json` file.

```json
{
  "extra": {
    "sync-scripts": {
      "source": "composer-scripts.json"
    }
  }
}
```

Then create a `composer-scripts.json` file in your project root directory and include this content:

```json
{
  "scripts": {
    "test": "phpunit",
    "lint": "phpstan analyse src tests",
    "fix": "php-cs-fixer fix"
  }
}
```

You can even extend scripts from another file (relative path).

```json
{
  "extends": "vendor/strictphp/conventions/composer-scripts.json",
  "scripts": {
    "test": "phpunit --testsuite=unit",
    "lint": "phpstan analyse src tests --level=7",
    "fix": "php-cs-fixer fix --dry-run"
  }
}
```


