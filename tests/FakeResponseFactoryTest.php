<?php


namespace Inertia\Tests;


use Inertia\FakeResponseFactory;
use PHPUnit\Framework\Constraint\ExceptionMessage;
use PHPUnit\Framework\ExpectationFailedException;

class FakeResponseFactoryTest extends TestCase
{

    /** @var FakeResponseFactory */
    private $fake;

    public function setUp() : void
    {
        parent::setUp();
        $this->fake = new FakeResponseFactory();
        $this->fake->share('this', 10);
        $this->fake->render('ComponentName', ['foo' => 'bar', 'shmeow' => ['dongle', 'banana']]);
    }

    /** @test */
    public function getAllProps()
    {
        $props = $this->fake->getAllProps();

        $this->assertEquals(
            ['foo' => 'bar', 'shmeow' => ['dongle', 'banana'], 'this' => 10],
            $props
        );
    }

    /** @test */
    public function getProp()
    {
        $prop = $this->fake->getProp('foo');

        $this->assertEquals('bar', $prop);
    }

    /** @test */
    public function assertComponentIs()
    {
        try {
            $this->fake->assertComponentIs('ShmeowDongle');
            $this->fail();
        } catch(ExpectationFailedException $exception) {
            $this->assertThat($exception, new ExceptionMessage('Failed asserting that ShmeowDongle was rendered. When ComponentName was rendered.'));
        }

        $this->fake->assertComponentIs('ComponentName');
    }

    /** @test */
    public function assertHasProps()
    {
        try {
            $this->fake->assertHasProps(['dongle', 'inertia']);
            $this->fail();
        } catch(ExpectationFailedException $exception) {
            $this->assertThat($exception, new ExceptionMessage('Failed asserting that prop dongle exists'));
        }

        $this->fake->assertHasProps(['foo', 'shmeow']);
    }

    /** @test */
    public function assertHasProp()
    {
        try {
            $this->fake->assertHasProp('dongle');
            $this->fail();
        } catch(ExpectationFailedException $exception) {
            $this->assertThat($exception, new ExceptionMessage('Failed asserting that prop dongle exists'));
        }

        $this->fake->assertHasProp('foo');
    }

    /** @test */
    public function assertPropsEqual()
    {
        try {
            $this->fake->assertPropsEqual(['inertia' => 'Rocks']);
            $this->fail();
        } catch(ExpectationFailedException $exception) {
            $this->assertThat($exception, new ExceptionMessage('Failed asserting that two arrays are equal.'));
        }

        $this->fake->assertPropsEqual($this->fake->getAllProps());
    }

    /** @test */
    public function assertPropEquals()
    {
        // will let user know if prop does not exist
        try {
            $this->fake->assertPropEquals('inertia', 'Rocks');
            $this->fail();
        } catch(ExpectationFailedException $exception) {
            $this->assertThat($exception, new ExceptionMessage('Failed asserting that prop inertia exists'));
        }

        // then asserts the value
        try {
            $this->fake->assertPropEquals('foo', 'socks');
            $this->fail();
        } catch(ExpectationFailedException $exception) {
            $this->assertThat($exception, new ExceptionMessage('Failed asserting that two strings are equal.'));
        }

        $this->fake->assertPropEquals('foo', 'bar');
    }

    /** @test */
    public function propsAreTransformed()
    {
        $this->fake->render('Component', [
            'func' => function() {
                return 'something';
            }
        ]);

        $this->assertEquals('something', $this->fake->getProp('func'));
    }

    /** @test */
    public function expectedValuesAreTransformed()
    {
        $this->fake = new FakeResponseFactory();

        $func = function() {
            return 'something';
        };

        $this->fake->render('Component', ['func' => $func]);

        $this->fake->assertPropsEqual(['func' => $func]);

        $this->fake->assertPropEquals('func', $func);
    }

}