<?php

declare(ticks = 1);

namespace Readdle\Scheduler;

use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

class TaskHandler implements TaskHandlerInterface
{
    /**
     * @var Command[]|TaskInterface[]
     */
    private $tasks = [];
    private $redis;
    private $logger;
    private $cacheKey = "scheduler:%s";
    
    public function __construct(
        ClientInterface $redis,
        LoggerInterface $logger
    ) {
        $this->redis = $redis;
        $this->logger = $logger;
    }
    
    public function addTask(TaskInterface $task)
    {
        $this->tasks[] = $task;
    }
    
    public function run(): bool
    {
        if (count($this->tasks) < 1) {
            return false;
        }
        pcntl_signal(SIGINT, [$this, 'terminate']);
        pcntl_signal(SIGTERM, [$this, 'terminate']);
        pcntl_signal(SIGHUP, [$this, 'terminate']);
        
        $timeCacheKey = $this->createCacheKey('times');
        $pidsCacheKey = $this->createCacheKey('pids');
        $resultCacheKey = $this->createCacheKey('result');
        
        while (true) {
            $workerSleepTime = 0;
            foreach ($this->tasks as $commandDetails) {
                $cacheTaskField = $this->createCacheTaskKey($commandDetails);
                if (!$this->canRunTaskAgain($pidsCacheKey, $cacheTaskField)) {
                    if (!$this->waitForResult($pidsCacheKey, $resultCacheKey, $cacheTaskField)) {
                        continue;
                    }
                }
                if ($this->redis->hexists($timeCacheKey, $cacheTaskField)) {
                    $taskNextRunTime = (int)$this->redis->hget($timeCacheKey, $cacheTaskField);
                } else {
                    $taskNextRunTime = time();
                }
                if ($taskNextRunTime > time()) {
                    continue;
                }
                
                $pid = pcntl_fork();
                if ($pid == -1) {
                    die('could not fork');
                } elseif ($pid == 0) {
                    try {
                        $taskResult = $commandDetails->run();
                    } catch (\Throwable $exception) {
                        $taskResult = [
                            'error' => 'Exception',
                            'message' => $exception->getMessage(),
                            'file' => $exception->getFile(),
                            'line' => $exception->getLine(),
                            'trace' => $exception->getTrace(),
                        ];
                    }
                    $this->redis->hset($resultCacheKey, $cacheTaskField, json_encode($taskResult));
                    exit;
                }
                $taskNextRunTime = $this->getCronLikeTimeSinceNow($commandDetails->getInterval());
                $this->redis->hset($timeCacheKey, $cacheTaskField, $taskNextRunTime);
                $this->redis->hset($pidsCacheKey, $cacheTaskField, $pid);
                $workerSleepTime = $this->getSleepTime($workerSleepTime, $taskNextRunTime);
            }
            
            if ($workerSleepTime > time()) {
                time_sleep_until($workerSleepTime + 1);
            }
        }
    }
    
    public function terminate(int $signal)
    {
        printf("\nClearing redis cache, wait while will be done\n");
        $pidsCacheKey = $this->createCacheKey('pids');
        $resultCacheKey = $this->createCacheKey('result');
        $fields = $this->redis->hkeys($pidsCacheKey);
        while (count($fields) > 0) {
            foreach ($fields as $key => $field) {
                if ($this->waitForResult($pidsCacheKey, $resultCacheKey, $field)) {
                    printf("\n'%s' task finished\n", $field);
                    unset($fields[$key]);
                }
            }
        }
        printf("\nTerminating!\n");
        $this->logger->notice('scheduler', [
            'message' => 'Process was terminated',
            'signal' => $signal,
        ]);
        die;
    }
    
    private function createCacheKey(string $fieldType): string
    {
        return sprintf($this->cacheKey, $fieldType);
    }
    
    private function getSleepTime(int $workerSleepTime, int $commandNextRunTime): int
    {
        if ($workerSleepTime === 0) {
            return $commandNextRunTime;
        }
        if ($workerSleepTime > $commandNextRunTime) {
            return $commandNextRunTime;
        }
        
        return $workerSleepTime;
    }
    
    private function canRunTaskAgain(string $pidsCacheKey, string $cacheTaskField): bool
    {
        if (!$this->redis->hexists($pidsCacheKey, $cacheTaskField)) {
            return true;
        }
        
        return false;
    }
    
    private function waitForResult(string $pidsCacheKey, string $resultCacheKey, string $cacheTaskField)
    {
        $pid = (int)$this->redis->hget($pidsCacheKey, $cacheTaskField);
    
        $forkStatus = pcntl_waitpid($pid, $status);
    
        if ($forkStatus > 0) {
            $result = $this->redis->hget($resultCacheKey, $cacheTaskField);
            $this->redis->hdel($pidsCacheKey, [$cacheTaskField]);
            $this->redis->hdel($resultCacheKey, [$cacheTaskField]);
            $context = [
                'command' => $cacheTaskField,
                'result'  => json_decode($result, true),
            ];
            if (preg_match('/[\s\S]+Exception[\s\S]+/', $result)) {
                $this->logger->error('scheduler', $context);
            } else {
                $this->logger->info('scheduler', $context);
            }
        
            return true;
        }
    
        return false;
    }
    
    private function createCacheTaskKey(TaskInterface $task): string
    {
        return str_replace(' ', '', $task->getName());
    }
    
    /**
     * Run scripts like cron: 5 minute -> every 3:00, 3:05, 3:10
     *
     * @param int $interval
     *
     * @return int
     */
    protected function getCronLikeTimeSinceNow(int $interval)
    {
        $adjustSeconds = 0;
        
        $time = time();
        $mod = $time % $interval;
        if ($mod > 0) {
            $adjustSeconds = $interval - $mod;
        }
        
        $normalized = ($time + $adjustSeconds);
        
        return (time() === $normalized) ? ($normalized + $interval) : $normalized;
    }
}
