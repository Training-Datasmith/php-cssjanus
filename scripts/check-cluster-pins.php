<?php

declare( strict_types=1 );

/**
 * Verify installed cluster packages match fixed Training-Datasmith branch tips.
 *
 * Expected map is empty (phpunit stays on Packagist). Wrong-SHA demos run in memory only.
 */
$expected = [];

/**
 * @param array<string, array{reference: string, alias: string}> $expected
 * @param array{versions?: array<string, array{reference?: string, aliases?: list<string>}>} $installed_php
 * @param list<array{name?: string, source?: array{reference?: string}}> $json_packages
 * @return list<array{side: string, message: string}>
 */
function evaluate_pins( array $expected, array $installed_php, array $json_packages ): array {
	$versions = $installed_php['versions'] ?? [];
	$json_by_name = [];
	foreach ( $json_packages as $package ) {
		if ( !isset( $package['name'] ) ) {
			continue;
		}
		$json_by_name[$package['name']] = $package;
	}

	$failures = [];
	foreach ( $expected as $name => $want ) {
		if ( !isset( $versions[$name] ) ) {
			$failures[] = [
				'side' => 'installed.php',
				'message' => sprintf( '%s is not installed.', $name ),
			];
			continue;
		}

		$pkg = $versions[$name];
		$aliases = $pkg['aliases'] ?? [];
		if ( !in_array( $want['alias'], $aliases, true ) ) {
			$failures[] = [
				'side' => 'installed.php',
				'message' => sprintf(
					'%s alias mismatch (expected %s in aliases %s).',
					$name,
					$want['alias'],
					json_encode( $aliases )
				),
			];
		}

		$reference = $pkg['reference'] ?? null;
		if ( !is_string( $reference ) || $reference === '' ) {
			$failures[] = [
				'side' => 'installed.php',
				'message' => sprintf( '%s has no reference.', $name ),
			];
		} elseif ( !hash_equals( strtolower( $want['reference'] ), strtolower( $reference ) ) ) {
			$failures[] = [
				'side' => 'installed.php',
				'message' => sprintf(
					'%s reference mismatch (expected %s, got %s).',
					$name,
					$want['reference'],
					$reference
				),
			];
		}

		if ( !isset( $json_by_name[$name] ) ) {
			$failures[] = [
				'side' => 'installed.json',
				'message' => sprintf( '%s is not in installed.json.', $name ),
			];
			continue;
		}

		$json_ref = $json_by_name[$name]['source']['reference'] ?? null;
		if ( !is_string( $json_ref ) || $json_ref === '' ) {
			$failures[] = [
				'side' => 'installed.json',
				'message' => sprintf( '%s has no source.reference.', $name ),
			];
		} elseif ( !hash_equals( strtolower( $want['reference'] ), strtolower( $json_ref ) ) ) {
			$failures[] = [
				'side' => 'installed.json',
				'message' => sprintf(
					'%s source.reference mismatch (expected %s, got %s).',
					$name,
					$want['reference'],
					$json_ref
				),
			];
		}
	}

	return $failures;
}

/**
 * @return list<array{side: string, message: string}>
 */
function failures_for_side( array $failures, string $side ): array {
	return array_values( array_filter(
		$failures,
		static fn ( array $row ): bool => $row['side'] === $side
	) );
}

$installed_php_path = dirname( __DIR__ ) . '/vendor/composer/installed.php';
$installed_json_path = dirname( __DIR__ ) . '/vendor/composer/installed.json';

if ( !is_readable( $installed_php_path ) ) {
	fwrite( STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n" );
	exit( 1 );
}

if ( !is_readable( $installed_json_path ) ) {
	fwrite( STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n" );
	exit( 1 );
}

/** @var array{versions?: array<string, array{reference?: string, aliases?: list<string>}>} $installed_php */
$installed_php = require $installed_php_path;

$raw_json = file_get_contents( $installed_json_path );
if ( $raw_json === false ) {
	fwrite( STDERR, "check-cluster-pins: could not read installed.json.\n" );
	exit( 1 );
}

/** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $installed_json */
$installed_json = json_decode( $raw_json, true, 512, JSON_THROW_ON_ERROR );
$json_packages = array_merge( $installed_json['packages'] ?? [], $installed_json['packages-dev'] ?? [] );

$live_failures = evaluate_pins( $expected, $installed_php, $json_packages );
if ( $live_failures !== [] ) {
	foreach ( $live_failures as $failure ) {
		fwrite( STDERR, sprintf( "check-cluster-pins: [%s] %s\n", $failure['side'], $failure['message'] ) );
	}
	exit( 1 );
}

$demo_name = 'example/cluster-pin-demo';
$good_ref = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$bad_ref = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$demo_expected = [
	$demo_name => [
		'reference' => $good_ref,
		'alias' => '10.5.63',
	],
];

$php_versions = [
	'versions' => [
		$demo_name => [
			'reference' => $bad_ref,
			'aliases' => [ '10.5.63' ],
		],
	],
];
$json_packages_php_wrong = [
	[
		'name' => $demo_name,
		'source' => [ 'reference' => $good_ref ],
	],
];

$php_demo_failures = evaluate_pins( $demo_expected, $php_versions, $json_packages_php_wrong );
if ( failures_for_side( $php_demo_failures, 'installed.php' ) === [] ) {
	fwrite( STDERR, "check-cluster-pins: wrong-SHA demo installed.php did not fail.\n" );
	exit( 1 );
}
if ( failures_for_side( $php_demo_failures, 'installed.json' ) !== [] ) {
	fwrite( STDERR, "check-cluster-pins: wrong-SHA demo installed.php incorrectly failed installed.json side.\n" );
	exit( 1 );
}
fwrite( STDOUT, "check-cluster-pins: wrong-SHA demo installed.php failed as expected.\n" );

$json_versions = [
	'versions' => [
		$demo_name => [
			'reference' => $good_ref,
			'aliases' => [ '10.5.63' ],
		],
	],
];
$json_packages_json_wrong = [
	[
		'name' => $demo_name,
		'source' => [ 'reference' => $bad_ref ],
	],
];

$json_demo_failures = evaluate_pins( $demo_expected, $json_versions, $json_packages_json_wrong );
if ( failures_for_side( $json_demo_failures, 'installed.json' ) === [] ) {
	fwrite( STDERR, "check-cluster-pins: wrong-SHA demo installed.json did not fail.\n" );
	exit( 1 );
}
if ( failures_for_side( $json_demo_failures, 'installed.php' ) !== [] ) {
	fwrite( STDERR, "check-cluster-pins: wrong-SHA demo installed.json incorrectly failed installed.php side.\n" );
	exit( 1 );
}
fwrite( STDOUT, "check-cluster-pins: wrong-SHA demo installed.json failed as expected.\n" );

fwrite( STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n" );
exit( 0 );
