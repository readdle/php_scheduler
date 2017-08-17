<?php

namespace Readdle\Scheduler;

class Command implements TaskInterface
{
    private $name;
    private $interval;
    
    public function __construct(string $name, int $interval)
    {
        $this->name = $name;
        $this->interval = $interval;
    }
    
    public function getInterval(): int
    {
        return $this->interval;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function run(): array
    {
        exec($this->getName(), $result);
        
        return $result;
    }
}
