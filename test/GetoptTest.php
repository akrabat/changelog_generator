<?php
// This class is extracted from Laminas\Console 2.8.0.

/**
 * @see       https://github.com/laminas/laminas-console for the canonical source repository
 * @copyright https://github.com/laminas/laminas-console/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-console/blob/master/LICENSE.md New BSD License
 */

namespace AppTest;

use App\Getopt;
use PHPUnit\Framework\TestCase;

class GetoptTest extends TestCase
{
    public function setUp(): void
    {
        if (ini_get('register_argc_argv') == false) {
            $this->markTestSkipped(
                "Cannot Test Getopt without 'register_argc_argv' ini option true."
            );
        }
        $_SERVER['argv'] = ['getopttest'];
    }

    public function testGetoptShortOptionsGnuMode(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals(true, $opts->a);
        $this->assertNull(@$opts->b);
        $this->assertEquals($opts->p, 'p_arg');
    }

    public function testGetoptLongOptionsLaminasMode(): void
    {
        $opts = new Getopt(
            [
                'apple|a' => 'Apple option',
                'banana|b' => 'Banana option',
                'pear|p=s' => 'Pear option'
            ],
            ['-a', '-p', 'p_arg']
        );
        $this->assertTrue($opts->apple);
        $this->assertNull(@$opts->banana);
        $this->assertEquals($opts->pear, 'p_arg');
    }

    public function testGetoptLaminasModeEqualsParam(): void
    {
        $opts = new Getopt(
            [
                'apple|a' => 'Apple option',
                'banana|b' => 'Banana option',
                'pear|p=s' => 'Pear option'
            ],
            ['--pear=pear.phpunit.de']
        );
        $this->assertEquals($opts->pear, 'pear.phpunit.de');
    }

    public function testGetoptToString(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals($opts->__toString(), 'a=true p=p_arg');
    }

    public function testGetoptDumpString(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals($opts->toString(), 'a=true p=p_arg');
    }

    public function testGetoptDumpArray(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals(implode(',', $opts->toArray()), 'a,p,p_arg');
    }

