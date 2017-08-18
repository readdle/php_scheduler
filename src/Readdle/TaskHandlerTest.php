<?php

declare(ticks = 1);

namespace Readdle\Scheduler;

use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

class TaskHandlerTest extends TestCase
{
    use RedisMockTrait;
    
    protected function setUp()
    {
        $this->clearRedis();
        pcntl_signal(SIGINT, [$this, 'tearDown']);
        pcntl_signal(SIGTERM, [$this, 'tearDown']);
        pcntl_signal(SIGHUP, [$this, 'tearDown']);
    }
    
    public function tearDown()
    {
        $this->clearRedis();
    }
    
    public function dataProvider(): array
    {
        return [
            [
                [
                    new Command('whoami', random_int(2, 8)),
                    new Command('pwd', random_int(2, 8)),
                ],
            ],
            [
                [
                    new Command('uname', random_int(2, 8)),
                    new Command('ifconfig', random_int(2, 8)),
                ],
            ],
            [
                [
                    new Command('printf "Exception has been thrown\n see logs \n"', random_int(2, 8)),
                    new Command('printf "Shit happens!\n Exception has been thrown \n"', random_int(2, 8)),
                ],
            ],
            [
                [
                    $this->getTaskMock('TestScript1', [], random_int(2, 8), true),
                    $this->getTaskMock('TestScript2', ['message' => "all done"], random_int(2, 8)),
                ],
            ],
        ];
    }
    
    private function getTaskMock(
        string $name,
        array $returnValue,
        int $interval,
        bool $isBrokenTask = false
    ): TaskInterface {
        $task = $this->getMockBuilder(TaskInterface::class)->getMock();
        if ($isBrokenTask) {
            $task->method('run')->willReturnCallback(
                function () {
                    throw new \InvalidArgumentException("Test");
                }
            );
        } else {
            $task->method('run')->willReturn($returnValue);
        }
        
        $task->method('getName')->willReturn($name);
        $task->method('getInterval')->willReturn($interval);
        
        /**
         * @var TaskInterface $task
         */
        return $task;
    }
    
    /**
     * @dataProvider dataProvider
     *
     * @param Command[] $commands
     */
    public function testRun(array $commands)
    {
        $this->check($commands);
    }
    
    /**
     * @param Command[] $commands
     */
    protected function check(array $commands)
    {
        $redis = $this->getRedis();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $logCallback = function (string $message, array $context) {
            TestCase::assertEquals('scheduler', $message);
            TestCase::assertArrayHasKey('command', $context);
            TestCase::assertArrayHasKey('result', $context);
        };
        $logger->method('info')->willReturnCallback($logCallback);
        $logger->method('error')->willReturnCallback($logCallback);
        $logger->method('notice')->willReturnCallback(function (string $message, array $context) {
            TestCase::assertEquals('scheduler', $message);
            TestCase::assertArrayHasKey('message', $context);
            TestCase::assertArrayHasKey('signal', $context);
        });
        
        /**
         * @var LoggerInterface $logger
         */
        $scriptHandler = new TaskHandler($redis, $logger);
        foreach ($commands as $command) {
            $scriptHandler->addTask($command);
        }
        
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('could not fork');
        } elseif ($pid == 0) {
            $scriptHandler->run();
            exit;
        }
        sleep(random_int(10, 20));
        posix_kill($pid, SIGINT);
        sleep(1);
        while (pcntl_waitpid($pid, $status) < 1) {
            ;
        }
        $pidsCacheKey = "scheduler:pids";
        $resultCacheKey = "scheduler:result";
        foreach ($commands as $command) {
            $pid = $redis->hget($pidsCacheKey, $command->getName());
            if (is_numeric($pid)) {
                posix_kill($pid, SIGKILL);
            }
            $result = $redis->hget($resultCacheKey, $command->getName());
            if (!empty($result)) {
                try {
                    $taskResult = $command->run();
                } catch (\Throwable $exception) {
                    $taskResult = [
                        'error' => 'Exception',
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTrace(),
                    ];
                }
                TestCase::assertEquals(
                    $taskResult,
                    json_decode(
                        $result,
                        true
                    )
                );
            }
        }
    }
    
    protected function getRedisMock(): \PHPUnit_Framework_MockObject_MockObject
    {
        return $this->getMockBuilder(ClientInterface::class)->disableOriginalConstructor()->getMock();
    }
    
    protected function getShmopKey(): int
    {
        return 0xff3;
    }
    
    protected function getShmopSize(): int
    {
        return 1024;
    }
}
