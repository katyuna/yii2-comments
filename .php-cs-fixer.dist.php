<?php

declare(strict_types=1);

// Code style: PSR-12. Check with `make lint`, fix with `make fix`.
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'runtime', 'web/assets', 'tests/Support/_generated', 'tests/e2e', 'docker'])
    ->notPath(['c3.php', 'config/__autocomplete.php'])
    // Views mix PHP and HTML, PSR-12 rules do not fit them well
    ->notPath('#(^|/)views/#')
    ->append([__DIR__ . '/yii']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/runtime/.php-cs-fixer.cache');
