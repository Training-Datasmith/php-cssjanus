<?php

declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Css_Janus_Benchmark.php';

$fixtures = null;
if ( isset( $argv[1] ) ) {
	$name = basename( $argv[1] );
	$data = file_get_contents( $argv[1] );
	$fixtures = [ $name => $data ];
}

( new Css_Janus_Benchmark( $fixtures ) )->run();
