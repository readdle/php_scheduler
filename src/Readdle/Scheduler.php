<?php
namespace Readdle\Scheduler;

class Scheduler
{
    const ONE_MINUTE_INTERVAL = 60;
    const FIVE_MINUTE_INTERVAL = 300;

    const ONE_HOUR_INTERVAL = 3600;
    const TWO_HOUR_INTERVAL = 7200;
    const FOUR_HOUR_INTERVAL = 14400;

    const ONE_DAY_INTERVAL = 86400;


    protected $dataStorage;
    protected $scripts = [];

    protected $nextRun = null;

    public function __construct(PersistentStorage $dataStorage)
    {
        $this->dataStorage = $dataStorage;
    }

    public function register(int $interval, callable $script)
    {
        if ($interval < 2) {
            throw new \Exception("Interval cannot be less that 2 sec.");
        }

        if (!method_exists($script, "getName")) {
            $scriptName = (new \ReflectionClass($script))->getShortName();
        } else {
            $scriptName = $script->getName();
        }

        $this->scripts["{$scriptName}:{$interval}"] = [
            'callable' => $script,
            'interval' => $interval
        ];
    }

    /**
     * Run scripts like cron: 5 minute -> every 3:00, 3:05, 3:10
     * @param int $interval
     * @return int
     */
    protected function getCronLikeTimeSinceNow(int $interval): int
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

    public function loop()
    {
        count($this->scripts) or die("Nothing to do" . PHP_EOL);

        $proccesess = [];
        while (true) {
            foreach ($proccesess as $key => $pid) {
                if (pcntl_waitpid($pid, $status) === -1) {
                    continue;
                }
                unset($proccesess[$key]);
            }
            foreach ($this->scripts as $scriptName => $scriptDetails) {
                $nextRun = (int)$this->dataStorage->get($scriptName);

                if ($nextRun > time()) {
                    $this->updateNextRun($nextRun);
                    continue;
                }

                $pid = pcntl_fork();
                if ($pid == -1) {
                    throw new \InvalidArgumentException("Can't fork process");
                } elseif ($pid == 0) {
                    try {
                        # running script
                        call_user_func($scriptDetails['callable']);
                        exit(0);
                    } catch (\Throwable $exception) {
                        exit (1);
                    }
                }
                $proccesess[] = $pid;

                # setup time for next running
                $nextRun = $this->getCronLikeTimeSinceNow($scriptDetails['interval']);
                $this->updateNextRun($nextRun);
                $this->dataStorage->save($scriptName, $nextRun);
            }

            if ($this->nextRun > time() + 1) { //to avoid proble with time_sleep_until 
                time_sleep_until($this->nextRun);
            }
            $this->nextRun = null;
        }
    }

    protected function updateNextRun(int $nextRun)
    {
        if ((null === $this->nextRun) || ($nextRun < $this->nextRun)) {
            $this->nextRun = $nextRun;
        }
    }
}
