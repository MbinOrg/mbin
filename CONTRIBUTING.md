# Contributing to Mbin

We always welcome new supporters and contributors. A quick list below of possible ways to contribute to Mbin.

> _Note_:
> We are all volunteers. Please be nice! ❤

## Code

The code code is mainly written in PHP using the Symfony framework with Twig templating and a bit of JavaScript & CSS.

With an account on [GitHub](https://github.com) you will be able to [fork this repository](https://github.com/MbinOrg/mbin) and `git clone` the repository locally if you wish.

\_Note:\_If you are a contributor with GitHub org rights, you do not need to fork the project, instead you are allowed to use branches.

### Way of Working

See below our agreements / guidelines how we work together in the project (in random order):

- Respect each other naturally.
- [Discuss](https://matrix.to/#/#mbin:melroy.org) (big) issues/changes in the community first and have some consensus (WIP: we are still looking into some voting tool that might help here).
- Always [create a new Pull Request](https://github.com/MbinOrg/mbin/pulls) in GitHub. Eg. Do _NOT_ merge directly to the `main` branch!
- All tests should be passing (**all green**) in the PR. This is to avoid regression. More info about the coding style as well as testing see below.
- Pull Requests require at least one (1) other maintainer approval before the PR gets merged (built-in peer review process).
- If a contributor has merge right, do _not_ merge a PR on their behalf (unless the contibutor agrees you can merge it).
  Leave it to the contributor when to merge the PR. This is to also avoid regression or merging unfinished work by accident.
  - In case of an _external_ contributor who does not have merge rights, anybody in the community with merge rights can merge the PR.
- Existing GitHub Organization owners are allowed to add another GitHub users to the repository or to the GitHub organization. That being said, _be careful_ who to add and discuss on [Matrix](https://matrix.to/#/#mbin:melroy.org) first.

### Coding Style Guide

We use [php-cs-fixer](https://cs.symfony.com/) to automatically fix code style issues according to [Symfony coding standard](https://symfony.com/doc/current/contributing/code/standards.html).  
It is based on the [PHP-FIG coding standards](https://www.php-fig.org/psr/).

Install PHP-CS-Fixer first: `composer -d tools install`

Then run the following command trying to auto-fix the issues: `./tools/vendor/bin/php-cs-fixer fix`

### Tests

When fixing a bug or implementing a new feature or improvement, we expect that test code will also be included with every delivery of production code.

There are three levels of tests that we distinguish between:

- Unit Tests: test a specific unit (SUT), mock external functions/classes/database calls, etc. Unit-tests are fast, isolated and repeatable
- Integration Tests: test larger part of the code, combining multiple units together (classes, services or alike).
- Application Tests: test high-level functionality, APIs or web calls.

For more info read: [Symfony Testing guide](https://symfony.com/doc/current/testing.html).

### Fixtures

You might want to load random data to database instead of manually adding magazines, users, posts, comments etc.  
To do so, execute: `bin/console doctrine:fixtures:load --append --no-debug`

Please note, that the command may take some time and data will not be visible during the process, but only after the finish.

- Omit `--append` flag to override data currently stored in the database
- Customize inserted data by editing files inside `src/DataFixtures` directory

## Translations

Translations are done in [Weblate](https://hosted.weblate.org/projects/mbin/).

## Documentation

Documentation is stored at in the [`docs` folder](docs) within git. Create a [new pull request](https://github.com/MbinOrg/mbin/pulls) with changes to the documentation files.

## Community

We have a very active [Matrix community](https://matrix.to/#/#mbin:melroy.org). Feel free to join our community, ask questions, share your ideas or help others!

## Reporting Issues

If you observe an error or any other issue, [create an new issue](https://github.com/MbinOrg/mbin/issues) in GitHub. A couple notes about this:

- Please try to verify that your issue is not already present before you create a new issue. You can search on existing open issues.
- We actually prefer you to **not** include `[Feature Request]` or `[Bug Report]` or similar tags in the title. Instead, we'll add the labels for you.
- If you're reporting an issue that happened while you're on a specific instance, please include **the URL**.
- If the issue is related to design/UI, please also **include screenshots**.
- If you're the server admin and have access to logging, please also **include logs** when relevant.

## Reporting Security Vulnerability

Contact Melroy (`@melroy:melroy.org`) or any other community member you trust via Matrix, using an encrypted room.

## I Have a Question

If you still feel the need for asking a question, we recommend [joining our community on Matrix](https://matrix.to/#/#mbin:melroy.org) where you can ask your questions to our community members.
