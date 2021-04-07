<?php
/**
 * @see https://hackernoon.com/how-to-configure-phpstorm-to-use-php-cs-fixer-1844991e521f
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

require __DIR__ . '/vendor/autoload.php';

$finder = (new Finder())
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

/**
 * Cache file for PHP-CS
 */
$cacheFilePath = sprintf('%s%sphp_cs.cache-%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, md5(__DIR__));

/**
 * Configuration
 *
 * @see https://mlocati.github.io/php-cs-fixer-configurator/#
 */
return (new Config('pecee-pixie'))
    ->setCacheFile($cacheFilePath)
    ->setRiskyAllowed(true)
    ->setRules([
        // default
        '@PSR2'                      => true,
        '@Symfony'                   => true,
        // additionally
        'array_syntax'               => ['syntax' => 'short'],
        'concat_space'               => false,
        'cast_spaces'                => false,
        'no_unused_imports'          => false,
        'no_useless_else'            => true,
        'no_useless_return'          => true,
        'no_superfluous_phpdoc_tags' => false,
        'ordered_imports'            => true,
        'phpdoc_align'               => true,
        'phpdoc_order'               => true,
        'phpdoc_trim'                => true,
        'phpdoc_summary'             => false,
        'simplified_null_return'     => false,
        'ternary_to_null_coalescing' => true,
        'binary_operator_spaces'     => ['default' => 'align'],
    ])
    ->setFinder($finder)
    ;
