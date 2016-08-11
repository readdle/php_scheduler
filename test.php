<?php

include "./vendor/autoload.php";

// Example of scheduled script
class SomeScript
{
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __invoke()
    {
        echo time() . " - {$this->name}" . PHP_EOL;
    }
}

// Mock of persistent storage. In production we reccomend to use redis storage
class Storage
{
    protected $keyValue = [];

    public function set(string $key, int $value)
    {
        $this->keyValue[$key] = $value;
    }

    public function get(string $key)
    {
        return array_key_exists($key, $this->keyValue) ? $this->keyValue[$key] : null;
    }
}

$storage = new Storage();

// Create scheduler object
$scheduler = new \Readdle\Scheduler\Scheduler(
    new \Readdle\Scheduler\PersistentStorage('test', [$storage, 'set'], [$storage, 'get'])
);

// Register your scripts
$scheduler->register(10, new SomeScript('10 second'));
$scheduler->register(5, new SomeScript('5 second'));
$scheduler->register(20, new SomeScript('20 second'));
$scheduler->register(7, new SomeScript('7 second'));
$scheduler->register(27, new SomeScript('27 second'));

// Start scheduler loop
$scheduler->loop();
