<?php

namespace Readdle\Scheduler;

interface TaskHandlerInterface
{
    public function addTask(TaskInterface $task);
    
    public function run(): bool;
}
