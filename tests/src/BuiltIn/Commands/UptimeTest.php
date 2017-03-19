<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Uptime;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\System\BuiltInCommandInfo;

class UptimeTest extends AbstractCommandTest
{
    const VALID_UPTIME_EXP = "/\d \bsecond(s)?\b|\d \bminute(s)?\b|\d \bday(s)?\b|\d \bhour(s)?\b/";

    private $builtIn;
    private $client;
    private $command;

    public function setUp()
    {
        parent::setUp();

        if (!defined('Room11\\Jeeves\\PROCESS_START_TIME')) {
            define('Room11\\Jeeves\\PROCESS_START_TIME', 1489013652);
        }

        $this->client = $this->createMock(ChatClient::class);
        $this->command = $this->createMock(Command::class);
        $this->builtIn = new Uptime($this->client);
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    public function testUptimeCommand()
    {
        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->matchesRegularExpression(self::VALID_UPTIME_EXP)
            )
            ->will($this->returnValue(new Success(true)))
        ;

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }
}