<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'node_modules',
        'vendor',
        'docker',
    ])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,

        # defined as "risky" as they could break code. Since our codebase is passing that's fine
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'native_function_invocation' => true,
        'phpdoc_to_comment' => [
            'ignored_tags' => ['var']
        ],

        # new rules which have yet to be applied to our codebase
        # TODO enable later
        'no_useless_else' => false,
        'no_trailing_whitespace' => false,
        'statement_indentation' => false,
        'no_extra_blank_lines' => false,
        'no_whitespace_in_blank_line' => false,
        'no_unneeded_control_parentheses' => false,
        'blank_line_before_statement' => false,
        'blank_line_after_opening_tag' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ;
