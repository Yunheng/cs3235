<?php

// Kickstart the framework
$f3=require('lib/base.php');

if ((float)PCRE_VERSION<7.9)
	trigger_error('PCRE version is out of date');

// Load configuration
$f3->config('config.ini');

$f3->route('GET /admin', 'Admin->main');
$f3->route('GET|POST /admin/@function', 'Admin->@function', 0);
$f3->route('GET|POST /api/@function', 'API->exec', 0);

$f3->run();
