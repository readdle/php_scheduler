<?php
namespace Readdle\Scheduler;

class Scheduler
{
    protected $dataStorage;
    protected $scripts = [];

    protected $nextRun = null;

    public function __construct(PersistentStorage $dataStorage)
    {
        $this->dataStorage = $dataStorage;
    }

    public function register(int $interval, callable $script)
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

    protected function calcNextScriptRunTime(string $scriptName): int
    {
        $lasScriptRunTime = (int)$this->dataStorage->get($scriptName);

        $nextScriptRunTime = $this->scripts[$scriptName]['interval'] + $lasScriptRunTime;

        if (null === $this->nextRun or $this->nextRun > $nextScriptRunTime) {
            $this->nextRun = $nextScriptRunTime;
        }

        return $nextScriptRunTime;
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
