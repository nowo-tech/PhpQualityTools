# Custom Rules

PHP Quality Tools permite añadir reglas custom tanto para Rector como para PHP-CS-Fixer.

## Estructura

Las reglas custom se organizan en los siguientes directorios:

```
src/
  Rector/
    Rules/          # Reglas custom de Rector
    Set/            # Helpers para cargar reglas
  PhpCsFixer/
    Rules/          # Fixers custom de PHP-CS-Fixer
    Set/            # Helpers para cargar fixers
```

## Reglas Custom de Rector

### Ubicación

Las reglas custom de Rector deben ubicarse en `src/Rector/Rules/`.

### Estructura de una Regla

Cada regla debe:
- Extender `Rector\Rector\AbstractRector` (Rector 2.x) o `Rector\Core\Rector\AbstractRector` (Rector 1.x)
- Implementar los métodos requeridos: `getRuleDefinition()` y `getNodeTypes()`
- Implementar el método `refactor()` que realiza la transformación
- Seguir los estándares PSR-12
- Incluir documentación PHPDoc completa

**Importante**: En Rector 2.x, el método correcto es `getRuleDefinition()` que retorna un `RuleDefinition`, NO `getDescription()`.

### Ejemplo de Regla Custom

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
            'Descripción de lo que hace esta regla',
            [
                new CodeSample(
                    'código antes',
                    'código después'
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
        // Implementación de la regla
        return $node;
    }
}
```

### Uso de Reglas Custom

#### Opción 1: Usar el Set Helper

```php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

return [
    'rules' => CustomRulesSet::getRules(),
];
```

#### Opción 2: Añadir manualmente en .rector.custom.php

```php
use NowoTech\PhpQualityTools\Rector\Rules\ExampleCustomRule;

return [
    'rules' => [
        ExampleCustomRule::class,
    ],
];
```

#### Opción 3: Añadir directamente en .rector.php

```php
use Rector\Config\RectorConfig;
use NowoTech\PhpQualityTools\Rector\Rules\ExampleCustomRule;

return RectorConfig::configure()
    ->withRules([
        ExampleCustomRule::class,
    ]);
```

## Fixers Custom de PHP-CS-Fixer

### Ubicación

Los fixers custom de PHP-CS-Fixer deben ubicarse en `src/PhpCsFixer/Rules/`.

### Estructura de un Fixer

Cada fixer debe:
- Extender `PhpCsFixer\Fixer\AbstractFixer` o implementar `PhpCsFixer\Fixer\FixerInterface`
- Implementar los métodos requeridos: `getName()`, `getPriority()`, `supports()`, `isCandidate()`, y `fix()`
- Seguir los estándares PSR-12
- Incluir documentación PHPDoc completa

### Ejemplo de Fixer Custom

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
            'Descripción de lo que hace este fixer.',
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
        // Implementación del fixer
    }
}
```

### Uso de Fixers Custom

#### Opción 1: Usar el Set Helper

```php
use NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet;

$config = (new Config())
    ->registerCustomFixers(CustomFixersSet::getFixers())
    ->setRules(array_merge([
        '@PSR12' => true,
        // ... otras reglas
    ], CustomFixersSet::getRules()));
```

#### Opción 2: Añadir manualmente en .php-cs-fixer.custom.php

```php
use NowoTech\PhpQualityTools\PhpCsFixer\Rules\ExampleCustomFixer;

return [
    'rules' => [
        'NowoTech/example_custom_fixer' => true,
    ],
];
```

#### Opción 3: Añadir directamente en .php-cs-fixer.php

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
        // ... otras reglas
    ]);
