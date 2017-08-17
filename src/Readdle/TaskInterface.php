<?php

namespace Readdle\Scheduler;

interface TaskInterface
{
    public function getName(): string;
    public function getInterval(): int;
    public function run(): array;
}