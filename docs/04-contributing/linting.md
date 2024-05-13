# Linting

Install tooling via:

```bash
composer -d tools install
```

Try to automatically fix linting errors:

```bash
tools/vendor/bin/php-cs-fixer fix
```

_Note:_ First time you run the linter, it might take a while. After a hot cache, linting will be faster.
