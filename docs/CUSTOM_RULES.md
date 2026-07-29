# Custom Rules

PHP Quality Tools lets you add custom rules for both Rector and PHP-CS-Fixer.


## Table of contents

- [Layout](#layout)
- [Custom Rector rules](#custom-rector-rules)
  - [Location](#location)
  - [Rule structure](#rule-structure)
  - [Custom rule example](#custom-rule-example)
  - [Using custom rules](#using-custom-rules)
- [Custom PHP-CS-Fixer fixers](#custom-php-cs-fixer-fixers)
  - [Location](#location-1)
  - [Fixer structure](#fixer-structure)
  - [Custom fixer example](#custom-fixer-example)
  - [Using custom fixers](#using-custom-fixers)
- [Required dependencies](#required-dependencies)
  - [For custom Rector rules](#for-custom-rector-rules)
  - [For custom PHP-CS-Fixer fixers](#for-custom-php-cs-fixer-fixers)
- [Bundled custom rules](#bundled-custom-rules)
  - [Rector](#rector)
  - [PHP-CS-Fixer](#php-cs-fixer)
- [Adding new rules](#adding-new-rules)
- [Testing](#testing)
- [Using the bundled rules](#using-the-bundled-rules)
  - [Enable custom Rector rules](#enable-custom-rector-rules)
  - [Enable custom PHP-CS-Fixer fixers](#enable-custom-php-cs-fixer-fixers)
- [Resources](#resources)

## Layout

Custom rules live under these directories:

```
src/
  Rector/
    Rules/          # Custom Rector rules
    Set/            # Helpers to load rules
  PhpCsFixer/
    Rules/          # Custom PHP-CS-Fixer fixers
    Set/            # Helpers to load fixers
```

## Custom Rector rules

### Location

Place custom Rector rules in `src/Rector/Rules/`.

### Rule structure

Each rule must:

- Extend `Rector\Rector\AbstractRector` (Rector 2.x) or `Rector\Core\Rector\AbstractRector` (Rector 1.x)
- Implement the required methods: `getRuleDefinition()` and `getNodeTypes()`
- Implement `refactor()` to perform the transformation
- Follow PSR-12
- Include complete PHPDoc documentation

**Important:** In Rector 2.x, the correct method is `getRuleDefinition()` returning a `RuleDefinition`, **not** `getDescription()`.

### Custom rule example

```php
<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Rules;

use PhpParser\Node;
use Rector\Rector\AbstractRector; // Rector 2.x
// use Rector\Core\Rector\AbstractRector; // Rector 1.x
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

### Using custom rules

#### Option 1: Use the set helper

```php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

return [
    'rules' => CustomRulesSet::getRules(),
];
```

#### Option 2: Add manually in `.rector.custom.php`

```php
use NowoTech\PhpQualityTools\Rector\Rules\ExampleCustomRule;

return [
    'rules' => [
        ExampleCustomRule::class,
    ],
];
```

#### Option 3: Add directly in `.rector.php`

```php
use Rector\Config\RectorConfig;
use NowoTech\PhpQualityTools\Rector\Rules\ExampleCustomRule;

return RectorConfig::configure()
    ->withRules([
        ExampleCustomRule::class,
    ]);
```

## Custom PHP-CS-Fixer fixers

### Location

Place custom PHP-CS-Fixer fixers in `src/PhpCsFixer/Rules/`.

### Fixer structure

Each fixer must:

- Extend `PhpCsFixer\Fixer\AbstractFixer` or implement `PhpCsFixer\Fixer\FixerInterface`
- Implement the required methods: `getName()`, `getPriority()`, `supports()`, `isCandidate()`, and `fix()`
- Follow PSR-12
- Include complete PHPDoc documentation

### Custom fixer example

```php
<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\PhpCsFixer\Rules;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;

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

### Using custom fixers

#### Option 1: Use the set helper

```php
use NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet;

$config = (new Config())
    ->registerCustomFixers(CustomFixersSet::getFixers())
    ->setRules(array_merge([
        '@PSR12' => true,
        // ... other rules
    ], CustomFixersSet::getRules()));
```

#### Option 2: Add manually in `.php-cs-fixer.custom.php`

```php
use NowoTech\PhpQualityTools\PhpCsFixer\Rules\ExampleCustomFixer;

return [
    'rules' => [
        'NowoTech/example_custom_fixer' => true,
    ],
];
```

#### Option 3: Add directly in `.php-cs-fixer.php`

```php
use PhpCsFixer\Config;
use NowoTech\PhpQualityTools\PhpCsFixer\Rules\ExampleCustomFixer;

$config = (new Config())
    ->registerCustomFixers([
        new ExampleCustomFixer(),
    ])
    ->setRules([
        '@PSR12' => true,
        'NowoTech/example_custom_fixer' => true,
        // ... other rules
    ]);
```

## Required dependencies

### For custom Rector rules

Custom Rector rules need this extra dependency:

- **symplify/rule-doc-generator-contracts**: Required for rule documentation (`RuleDefinition`, `CodeSample`)

**Install:**

```bash
composer require --dev symplify/rule-doc-generator-contracts
```

**Note:** If you use custom rules without this dependency, you will see an informative message listing the missing package and how to install it.

### For custom PHP-CS-Fixer fixers

Custom PHP-CS-Fixer fixers need no extra dependencies; they use classes from `friendsofphp/php-cs-fixer`, which is already in the suggested dependencies.

## Bundled custom rules

### Rector

The package ships these custom Rector rules:

1. **SplitLongGroupedImportsRector**: Formats long grouped imports as multiline when they exceed 120 characters or have 3+ items.

2. **SplitLongConstructorParametersRector**: Splits long constructor parameter lists across multiple lines when they exceed 120 characters.

3. **AddMissingReturnTypeRector**: Adds missing return types to public and protected methods based on method-body analysis.

4. **SplitLongMethodCallRector**: Identifies long method-call chains (3+ chained calls or >120 characters) and marks them for multiline formatting.

**Required dependency:** `symplify/rule-doc-generator-contracts`

These rules are available automatically when you use `CustomRulesSet::getRules()`. If the dependency is missing, an informative message is shown.

### PHP-CS-Fixer

The package ships these custom fixers:

1. **MultilineGroupedImportsFixer**: Formats long grouped imports as multiline when they exceed 120 characters or have 3+ items.

2. **MultilineArrayFixer**: Formats long arrays as multiline when they exceed 120 characters or have 3+ elements.

3. **ConsistentDocblockFixer**: Keeps consistent docblock formatting with proper alignment and spacing.

These fixers are available automatically when you use `CustomFixersSet::getFixers()` and `CustomFixersSet::getRules()`.

## Adding new rules

To add a new custom rule:

1. **Create the rule class** in the right directory:
   - Rector: `src/Rector/Rules/YourCustomRule.php`
   - PHP-CS-Fixer: `src/PhpCsFixer/Rules/YourCustomFixer.php`

2. **Update the matching set helper**:
   - Rector: add the class to `CustomRulesSet::getRules()` in `src/Rector/Set/CustomRulesSet.php`
   - PHP-CS-Fixer: add the instance to `CustomFixersSet::getFixers()` and the config to `CustomFixersSet::getRules()` in `src/PhpCsFixer/Set/CustomFixersSet.php`

3. **Add tests** for the new rule under `tests/`

4. **Update documentation** if needed

## Testing

Each custom rule should have associated tests:

- **Rector**: tests in `tests/Rector/Rules/`
- **PHP-CS-Fixer**: tests in `tests/PhpCsFixer/Rules/`

Run tests with:

```bash
composer test
```

## Using the bundled rules

### Enable custom Rector rules

**Before using the rules**, install the required dependency:

```bash
composer require --dev symplify/rule-doc-generator-contracts
```

Custom rules are available automatically via `CustomRulesSet`:

```php
// In .rector.php or .rector.custom.php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

return [
    'rules' => CustomRulesSet::getRules(), // Checks dependencies automatically
    // ... other configuration
];
```

**Automatic validation:** `CustomRulesSet::getRules()` checks whether dependencies are installed. If any are missing, it prints an informative message to STDERR (CLI) or raises a warning (web).

To disable the check (for example when enabling rules conditionally):

```php
// Disable dependency checks
$rules = CustomRulesSet::getRules(checkDependencies: false);
```

**Manual dependency check:**

```php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

// Check whether all dependencies are installed
if (!CustomRulesSet::hasAllDependencies()) {
    $missing = CustomRulesSet::getMissingDependencies();
    echo 'Missing dependencies: ' . implode(', ', $missing) . "\n";
    echo 'Install with: composer require --dev ' . implode(' ', $missing) . "\n";
}

// List missing dependency package names
$missingPackages = CustomRulesSet::getMissingDependencies();
```

Or register them manually:

```php
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongGroupedImportsRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongConstructorParametersRector;

return [
    'rules' => [
        SplitLongGroupedImportsRector::class,
        SplitLongConstructorParametersRector::class,
    ],
];
```

**Important note:** `SplitLongConstructorParametersRector` identifies constructors that need multiline formatting, but the actual formatting should be applied with PHP-CS-Fixer using the `method_argument_space` rule with `ensure_fully_multiline`.

### Enable custom PHP-CS-Fixer fixers

Custom fixers are available automatically via `CustomFixersSet`:

```php
// In .php-cs-fixer.php or .php-cs-fixer.custom.php
use NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet;

$config = (new Config())
    ->registerCustomFixers(CustomFixersSet::getFixers())
    ->setRules(array_merge([
        '@PSR12' => true,
        'method_argument_space' => ['ensure_fully_multiline' => true], // For SplitLongConstructorParametersRector
        // ... other rules
    ], CustomFixersSet::getRules()));
```

## Resources

- [Rector documentation](https://getrector.com/documentation)
- [PHP-CS-Fixer documentation](https://cs.symfony.com/)
- [Creating Rector rules](https://getrector.com/documentation/how-it-works)
- [Creating PHP-CS-Fixer fixers](https://cs.symfony.com/doc/custom_fixers.html)
