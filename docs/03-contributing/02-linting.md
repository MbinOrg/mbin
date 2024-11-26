# Linting

## PHP Code

We use [php-cs-fixer](https://cs.symfony.com/) to automatically fix code style issues according to [Symfony coding standard](https://symfony.com/doc/current/contributing/code/standards.html).

Install tooling via:

```sh
composer -d tools install
```

Try to automatically fix linting errors:

```sh
./tools/vendor/bin/php-cs-fixer fix
```

_Note:_ First time you run the linter, it might take a while. After a hot cache, linting will be much faster.

## JavaScript Code

For JavaScript inside the `assets/` directory, we use ESLint for linting and potentially fix the code style issues.

Install Eslint and its required packages by:

```sh
npm install
```

Run the following command to perform linting:

```sh
npm run lint
```

Run the following command to attempt auto-fix linting issues:

```sh
npm run lint-fix
```

Note that unlike PHP-CS-Fixer, _not all linting problems could be automatically fixed_, some of these would requires manually fixing them as appropiate, be sure to do those.
