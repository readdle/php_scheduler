<?php
namespace Readdle\Scheduler;

class Scheduler
{
    const ONE_DAY_INTERVAL = 86400;
    const ONE_HOUR_INTERVAL = 3600;
    const TWO_HOUR_INTERVAL = 7200;
    const FOUR_HOUR_INTERVAL = 14400;
    const FIVE_MINUTE_INTERVAL = 300;

    protected $dataStorage;
    protected $scripts = [];

    protected $nextRun = null;

    public function __construct(PersistentStorage $dataStorage)
    {
        $this->dataStorage = $dataStorage;
    }

    public function register($interval, $script)
    {
        if ($interval < 1) {
            throw new \Exception("Interval cannot be less that 1 sec.");
        }

        $scriptName = (new \ReflectionClass($script))->getShortName();

        $this->scripts[$scriptName] = [
            'callable' => $script,
            'interval' => $interval
        ];
    }

    protected function calcNextScriptRunTime($scriptName)
    {
        $lasScriptRunTime = (int)$this->dataStorage->get($scriptName);
        $lasScriptRunTime = $this->getCronLikeTime($lasScriptRunTime, $this->scripts[$scriptName]['interval']);

        $nextScriptRunTime = $this->scripts[$scriptName]['interval'] + $lasScriptRunTime;

        if (null === $this->nextRun or $this->nextRun > $nextScriptRunTime) {
            $this->nextRun = $nextScriptRunTime;
        }

        return $nextScriptRunTime;
    }

    /**
     * Run scripts like cron: 5 minute -> every 3:00, 3:05, 3:10
     * @param int $time
     * @param int $interval
     * @return int
     */
    protected function getCronLikeTime($time, $interval)
    {
        $adjustSeconds = 0;

        $mod = $time % $interval;
        if ($mod > 0) {
            $adjustSeconds = $interval - $mod;
        }

        return $time + $adjustSeconds;
    }

    public function loop()
    {
        count($this->scripts) or die("Nothing to do" . PHP_EOL);

        while (true) {
            foreach ($this->scripts as $scriptName => $scriptDetails) {
                $nextRun = $this->calcNextScriptRunTime($scriptName);

                if ($nextRun > time()) {
                    continue;
                }

                $scriptDetails['callable']();
                $this->dataStorage->save($scriptName, time());
            }

            if ($this->nextRun > time()) {
                time_sleep_until($this->nextRun + 1);
            } else {
                time_sleep_until(time() + 1);
            }
            $this->nextRun = null;
        }
    }
}
