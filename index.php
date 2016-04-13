<?php

require __DIR__ . '/vendor/autoload.php';

// Kickstart the framework
$f3=Base::instance();

if ((float)PCRE_VERSION<7.9)
	trigger_error('PCRE version is out of date');

// Load configuration
$f3->config('config.ini');

$f3->route('GET /', function($f3) {
	$f3->reroute('/admin');
});

$f3->route('GET /admin', 'Admin->exec');
$f3->route('GET|POST /admin/@function', 'Admin->exec', 0);
$f3->route('GET|POST /api/@function', 'API->exec', 0);
$f3->route('GET /info', function($f3) {
	phpinfo();
});

$f3->run();
