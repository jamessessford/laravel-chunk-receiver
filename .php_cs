<?php

$finder = Symfony\Component\Finder\Finder::create()
    ->notPath('vendor')
    ->in([
        __DIR__ . '/config/',
        __DIR__ . '/src/',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);


return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,

        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline_array' => true,

        'ordered_imports' => ['sortAlgorithm' => 'alpha'],
        'no_unused_imports' => true,

        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'cast_spaces' => true,
        'not_operator_with_successor_space' => false,


        'blank_line_before_statement' => [
            'statements' => [],
        ],

        'phpdoc_scalar' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,

        'class_attributes_separation' => [
            'elements' => [
                'method', 'property',
            ],
        ],
        'visibility_required' => ['property', 'method'],

        'protected_to_private' => true,

        'explicit_string_variable' => true,
    ])
    ->setFinder($finder);
