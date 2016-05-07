<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Log;

use Room11\Jeeves\Log\StdOut;
use Room11\Jeeves\Log\BaseLogger;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\Level;

class StdOutTest extends \PHPUnit_Framework_TestCase
{
    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceof(Logger::class, new StdOut(0));
    }

    public function testExtendsBaseClass()
    {
        $this->assertInstanceof(BaseLogger::class, new StdOut(0));
    }

    public function testLogWithoutMeetingTheLoglevel()
    {
        $this->assertNull((new StdOut(Level::ERROR))->log(Level::DEBUG, 'foo'));
    }

    public function testLogWithoutExtraData()
    {
        ob_start();

        (new StdOut(Level::DEBUG))->log(Level::DEBUG, 'foo');

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            ob_get_clean()
        );
    }

    public function testLogWithExtraDataWithoutExtraDataLevel()
    {
        ob_start();

        (new StdOut(Level::DEBUG))->log(Level::DEBUG, 'foo', 'bar');

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            ob_get_clean()
        );
    }

    public function testLogWithExtraData()
    {
        ob_start();

        (new StdOut(Level::DEBUG | Level::EXTRA_DATA))->log(Level::DEBUG, 'foo', 'bar');

        $logLines = explode("\n", ob_get_clean());

        $this->assertSame(3, count($logLines));

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            $logLines[0]
        );

        $this->assertSame('string(3) "bar"', $logLines[1]);

        $this->assertSame('', $logLines[2]);
    }
}