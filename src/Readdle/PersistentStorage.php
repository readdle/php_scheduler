<?php
namespace Readdle\Scheduler;

class PersistentStorage
{
    const CACHE_PREFIX = 'php_scheduler';

    protected $functions;
    protected $appName;

    public function __construct(string $appName, callable $setFunc, callable $getFunc)
    {
        $this->functions['set'] = $setFunc;
        $this->functions['get'] = $getFunc;
        $this->appName = $appName;
    }

    protected function getNameSpacedKey(string $key)
    {
        return self::CACHE_PREFIX . ":{$this->appName}:{$key}";
    }

    public function save(string $key, string $value)
    {
        $this->functions['set']($this->getNameSpacedKey($key), $value);
    }

    public function get(string $key)
    {
        return $this->functions['get']($this->getNameSpacedKey($key));
    }
}
