**General scheduler project**

- docker-compose file is only for dev

**Example usage**

```php
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

class SomeScript1 extends SomeScript {};

class SomeScript2 extends SomeScript {};

class SomeScript3 extends SomeScript {};

class SomeScript4 extends SomeScript {};

// Mock of persistent storage. In production we reccomend to use redis storage
class Storage
{
    protected $keyValue = [];

    public function set($key, $value)
    {
        $this->keyValue[$key] = $value;
    }

    public function get($key)
    {
        return array_key_exists($key, $this->keyValue) ? $this->keyValue[$key] : 0;
    }
}

$storage = new Storage();

// Create scheduler object
$scheduler = new \Readdle\Scheduler\Scheduler(
    new \Readdle\Scheduler\PersistentStorage(
        'test',
        [$storage, 'set'],
        [$storage, 'get']
        )
);

// Register your scripts
$scheduler->register(10, new SomeScript('10 second'));
$scheduler->register(20, new SomeScript1('20 second'));
$scheduler->register(7, new SomeScript2('7 second'));
$scheduler->register(3, new SomeScript3('3 second'));
$scheduler->register(27, new SomeScript4('27 second'));

// Start scheduler loop
$scheduler->loop();
```
