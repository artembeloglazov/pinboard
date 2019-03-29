<?php
pinba_timer_start(['gegi' => 'local']);
pinba_server_name_set('gegi-pinba');
require __DIR__.'/../src/init.php';

$app['debug'] = true;

include __DIR__.'/../src/Pinboard/Controller/_before.php';
$app->mount('/',       include __DIR__.'/../src/Pinboard/Controller/index.php');
$app->mount('/server', include __DIR__.'/../src/Pinboard/Controller/server.php');
$app->mount('/timers', include __DIR__.'/../src/Pinboard/Controller/timer.php');

$app->run();
pinba_timer_stop();