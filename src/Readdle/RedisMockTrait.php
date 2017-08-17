<?php

namespace Readdle\Scheduler;

use Predis\ClientInterface;

trait RedisMockTrait
{
    private $redisData = [];
    private $redis = null;
    
    public function redisFunctions($method, $arguments)
    {
        $result = false;
        $this->redisData = $this->getSharedData();
        switch ($method) {
            case 'hexists':
                $result = $this->hExists($arguments[0], $arguments[1]);
                break;
            case 'hget':
                $result = $this->hget($arguments[0], $arguments[1]);
                break;
            case 'hset':
                $result = $this->hset($arguments[0], $arguments[1], $arguments[2]);
                break;
            case 'hdel':
                $result = $this->hdel($arguments[0], $arguments[1]);
                break;
        }
        
        $this->saveSharedData($this->redisData);
        
        return $result;
    }
    
    protected function getRedis(): ClientInterface
    {
        if ($this->redis instanceof ClientInterface) {
            return $this->redis;
        }
        $redis = $this->getRedisMock();
        $redis->method('__call')->willReturnCallback([$this, 'redisFunctions']);
        
        /**
         * @var ClientInterface $redis
         */
        return $redis;
    }
    
    private function hExists(string $key, string $field): bool
    {
        if (!array_key_exists($key, $this->redisData)) {
            return false;
        }
        if (!array_key_exists($field, $this->redisData[$key])) {
            return false;
        }
        
        return true;
    }
    
    private function hget(string $key, string $field): string
    {
        if (!$this->hExists($key, $field)) {
            return '';
        }
        
        return $this->redisData[$key][$field];
    }
    
    private function hset(string $key, string $field, string $value): int
    {
        if (!array_key_exists($key, $this->redisData)) {
            $this->redisData[$key] = [];
        }
        $this->redisData[$key][$field] = $value;
        
        return 1;
    }
    
    private function hdel(string $key, array $fields): int
    {
        if (!array_key_exists($key, $this->redisData)) {
            return 1;
        }
        foreach ($fields as $field) {
            if (!array_key_exists($field, $this->redisData[$key])) {
                continue;
            }
            unset($this->redisData[$key][$field]);
        }
        
        return 1;
    }
    
    private function getSharedData(): array
    {
        $shm = shmop_open(0xff3, "c", 0644, 2048);
        $shm_size = shmop_size($shm);
        $data = shmop_read($shm, 0, $shm_size);
        shmop_close($shm);
        $position = strpos($data, "\x00");
        if ($position !== false && $position > 0) {
            $data = substr($data, 0, $position);
            $data = json_decode($data, true);
            
            return $data;
        }
        
        return [];
    }
    
    private function saveSharedData(array $data): bool
    {
        $this->clearRedis();
        $shm = shmop_open(0xff3, "c", 0644, 2048);
        $dataToSave = json_encode($data);
        $savedLength = shmop_write($shm, $dataToSave, 0);
        shmop_close($shm);
        
        return $savedLength === strlen($dataToSave);
    }
    
    protected function clearRedis()
    {
        $shm = shmop_open(0xff3, "c", 0644, 2048);
        shmop_delete($shm);
        shmop_close($shm);
    }
    
    abstract protected function getRedisMock(): \PHPUnit_Framework_MockObject_MockObject;
}