```

## Dependencias Requeridas

### Para Reglas Custom de Rector

Las reglas custom de Rector requieren la siguiente dependencia adicional:

- **symplify/rule-doc-generator-contracts**: Requerida para la documentación de las reglas (`RuleDefinition`, `CodeSample`)

**Instalación:**
```bash
composer require --dev symplify/rule-doc-generator-contracts
```

**Nota**: Si intentas usar las reglas custom sin esta dependencia, verás un mensaje informativo indicando qué paquete falta y cómo instalarlo.

### Para Fixers Custom de PHP-CS-Fixer

Los fixers custom de PHP-CS-Fixer no requieren dependencias adicionales, ya que usan las clases de `friendsofphp/php-cs-fixer` que ya está en las dependencias sugeridas.

## Reglas Custom Incluidas

### Rector

El paquete incluye las siguientes reglas custom de Rector:

1. **SplitLongGroupedImportsRector**: Formatea imports agrupados largos en formato multilínea cuando exceden 120 caracteres o tienen 3+ items.

2. **SplitLongConstructorParametersRector**: Divide listas de parámetros de constructor largas en múltiples líneas cuando exceden 120 caracteres.

3. **AddMissingReturnTypeRector**: Añade tipos de retorno faltantes a métodos públicos y protegidos basándose en el análisis del cuerpo del método.

4. **SplitLongMethodCallRector**: Identifica cadenas de llamadas a métodos largas (3+ llamadas encadenadas o >120 caracteres) y las marca para formato multilínea.

**Dependencia requerida**: `symplify/rule-doc-generator-contracts`

Estas reglas están automáticamente disponibles cuando usas `CustomRulesSet::getRules()`. Si falta la dependencia, se mostrará un mensaje informativo.

### PHP-CS-Fixer

El paquete incluye los siguientes fixers custom:

1. **MultilineGroupedImportsFixer**: Formatea imports agrupados largos en formato multilínea cuando exceden 120 caracteres o tienen 3+ items.

2. **MultilineArrayFixer**: Formatea arrays largos en formato multilínea cuando exceden 120 caracteres o tienen 3+ elementos.

3. **ConsistentDocblockFixer**: Asegura formato consistente en docblocks con alineación y espaciado adecuados.

Estos fixers están automáticamente disponibles cuando usas `CustomFixersSet::getFixers()` y `CustomFixersSet::getRules()`.

## Añadir Nuevas Reglas

Para añadir una nueva regla custom:

1. **Crear la clase de la regla** en el directorio apropiado:
   - Rector: `src/Rector/Rules/YourCustomRule.php`
   - PHP-CS-Fixer: `src/PhpCsFixer/Rules/YourCustomFixer.php`

2. **Actualizar el Set Helper** correspondiente:
   - Rector: Añadir la clase a `CustomRulesSet::getRules()` en `src/Rector/Set/CustomRulesSet.php`
   - PHP-CS-Fixer: Añadir la instancia a `CustomFixersSet::getFixers()` y la configuración a `CustomFixersSet::getRules()` en `src/PhpCsFixer/Set/CustomFixersSet.php`

3. **Añadir tests** para la nueva regla en `tests/`

4. **Actualizar la documentación** si es necesario

## Testing

Cada regla custom debe tener tests asociados:

- **Rector**: Tests en `tests/Rector/Rules/`
- **PHP-CS-Fixer**: Tests en `tests/PhpCsFixer/Rules/`

Ejecuta los tests con:

```bash
composer test
```

## Uso de las Reglas Incluidas

### Activar Reglas Custom de Rector

**Antes de usar las reglas**, asegúrate de tener instalada la dependencia requerida:

```bash
composer require --dev symplify/rule-doc-generator-contracts
```

Las reglas custom están disponibles automáticamente cuando usas el `CustomRulesSet`:

```php
// En .rector.php o .rector.custom.php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

return [
    'rules' => CustomRulesSet::getRules(), // Verifica dependencias automáticamente
    // ... otras configuraciones
];
```

**Validación automática**: `CustomRulesSet::getRules()` verifica automáticamente si las dependencias están instaladas. Si faltan, mostrará un mensaje informativo en STDERR (CLI) o como warning (web).

Si prefieres desactivar la verificación (por ejemplo, para usar las reglas condicionalmente):

```php
// Desactivar verificación de dependencias
$rules = CustomRulesSet::getRules(checkDependencies: false);
```

**Verificación manual de dependencias**:

```php
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;

// Verificar si todas las dependencias están instaladas
if (!CustomRulesSet::hasAllDependencies()) {
    $missing = CustomRulesSet::getMissingDependencies();
    echo "Faltan dependencias: " . implode(', ', $missing) . "\n";
    echo "Instálalas con: composer require --dev " . implode(' ', $missing) . "\n";
}

// Obtener lista de dependencias faltantes
$missingPackages = CustomRulesSet::getMissingDependencies();
```

O añadirlas manualmente:

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

**Nota importante**: `SplitLongConstructorParametersRector` identifica constructores que necesitan formato multilínea, pero el formato real debe aplicarse con PHP-CS-Fixer usando la regla `method_argument_space` con `ensure_fully_multiline`.

### Activar Fixers Custom de PHP-CS-Fixer

Los fixers custom están disponibles automáticamente cuando usas el `CustomFixersSet`:

```php
// En .php-cs-fixer.php o .php-cs-fixer.custom.php
use NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet;

$config = (new Config())
    ->registerCustomFixers(CustomFixersSet::getFixers())
    ->setRules(array_merge([
        '@PSR12' => true,
        'method_argument_space' => ['ensure_fully_multiline' => true], // Para SplitLongConstructorParametersRector
        // ... otras reglas
    ], CustomFixersSet::getRules()));
```

## Recursos

- [Documentación de Rector](https://getrector.com/documentation)
- [Documentación de PHP-CS-Fixer](https://cs.symfony.com/)
- [Guía de creación de reglas Rector](https://getrector.com/documentation/how-it-works)
- [Guía de creación de fixers PHP-CS-Fixer](https://cs.symfony.com/doc/custom_fixers.html)

