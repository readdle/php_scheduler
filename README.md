**General scheduler project**

- docker-compose file is only for dev
- This project will be included in fluix/baselemp container and default supervisor config

**Settings**

make you script inside your app container:

```php
<?php
/**
 * @interval 5
 */
class SomeScript 
{
    public function __invoke() 
    {
        echo 'this row will be echoed every 5 sec';
    }
}
```

make your config file inside your app container:

```php
<?php

return [
    'redis' => '127.0.0.1',
    'appName' => 'My awesome app',
    'scripts' => [
        new SomeScript
    ]
];
```

run your app container with CMD:

```
["supervisord","-c","supervisor/scheduler.ini","-n"]
```
