# Contributing to Mbin

We always welcome new supporters and contributors. A quick list below of possible ways to contribute to Mbin.

### Way of Working

Comply with **our version** of [Collective Code Construction Contract (C4)](C4.md) specification. Read this document to understand how we work together and how the development process works at Mbin.

Below some general non-technical agreements and guidelines:

- **Respect for Others**: Treat everyone involved in the project with respect, regardless of their role or level of expertise. Value their opinions and contributions.
- **Effective Communication**: Encourage clear, concise, and respectful communication among team members. Listen actively and considerately to others' perspectives.
- **Constructive Feedback**: Provide feedback in a constructive manner, focusing on the improvement of the work rather than criticizing individuals.
- **Professional Language**: Refrain from using offensive language, including swearing, derogatory remarks, or discriminatory language.
- **Positive Environment**: Foster a positive and inclusive work environment where everyone feels comfortable expressing their ideas and concerns.
- **Conflict Resolution**: Address conflicts or disagreements promptly and professionally. Encourage open dialogue and find mutually beneficial solutions.
- **Team Collaboration**: Encourage collaboration and teamwork, recognizing that everyone brings unique skills and perspectives to the project.
- **Accountability**: Take responsibility for your actions and commitments. Honor agreements, and communicate openly if there are any challenges or issues.
- **Appreciation and Recognition**: Acknowledge and appreciate the efforts and achievements of team members. Recognize their contributions publicly and privately.
- **Embrace Fun and Camaraderie**: Encourage team members to engage in light-hearted conversations, share jokes, and enjoy each other's company. Take breaks to socialize and build relationships beyond the scope of the project.

## Code

The code is mainly written in PHP using the Symfony framework with Twig templating and a bit of JavaScript & CSS.

With an account on [GitHub](https://github.com) you will be able to [fork this repository](https://github.com/MbinOrg/mbin) and `git clone` the repository locally if you wish.

> [!Note]
> If you are a Maintainer with GitHub org admin rights, you do NOT need to fork the project, instead you are allowed to use git branches. See also [C4](C4.md).

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

#### Unit Tests

- First increase execution time in your PHP config file: `/etc/php/8.2/fpm/php.ini`:

```ini
max_execution_time = 120
```

- Increase/set max_nesting_level in `/etc/php/8.2/fpm/conf.d/20-xdebug.ini`:

```ini
xdebug.max_nesting_level=512
```

- Restart the PHP-FPM service: `sudo systemctl restart php8.2-fpm.service`
- Copy the dot env file: `cp .env.example .env`
- Install composer packages: `composer install --no-scripts`

Running the unit tests by executing:

```bash
SYMFONY_DEPRECATIONS_HELPER=disabled ./bin/phpunit tests/Unit
```

### Fixtures

You might want to load random data to database instead of manually adding magazines, users, posts, comments etc.
To do so, execute: `bin/console doctrine:fixtures:load --append --no-debug`

If you want to do the same but with the Docker setup, execute: `docker compose exec php bin/console doctrine:fixtures:load --append --no-debug` (from the `docker` directory)

Please note, that the command may take some time and data will not be visible during the process, but only after the finish.

- Omit `--append` flag to override data currently stored in the database
- Customize inserted data by editing files inside `src/DataFixtures` directory

## Translations

Translations are done in [Weblate](https://hosted.weblate.org/projects/mbin/).

## Documentation

Documentation is stored at in the [`docs` folder](https://github.com/MbinOrg/mbin/tree/main/docs) within git. Create a [new pull request](https://github.com/MbinOrg/mbin/pulls) with changes to the documentation files.

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
