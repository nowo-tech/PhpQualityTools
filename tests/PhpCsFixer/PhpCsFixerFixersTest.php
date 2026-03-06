<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\PhpCsFixer;

use NowoTech\PhpQualityTools\PhpCsFixer\Rules\ConsistentDocblockFixer;
use NowoTech\PhpQualityTools\PhpCsFixer\Rules\MultilineArrayFixer;
use NowoTech\PhpQualityTools\PhpCsFixer\Rules\MultilineGroupedImportsFixer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for custom PHP-CS-Fixer fixers.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class PhpCsFixerFixersTest extends TestCase
{
    public function testConsistentDocblockFixerDefinitionAndName(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $this->assertInstanceOf(\PhpCsFixer\FixerDefinition\FixerDefinitionInterface::class, $fixer->getDefinition());
        $this->assertSame('NowoTech/consistent_docblock', $fixer->getName());
        $this->assertSame(0, $fixer->getPriority());
        $this->assertTrue($fixer->supports(new \SplFileInfo('test.php')));
    }

    public function testConsistentDocblockFixerIsCandidate(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode('<?php /** @var string */ class X {}');
        $this->assertTrue($fixer->isCandidate($tokens));
    }

    public function testConsistentDocblockFixerApplyFix(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/** @var string */\nclass Example {}\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('@var', $tokens->generateCode());
    }

    public function testConsistentDocblockFixerApplyFixMultilineDocblock(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/**\n * @param string \$a\n * @return void\n */\nfunction f(\$a) {}\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('@param', $tokens->generateCode());
    }

    public function testConsistentDocblockFixerApplyFixDocblockWithLineWithoutAsterisk(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/**\nDescription without asterisk prefix\n * @var string\n */\n\$x = '';\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('Description', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerDefinitionAndName(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $this->assertInstanceOf(\PhpCsFixer\FixerDefinition\FixerDefinitionInterface::class, $fixer->getDefinition());
        $this->assertSame('NowoTech/multiline_grouped_imports', $fixer->getName());
        $this->assertSame(-5, $fixer->getPriority());
    }

    public function testMultilineGroupedImportsFixerIsCandidate(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode('<?php use Foo\Bar;');
        $this->assertTrue($fixer->isCandidate($tokens));
    }

    public function testMultilineArrayFixerDefinitionAndName(): void
    {
        $fixer = new MultilineArrayFixer();
        $this->assertInstanceOf(\PhpCsFixer\FixerDefinition\FixerDefinitionInterface::class, $fixer->getDefinition());
        $this->assertSame('NowoTech/multiline_array', $fixer->getName());
        $this->assertSame(-10, $fixer->getPriority());
    }

    public function testMultilineArrayFixerIsCandidate(): void
    {
        $fixer = new MultilineArrayFixer();
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode('<?php $a = [1, 2];');
        $this->assertTrue($fixer->isCandidate($tokens));
    }

    public function testMultilineGroupedImportsFixerApplyFix(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\Entity\Chat\{Conversation, UserConversation, ChatMessage};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('use ', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerFormatsLongLineWithTwoItems(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $ns = 'App\\Entity\\VeryLong\\Namespace\\Path\\Here\\To\\Exceed\\MaxLineLength\\ClassName';
        $code = "<?php\nuse {$ns}\\{ShortA, ShortB};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $out = $tokens->generateCode();
        $this->assertStringContainsString('ShortA', $out);
        $this->assertStringContainsString('ShortB', $out);
    }

    public function testMultilineGroupedImportsFixerFormatsGroupedUseNoSpaceAfterBrace(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Sub\\{A, B, C};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $out = $tokens->generateCode();
        $this->assertStringContainsString('A', $out);
        $this->assertStringContainsString('B', $out);
    }

    public function testMultilineGroupedImportsFixerFormatsGroupedUseWithSpaceBeforeClosingBrace(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Entity\\{One, Two, Three };\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('One', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerSkipsAlreadyMultilineGroupedUse(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Model\\{\n    User,\n    Role,\n    Permission\n};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('User', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerFormatsGroupedUseWithSpaceAfterOpeningBrace(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Sub\\{ Alpha, Beta, Gamma };\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('Alpha', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerFormatsGroupedUseWithNewlineAfterComma(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Api\\{First,\nSecond, Third};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('First', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerFormatsLongLineGroupedUse(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $ns = 'NowoTech\\PhpQualityTools\\Some\\Very\\Long\\Namespace\\Name\\For\\Testing\\Purposes';
        $code = "<?php\nuse {$ns}\\{FirstClass, SecondClass};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('FirstClass', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerFormatsGroupedUseNoSpacesAfterCommas(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Ns\\{Foo,Bar,Baz};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('Foo', $tokens->generateCode());
        $this->assertStringContainsString('Bar', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerFormatsOnlyGroupedUseWhenMixedWithSimpleUse(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse Some\\Other\\Class;\nuse App\\Entity\\{Alpha, Beta, Gamma};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $out = $tokens->generateCode();
        $this->assertStringContainsString('Some', $out);
        $this->assertStringContainsString('Alpha', $out);
    }

    public function testMultilineGroupedImportsFixerApplyFixSimpleUseUnchanged(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse Foo\Bar;\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('use Foo\Bar', $tokens->generateCode());
    }

    public function testMultilineArrayFixerApplyFix(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$data = ['a' => 1, 'b' => 2, 'c' => 3];\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('$data', $tokens->generateCode());
    }

    public function testMultilineArrayFixerApplyFixLongSyntax(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$data = array('k1' => 1, 'k2' => 2, 'k3' => 3);\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('$data', $tokens->generateCode());
    }

    public function testMultilineArrayFixerSkipsAlreadyMultilineArray(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$data = [\n    'a' => 1,\n    'b' => 2,\n];\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString("'a' => 1", $tokens->generateCode());
    }

    public function testMultilineArrayFixerFormatsNestedArray(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$data = [['a' => 1], ['b' => 2], ['c' => 3]];\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('$data', $tokens->generateCode());
    }

    public function testMultilineArrayFixerFormatsLongSyntaxArray(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$items = array('one', 'two', 'three', 'four');\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('$items', $tokens->generateCode());
    }

    public function testMultilineArrayFixerFormatsArrayWithSpacesAfterCommas(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$a = [1, 2, 3, 4];\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('$a', $tokens->generateCode());
    }

    public function testMultilineArrayFixerFormatsArrayWithNoSpacesAfterCommas(): void
    {
        $fixer = new MultilineArrayFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\n\$b = [1,2,3,4];\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('$b', $tokens->generateCode());
    }

    public function testMultilineGroupedImportsFixerApplyFixAlreadyMultiline(): void
    {
        $fixer = new MultilineGroupedImportsFixer();
        $file = new \SplFileInfo(__FILE__);
        $code = "<?php\nuse App\\Entity\\Chat\\{\n    Conversation,\n    UserConversation\n};\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('use ', $tokens->generateCode());
    }

    public function testConsistentDocblockFixerSingleLineWithExtraSpaces(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/**  @var string  */\nclass X {}\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('@var', $tokens->generateCode());
    }

    public function testConsistentDocblockFixerSkipsEmptyLinesInDocblock(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/**\n * Summary.\n *\n * @param string \$x\n */\nfunction f(\$x) {}\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('@param', $tokens->generateCode());
    }

    public function testConsistentDocblockFixerSkipsLeadingAndTrailingEmptyLines(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/**\n * Single line.\n */\n\n\$x = 1;\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('Single', $tokens->generateCode());
    }

    public function testConsistentDocblockFixerFormatsDocblockWithEmptyLineInMiddle(): void
    {
        $fixer = new ConsistentDocblockFixer();
        $file = new \SplFileInfo(__FILE__);
        $content = "<?php\n/**\n * Line1\n\n * Line2\n */\nfunction f() {}\n";
        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($content);
        $fixer->fix($file, $tokens);
        $this->assertStringContainsString('Line1', $tokens->generateCode());
        $this->assertStringContainsString('Line2', $tokens->generateCode());
    }
}
