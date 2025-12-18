# Custom PHP-CS-Fixer Rules

This directory contains custom PHP-CS-Fixer fixers for PHP Quality Tools.

## Structure

Each custom fixer should:
- Extend `PhpCsFixer\Fixer\AbstractFixer` or implement `PhpCsFixer\Fixer\FixerInterface`
- Implement the required methods: `getName()`, `getPriority()`, `supports()`, `isCandidate()`, and `fix()`
- Follow PSR-12 coding standards
- Include comprehensive PHPDoc comments

## Example Fixer

```php
<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\PhpCsFixer\Rules;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;

final class ExampleCustomFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'NowoTech/example_custom_fixer';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Description of what this fixer does.',
            [
                new CodeSample('<?php $a = 1;'),
            ]
        );
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_STRING);
    }

    public function supports(\SplFileInfo $file): bool
    {
        return true;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        // Fixer implementation
    }
}
```

## Usage

To use custom fixers, add them to your `.php-cs-fixer.php` or `.php-cs-fixer.custom.php`:

```php
use NowoTech\PhpQualityTools\PhpCsFixer\Rules\ExampleCustomFixer;

return (new Config())
    ->registerCustomFixers([
        new ExampleCustomFixer(),
    ])
    ->setRules([
        'NowoTech/example_custom_fixer' => true,
        // ... other rules
    ]);
```

