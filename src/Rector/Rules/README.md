# Custom Rector Rules

This directory contains custom Rector rules for PHP Quality Tools.

## Structure

Each custom rule should:
- Extend `Rector\Core\Rector\AbstractRector` (Rector 1.x) or `Rector\Rector\AbstractRector` (Rector 2.x)
- Implement the required methods: `getRuleDefinition()` and `getNodeTypes()`
- Follow PSR-12 coding standards
- Include comprehensive PHPDoc comments

## Example Rule

```php
<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Rules;

use PhpParser\Node;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ExampleCustomRule extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Description of what this rule does',
            [
                new CodeSample(
                    'code before',
                    'code after'
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Node\Stmt\Class_::class];
    }

    /**
     * @param Node\Stmt\Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // Rule implementation
        return $node;
    }
}
```

## Usage

To use custom rules, add them to your `rector.php` or `rector.custom.php`:

```php
use NowoTech\PhpQualityTools\Rector\Rules\ExampleCustomRule;

return [
    'rules' => [
        ExampleCustomRule::class,
    ],
];
```

Or use the custom rules set:

```php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

return RectorConfig::configure()
    ->withSets([
        CustomRulesSet::CUSTOM_RULES,
    ]);
```

