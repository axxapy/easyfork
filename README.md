Single fork
=============
Create signle fork and read it's result:
```php
$proc = (new Fork(job: function(Process $proc) {
    $proc->shared_memory['job_is_done'] = true;
}))->run();

var_dump($proc->shared_memory['job_is_done']); // null

$proc->waitFor();

var_dump($proc->shared_memory['job_is_done']); // true

```

Downloaded multiple web pages in parallel:
```php

$fork = new Fork(job: function(Process $proc, ...$args) {
    $url = $args[0];
    $proc->shared_memory['result'] = file_get_contents($url);
});

$procs = [
    $fork->run('https://some-website.com/page1.html'),
    $fork->run('https://some-website.com/page2.html'),
    $fork->run('https://some-website.com/page3.html'),
];

// do some other job

//when ready, read result:
foreach ($procs as $proc) {
    $proc->waitFor();
    echo $proc->shared_memory['result'];
}
```

# ForkPoolExecutor
```php
$stop = false;
$result = (new ForkPoolExecutor(job: function(Process $proc) use (&$stop) {
	$proc->shared_memory["fork_$this->id"] = 'result';
}))->run();

var_dump($result);
```