    public function testGetoptDumpJson(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals(
            $opts->toJson(),
            '{
    "options": [
        {
            "option": {
                "flag": "a",
                "parameter": true
            }
        },
        {
            "option": {
                "flag": "p",
                "parameter": "p_arg"
            }
        }
    ]
}'
        );
    }

    public function testGetoptDumpXml(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals(
            $opts->toXml(),
            "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<options><option flag=\"a\"/>"
            . "<option flag=\"p\" parameter=\"p_arg\"/></options>\n"
        );
    }

    public function testGetoptExceptionForMissingFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Blank flag not allowed in rule');
        $opts = new Getopt(['|a' => 'Apple option']);
    }

    public function testGetoptExceptionForKeyWithDuplicateFlagsViaOrOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('defined more than once');
        $opts = new Getopt(
            ['apple|apple' => 'apple-option']
        );
    }

    public function testGetoptExceptionForKeysThatDuplicateFlags(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('defined more than once');
        $opts = new Getopt(
            ['a' => 'Apple option', 'apple|a' => 'Apple option']
        );
    }

    public function testGetoptAddRules(): void
    {
        $opts = new Getopt(
            [
                'apple|a' => 'Apple option',
                'banana|b' => 'Banana option'
            ],
            ['--pear', 'pear_param']
        );
        try {
            $opts->parse();
            $this->fail('Expected to catch \RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertEquals($e->getMessage(), 'Option "pear" is not recognized.');
        }
        $opts->addRules(['pear|p=s' => 'Pear option']);
        $this->assertEquals($opts->pear, 'pear_param');
    }

    public function testGetoptExceptionMissingParameter(): void
    {
        $opts = new Getopt(
            [
                'apple|a=s' => 'Apple with required parameter',
                'banana|b' => 'Banana'
            ],
            ['--apple']
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires a parameter');
        $opts->parse();
    }

    public function testGetoptOptionalParameter(): void
    {
        $opts = new Getopt(
            [
                'apple|a-s' => 'Apple with optional parameter',
                'banana|b' => 'Banana'
            ],
            ['--apple', '--banana']
        );
        $this->assertTrue($opts->apple);
        $this->assertTrue($opts->banana);
    }

    public function testGetoptIgnoreCaseGnuMode(): void
    {
        $opts = new Getopt(
            'aB',
            ['-A', '-b'],
            [Getopt::CONFIG_IGNORECASE => true]
        );
        $this->assertEquals(true, $opts->a);
        $this->assertEquals(true, $opts->B);
    }

    public function testGetoptIgnoreCaseLaminasMode(): void
    {
        $opts = new Getopt(
            [
                'apple|a' => 'Apple-option',
                'Banana|B' => 'Banana-option'
            ],
            ['--Apple', '--bAnaNa'],
            [Getopt::CONFIG_IGNORECASE => true]
        );
        $this->assertEquals(true, $opts->apple);
        $this->assertEquals(true, $opts->BANANA);
    }

    public function testGetoptIsSet(): void
    {
        $opts = new Getopt('ab', ['-a']);
        $this->assertTrue(isset($opts->a));
        $this->assertFalse(isset($opts->b));
    }

    public function testGetoptIsSetAlias(): void
    {
        $opts = new Getopt('ab', ['-a']);
        $opts->setAliases(['a' => 'apple', 'b' => 'banana']);
        $this->assertTrue(isset($opts->apple));
        $this->assertFalse(isset($opts->banana));
    }

    public function testGetoptIsSetInvalid(): void
    {
        $opts = new Getopt('ab', ['-a']);
        $opts->setAliases(['a' => 'apple', 'b' => 'banana']);
        $this->assertFalse(isset($opts->cumquat));
    }

    public function testGetoptSet(): void
    {
        $opts = new Getopt('ab', ['-a']);
        $this->assertFalse(isset($opts->b));
        $opts->b = true;
        $this->assertTrue(isset($opts->b));
    }

    public function testGetoptSetBeforeParse(): void
    {
        $opts = new Getopt('ab', ['-a']);
        $opts->b = true;
        $this->assertTrue(isset($opts->b));
    }

    public function testGetoptUnSet(): void
    {
        $opts = new Getopt('ab', ['-a']);
        $this->assertTrue(isset($opts->a));
        unset($opts->a);
        $this->assertFalse(isset($opts->a));
    }

    public function testGetoptUnSetBeforeParse(): void
    {
        $opts = new Getopt('ab', ['-a']);
        unset($opts->a);
        $this->assertFalse(isset($opts->a));
    }

    /**
     * @group Laminas-5948
     */
    public function testGetoptAddSetNonArrayArguments(): void
    {
        $opts = new Getopt('abp:', ['-foo']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('should be an array');
        $opts->setArguments('-a');
    }

    public function testGetoptAddArguments(): void
    {
        $opts = new Getopt('abp:', ['-a']);
        $this->assertNull(@$opts->p);
        $opts->addArguments(['-p', 'p_arg']);
        $this->assertEquals($opts->p, 'p_arg');
    }

    public function testGetoptRemainingArgs(): void
    {
        $opts = new Getopt('abp:', ['-a', '--', 'file1', 'file2']);
        $this->assertEquals(implode(',', $opts->getRemainingArgs()), 'file1,file2');
        $opts = new Getopt('abp:', ['-a', 'file1', 'file2']);
        $this->assertEquals(implode(',', $opts->getRemainingArgs()), 'file1,file2');
    }

    public function testGetoptDashDashFalse(): void
    {
        $opts = new Getopt(
            'abp:',
            ['-a', '--', '--fakeflag'],
            [Getopt::CONFIG_DASHDASH => false]
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not recognized');
        $opts->parse();
    }

    public function testGetoptGetOptions(): void
    {
        $opts = new Getopt('abp:', ['-a', '-p', 'p_arg']);
        $this->assertEquals(implode(',', $opts->getOptions()), 'a,p');
    }

    public function testGetoptGetUsageMessage(): void
    {
        $opts = new Getopt('abp:', ['-x']);
        $message = preg_replace(
            '/Usage: .* \[ options \]/',
            'Usage: <progname> [ options ]',
            $opts->getUsageMessage()
        );
        $message = preg_replace('/ /', '_', $message);
        $this->assertEquals(
            $message,
            "Usage:_<progname>_[_options_]\n-a___________________\n-b___________________\n-p_<string>__________\n"
        );
    }
    public function testGetoptSetAliases(): void
    {
        $opts = new Getopt('abp:', ['--apple']);
        $opts->setAliases(['a' => 'apple']);
        $this->assertTrue($opts->a);
    }

    public function testGetoptSetAliasesIgnoreCase(): void
    {
        $opts = new Getopt(
            'abp:',
            ['--apple'],
            [Getopt::CONFIG_IGNORECASE => true]
        );
        $opts->setAliases(['a' => 'APPLE']);
        $this->assertTrue($opts->apple);
    }

    public function testGetoptSetAliasesWithNamingConflict(): void
    {
        $opts = new Getopt('abp:', ['--apple']);
        $opts->setAliases(['a' => 'apple']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('defined more than once');
        $opts->setAliases(['b' => 'apple']);
    }

    public function testGetoptSetAliasesInvalid(): void
    {
        $opts = new Getopt('abp:', ['--apple']);
        $opts->setAliases(['c' => 'cumquat']);
        $opts->setArguments(['-c']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not recognized');
        $opts->parse();
    }

    public function testGetoptSetHelp(): void
    {
        $opts = new Getopt('abp:', ['-a']);
        $opts->setHelp([
                           'a' => 'apple',
                           'b' => 'banana',
                           'p' => 'pear']);
        $message = preg_replace(
            '/Usage: .* \[ options \]/',
            'Usage: <progname> [ options ]',
            $opts->getUsageMessage()
        );
        $message = preg_replace('/ /', '_', $message);
        $this->assertEquals(
            $message,
            "Usage:_<progname>_[_options_]\n-a___________________apple\n-b___________________banana\n"
            . "-p_<string>__________pear\n"
        );
    }

    public function testGetoptSetHelpInvalid(): void
    {
        $opts = new Getopt('abp:', ['-a']);
        $opts->setHelp([
                           'a' => 'apple',
                           'b' => 'banana',
                           'p' => 'pear',
                           'c' => 'cumquat']);
        $message = preg_replace(
            '/Usage: .* \[ options \]/',
            'Usage: <progname> [ options ]',
            $opts->getUsageMessage()
        );
        $message = preg_replace('/ /', '_', $message);
        $this->assertEquals(
            $message,
            "Usage:_<progname>_[_options_]\n-a___________________apple\n-b___________________banana\n"
            . "-p_<string>__________pear\n"
        );
    }

    public function testGetoptCheckParameterType(): void
    {
        $opts = new Getopt([
                               'apple|a=i' => 'apple with integer',
                               'banana|b=w' => 'banana with word',
                               'pear|p=s' => 'pear with string',
                               'orange|o-i' => 'orange with optional integer',
                               'lemon|l-w' => 'lemon with optional word',
                               'kumquat|k-s' => 'kumquat with optional string']);

        $opts->setArguments(['-a', 327]);
        $opts->parse();
        $this->assertEquals(327, $opts->a);

        $opts->setArguments(['-a', 'noninteger']);
        try {
            $opts->parse();
            $this->fail('Expected to catch \RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertEquals(
                $e->getMessage(),
                'Option "apple" requires an integer parameter, but was given "noninteger".'
            );
        }

        $opts->setArguments(['-b', 'word']);
        $this->assertEquals('word', $opts->b);

        $opts->setArguments(['-b', 'two words']);
        try {
            $opts->parse();
            $this->fail('Expected to catch \RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertEquals(
                $e->getMessage(),
                'Option "banana" requires a single-word parameter, but was given "two words".'
            );
        }

        $opts->setArguments(['-p', 'string']);
        $this->assertEquals('string', $opts->p);

        $opts->setArguments(['-o', 327]);
        $this->assertEquals(327, $opts->o);

        $opts->setArguments(['-o']);
        $this->assertTrue($opts->o);

        $opts->setArguments(['-l', 'word']);
        $this->assertEquals('word', $opts->l);

        $opts->setArguments(['-k', 'string']);
        $this->assertEquals('string', $opts->k);
    }

    /**
     * @group Laminas-2295
     */
    public function testRegisterArgcArgvOffThrowsException(): void
    {
        $argv = $_SERVER['argv'];
        unset($_SERVER['argv']);

        try {
            $opts = new GetOpt('abp:');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('$_SERVER["argv"]', $e->getMessage());
        }

        $_SERVER['argv'] = $argv;
    }

    /**
     * Test to ensure that dashed long names will parse correctly
     *
     * @group Laminas-4763
     */
    public function testDashWithinLongOptionGetsParsed(): void
    {
        $opts = new Getopt(
            [ // rules
                'man-bear|m-s' => 'ManBear with dash',
                'man-bear-pig|b=s' => 'ManBearPid with dash',
            ],
            [ // arguments
                '--man-bear-pig=mbp',
                '--man-bear',
                'foobar'
            ]
        );

        $opts->parse();
        $this->assertEquals('foobar', $opts->getOption('man-bear'));
        $this->assertEquals('mbp', $opts->getOption('man-bear-pig'));
    }

    /**
     * @group Laminas-2064
     */
    public function testAddRulesDoesNotThrowWarnings(): void
    {
        // Fails if warning is thrown: Should not happen!
        $this->expectNotToPerformAssertions();
        $opts = new Getopt('abp:');
        $opts->addRules(
            [
                'verbose|v' => 'Print verbose output'
            ]
        );
    }

    /**
     * @group Laminas-5345
     */
    public function testUsingDashWithoutOptionNameAsLastArgumentIsRecognizedAsRemainingArgument(): void
    {
        $opts = new Getopt('abp:', ['-']);
        $opts->parse();

        $this->assertCount(1, $opts->getRemainingArgs());
        $this->assertEquals(['-'], $opts->getRemainingArgs());
    }

    /**
     * @group Laminas-5345
     */
    public function testUsingDashWithoutOptionNotAsLastArgumentThrowsException(): void
    {
        $opts = new Getopt('abp:', ['-', 'file1']);

        $this->expectException(\RuntimeException::class);
        $opts->parse();
    }

    /**
     * @group Laminas-5624
     */
    public function testEqualsCharacterInLongOptionsValue(): void
    {
        $fooValue = 'some text containing an = sign which breaks';

        $opts = new Getopt(
            ['foo=s' => 'Option One (string)'],
            ['--foo=' . $fooValue]
        );
        $this->assertEquals($fooValue, $opts->foo);
    }

    public function testGetoptIgnoreCumulativeParamsByDefault(): void
    {
        $opts = new Getopt(
            ['colors=s' => 'Colors-option'],
            ['--colors=red', '--colors=green', '--colors=blue']
        );

        $this->assertIsString($opts->colors);
        $this->assertEquals('blue', $opts->colors, 'Should be equal to last variable');
    }

    public function testGetoptWithCumulativeParamsOptionHandleArrayValues(): void
    {
        $opts = new Getopt(
            ['colors=s' => 'Colors-option'],
            ['--colors=red', '--colors=green', '--colors=blue'],
            [Getopt::CONFIG_CUMULATIVE_PARAMETERS => true]
        );

        $this->assertIsArray($opts->colors, 'Colors value should be an array');
        $this->assertEquals('red,green,blue', implode(',', $opts->colors));
    }

    public function testGetoptIgnoreCumulativeFlagsByDefault(): void
    {
        $opts = new Getopt('v', ['-v', '-v', '-v']);

        $this->assertEquals(true, $opts->v);
    }

    public function testGetoptWithCumulativeFlagsOptionHandleCountOfEqualFlags(): void
    {
        $opts = new Getopt(
            'v',
            ['-v', '-v', '-v'],
            [Getopt::CONFIG_CUMULATIVE_FLAGS => true]
        );

        $this->assertEquals(3, $opts->v);
    }

    public function testGetoptIgnoreParamsWithMultipleValuesByDefault(): void
    {
        $opts = new Getopt(
            ['colors=s' => 'Colors-option'],
            ['--colors=red,green,blue']
        );

        $this->assertEquals('red,green,blue', $opts->colors);
    }

    public function testGetoptWithNotEmptyParameterSeparatorSplitMultipleValues(): void
    {
        $opts = new Getopt(
            ['colors=s' => 'Colors-option'],
            ['--colors=red,green,blue'],
            [Getopt::CONFIG_PARAMETER_SEPARATOR => ',']
        );

        $this->assertEquals('red:green:blue', implode(':', $opts->colors));
    }

    public function testGetoptWithFreeformFlagOptionRecognizeAllFlags(): void
    {
        $opts = new Getopt(
            ['colors' => 'Colors-option'],
            ['--freeform'],
            [Getopt::CONFIG_FREEFORM_FLAGS => true]
        );

        $this->assertEquals(true, $opts->freeform);
    }

    public function testGetoptWithFreeformFlagOptionRecognizeFlagsWithValue(): void
    {
        $opts = new Getopt(
            ['colors' => 'Colors-option'],
            ['color', '--freeform', 'test', 'laminas'],
            [Getopt::CONFIG_FREEFORM_FLAGS => true]
        );

        $this->assertEquals('test', $opts->freeform);
    }

    public function testGetoptWithFreeformFlagOptionShowHelpAfterParseDoesNotThrowNotices(): void
    {
        // this formerly failed, because the index 'alias' is not set for freeform flags.
        $this->expectNotToPerformAssertions();
        $opts = new Getopt(
            ['colors' => 'Colors-option'],
            ['color', '--freeform', 'test', 'laminas'],
            [Getopt::CONFIG_FREEFORM_FLAGS => true]
        );
        $opts->parse();

        $opts->getUsageMessage();
    }

    public function testGetoptWithFreeformFlagOptionShowHelpAfterParseDoesNotShowFreeformFlags(): void
    {
        $opts = new Getopt(
            ['colors' => 'Colors-option'],
            ['color', '--freeform', 'test', 'laminas'],
            [Getopt::CONFIG_FREEFORM_FLAGS => true]
        );
        $opts->parse();

        $message = preg_replace(
            '/Usage: .* \[ options \]/',
            'Usage: <progname> [ options ]',
            $opts->getUsageMessage()
        );
        $message = preg_replace('/ /', '_', $message);
        $this->assertEquals($message, "Usage:_<progname>_[_options_]\n--colors_____________Colors-option\n");
    }

    public function testGetoptRaiseExceptionForNumericOptionsByDefault(): void
    {
        $opts = new Getopt(
            ['colors=s' => 'Colors-option'],
            ['red', 'green', '-3']
        );

        $this->expectException(\RuntimeException::class);
        $opts->parse();
    }

    public function testGetoptCanRecognizeNumericOprions(): void
    {
        $opts = new Getopt(
            ['lines=#' => 'Lines-option'],
            ['other', 'arguments', '-5'],
            [Getopt::CONFIG_NUMERIC_FLAGS => true]
        );

        $this->assertEquals(5, $opts->lines);
    }

    public function testGetoptRaiseExceptionForNumericOptionsIfAneHandlerIsSpecified(): void
    {
        $opts = new Getopt(
            ['lines=s' => 'Lines-option'],
            ['other', 'arguments', '-5'],
            [Getopt::CONFIG_NUMERIC_FLAGS => true]
        );

        $this->expectException(\RuntimeException::class);
        $opts->parse();
    }

    public function testOptionCallback(): void
    {
        $opts = new Getopt('a', ['-a']);
        $testVal = null;
        $opts->setOptionCallback('a', function ($val, $opts) use (&$testVal) {
            $testVal = $val;
        });
        $opts->parse();

        $this->assertTrue($testVal);
    }

    public function testOptionCallbackAddedByAlias(): void
    {
        $opts = new Getopt([
                               'a|apples|apple=s' => "APPLES",
                               'b|bears|bear=s' => "BEARS"
                           ], [
                               '--apples=Gala',
                               '--bears=Grizzly'
                           ]);

        $appleCallbackCalled = null;
        $bearCallbackCalled = null;

        $opts->setOptionCallback('a', function ($val) use (&$appleCallbackCalled) {
            $appleCallbackCalled = $val;
        });

        $opts->setOptionCallback('bear', function ($val) use (&$bearCallbackCalled) {
            $bearCallbackCalled = $val;
        });

        $opts->parse();

        $this->assertSame('Gala', $appleCallbackCalled);
        $this->assertSame('Grizzly', $bearCallbackCalled);
    }

    public function testOptionCallbackNotCalled(): void
    {
        $opts = new Getopt([
                               'a|apples|apple' => "APPLES",
                               'b|bears|bear' => "BEARS"
                           ], [
                               '--apples=Gala'
                           ]);

        $bearCallbackCalled = null;

        $opts->setOptionCallback('bear', function ($val) use (&$bearCallbackCalled) {
            $bearCallbackCalled = $val;
        });

        $opts->parse();

        $this->assertNull($bearCallbackCalled);
    }

    public function testOptionCallbackReturnsFallsAndThrowException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The option x is invalid. See usage.');
        $opts = new Getopt('x', ['-x']);
        $opts->setOptionCallback('x', function () {
            return false;
        });
        $opts->parse();
    }
}
