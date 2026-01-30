# Contributing

Thanks for considering contributing to Mbin! We appreciate your interest in helping us improve the project.

## Code

Mbin uses a number of frameworks for different purposes:

1. Symfony - the PHP framework that runs it all. This is our backend framework, which is handling all requests and connecting all the other frameworks together.
2. Doctrine - our ORM to make it easier to make calls to the database.
3. Stimulus - the frontend framework that ties in nicely to Twig and Symfony. It is very vanilla JavaScripty, but allows for easily reusable component code.
4. Twig - a simple templating language to write reusable components that are rendered through PHP, so on the server-side.

Follow the [getting started instructions](01-getting_started.md) to setup a development server.

## Coding Style

Please, follow the [linting guide](02-linting.md).

## Way of Working

Comply with **our version** of [Collective Code Construction Contract (C4)](03-C4.md) specification. Read this document to understand how we work together and how the development process works at Mbin.

## Translations

Translations are done in [Weblate](https://hosted.weblate.org/projects/mbin/).

## Documentation

Documentation is stored at in the [`docs` folder](https://github.com/MbinOrg/mbin/tree/main/docs) within git. Create a [new pull request](https://github.com/MbinOrg/mbin/pulls) with changes to the documentation files.

## Community

We have a very active [Matrix community](https://matrix.to/#/#mbin:melroy.org). Feel free to join our community, ask questions, share your ideas or help others!

## Reporting Issues

If you observe an error or any other issue, [create an new issue](https://github.com/MbinOrg/mbin/issues) in GitHub. And select the correct issue template.

## Reporting Security Vulnerability

Contact Melroy (`@melroy:melroy.org`) or any other community member you trust via Matrix chat, using an encrypted room.
