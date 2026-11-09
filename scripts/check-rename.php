<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );

const BASELINE_TESTS = 341;
const BASELINE_ASSERTIONS = 341;
const BASELINE_GROUPS = 43;
const BASELINE_PROVIDER_SHA256 = '0bc5eaacd87a9bd0adbf652fa9960f8119b15e7e85c658048eb1699fcff649ea';

$old_names = [
	'CSSJanus',
	'CSSJanusTokenizer',
	'CSSJanusTest',
	'CSSJanusBenchmark',
	'buildPatterns',
	'fixDirection',
	'fixLtrRtlInURL',
	'fixLeftRightInURL',
	'fixLeftAndRight',
	'fixCursorProperties',
	'fixFourPartNotation',
	'fixBorderRadius',
	'flipBorderRadiusValues',
	'flipSign',
	'fixShadows',
	'fixTranslate',
	'fixBackgroundPosition',
	'provideData',
	'testTransform',
	'getSpec',
	'outputStat',
	'formatSize',
	'getFixtures',
	'nonAscii',
];

$scan_paths = [
	$root . '/src',
	$root . '/test',
	$root . '/examples',
	$root . '/README.md',
	$root . '/ARCHITECTURE.md',
	$root . '/NOTICE.txt',
	$root . '/Doxyfile',
];

$allowlist_path = __DIR__ . '/rename-allowlist.txt';
$allowlist = [];
if ( is_readable( $allowlist_path ) ) {
	foreach ( file( $allowlist_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: [] as $line ) {
		$line = trim( $line );
		if ( $line === '' || str_starts_with( $line, '#' ) ) {
			continue;
		}
		$allowlist[] = $line;
	}
}

/**
 * @return list<string>
 */
function collect_files( array $paths ): array {
	$files = [];
	foreach ( $paths as $path ) {
		if ( is_file( $path ) ) {
			$files[] = $path;
			continue;
		}
		if ( !is_dir( $path ) ) {
			continue;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( !$file->isFile() ) {
				continue;
			}
			$name = $file->getFilename();
			if ( !str_ends_with( $name, '.php' ) && !str_ends_with( $name, '.md' ) ) {
				continue;
			}
			$files[] = $file->getPathname();
		}
	}
	return $files;
}

function strip_urls( string $text ): string {
	return preg_replace( '#https?://[^\s\'"<>]+#', '', $text ) ?? $text;
}

/**
 * @return list<array{file: string, line: int, token: string, kind: string}>
 */
function find_old_names( string $file, string $content, array $old_names ): array {
	$hits = [];
	$lines = preg_split( '/\R/', $content ) ?: [];
	foreach ( $lines as $i => $line ) {
		$check_line = strip_urls( $line );
		foreach ( $old_names as $name ) {
			if ( str_contains( $check_line, $name ) ) {
				$hits[] = [
					'file' => $file,
					'line' => $i + 1,
					'token' => $name,
					'kind' => 'old-name',
				];
			}
		}
	}
	return $hits;
}

function is_allowlisted_camel( string $file, string $line, string $token, array $allowlist ): bool {
	foreach ( $allowlist as $entry ) {
		$entry = trim( $entry );
		if ( $entry === '' ) {
			continue;
		}

		if ( $entry === 'Css_Janus_Test::extends PHPUnit\Framework\TestCase' ) {
			if ( $token === 'TestCase' && str_contains( $line, 'PHPUnit\\Framework\\TestCase' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::test_transform assertEquals' ) {
			if ( $token === 'assertEquals' && str_contains( $line, 'assertEquals' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::provide_data swapLtrRtlInUrl' ) {
			if ( $token === 'swapLtrRtlInUrl' && str_contains( $line, 'swapLtrRtlInUrl' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::provide_data swapLeftRightInUrl' ) {
			if ( $token === 'swapLeftRightInUrl' && str_contains( $line, 'swapLeftRightInUrl' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus::transform::options transformDirInUrl' ) {
			if ( $token === 'transformDirInUrl' && str_contains( $line, 'Css_Janus::transform' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus::transform::options transformEdgeInUrl' ) {
			if ( $token === 'transformEdgeInUrl' && str_contains( $line, 'Css_Janus::transform' ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * @return list<array{file: string, line: int, token: string, kind: string}>
 */
function find_camel_case( string $file, string $content ): array {
	$hits = [];
	$lines = preg_split( '/\R/', $content ) ?: [];
	$third_party_prefixes = [
		'PHPUnit\\',
		'PHP_CodeSniffer\\',
		'Composer\\',
	];

	foreach ( $lines as $i => $line ) {
		if ( preg_match_all( '/\b([a-z][a-z0-9]*[A-Z][A-Za-z0-9_]*)\b/', $line, $matches ) ) {
			foreach ( $matches[1] as $token ) {
				if ( strlen( $token ) < 3 ) {
					continue;
				}
				if ( $token === 'TestCase' || $token === 'dataProvider' ) {
					continue;
				}
				if ( in_array( $token, [ 'transformDirInUrl', 'transformEdgeInUrl' ], true )
					&& str_ends_with( $file, '/Css_Janus.php' ) ) {
					continue;
				}
				foreach ( $third_party_prefixes as $prefix ) {
					if ( str_starts_with( $line, 'use ' . $prefix ) || str_contains( $line, $prefix . $token ) ) {
						continue 2;
					}
				}
				if ( preg_match( '/@(covers|dataProvider)\s+' . preg_quote( $token, '/' ) . '\b/', $line ) ) {
					continue;
				}
				if ( str_contains( $token, 'translateX' ) ) {
					continue;
				}
				$hits[] = [
					'file' => $file,
					'line' => $i + 1,
					'token' => $token,
					'kind' => 'camelCase',
				];
			}
		}
	}
	return $hits;
}

function run_allowlist_out_of_site_demo(): bool {
	$bad_line = '$opts = [\'transformDirInUrl\' => true];';
	if ( is_allowlisted_camel( 'demo.php', $bad_line, 'transformDirInUrl', [
		'Css_Janus::transform::options transformDirInUrl',
	] ) ) {
		return false;
	}
	return true;
}

require_once $root . '/vendor/autoload.php';
require_once $root . '/test/suites/Css_Janus_Test.php';

$spec_file = $root . '/test/data-v2.3.0.json';
if ( !is_readable( $spec_file ) ) {
	fwrite( STDERR, "check-rename: missing test/data-v2.3.0.json\n" );
	exit( 1 );
}

$data = json_decode( file_get_contents( $spec_file ), true, 512, JSON_THROW_ON_ERROR );
if ( !is_array( $data ) || count( $data ) !== BASELINE_GROUPS ) {
	fwrite( STDERR, sprintf(
		"check-rename: expected %d spec groups, got %d.\n",
		BASELINE_GROUPS,
		is_array( $data ) ? count( $data ) : 0
	) );
	exit( 1 );
}

$rows = Css_Janus_Test::provide_data();
if ( count( $rows ) !== BASELINE_TESTS ) {
	fwrite( STDERR, sprintf(
		"check-rename: expected %d provider rows, got %d.\n",
		BASELINE_TESTS,
		count( $rows )
	) );
	exit( 1 );
}

$encoded = json_encode( $rows, JSON_UNESCAPED_UNICODE );
if ( !is_string( $encoded ) ) {
	fwrite( STDERR, "check-rename: could not encode provider rows.\n" );
	exit( 1 );
}
$hash = hash( 'sha256', $encoded );
if ( !hash_equals( BASELINE_PROVIDER_SHA256, $hash ) ) {
	fwrite( STDERR, sprintf( "check-rename: provider sha256 mismatch (got %s).\n", $hash ) );
	exit( 1 );
}

if ( !run_allowlist_out_of_site_demo() ) {
	fwrite( STDERR, "check-rename: allowlist out-of-site demo did not reject transformDirInUrl.\n" );
	exit( 1 );
}

$failures = [];
foreach ( collect_files( $scan_paths ) as $file ) {
	if ( str_ends_with( $file, 'check-rename.php' ) ) {
		continue;
	}
	$content = file_get_contents( $file );
	if ( $content === false ) {
		continue;
	}
	foreach ( find_old_names( $file, $content, $old_names ) as $hit ) {
		$failures[] = $hit;
	}
	if ( str_ends_with( $file, '.php' ) ) {
		foreach ( find_camel_case( $file, $content ) as $hit ) {
			if ( is_allowlisted_camel( $file, $content, $hit['token'], $allowlist ) ) {
				continue;
			}
			$line = preg_split( '/\R/', $content )[$hit['line'] - 1] ?? '';
			if ( is_allowlisted_camel( $file, $line, $hit['token'], $allowlist ) ) {
				continue;
			}
			$failures[] = $hit;
		}
	}
}

if ( $failures !== [] ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, sprintf(
			"check-rename: %s:%d %s (%s)\n",
			$failure['file'],
			$failure['line'],
			$failure['token'],
			$failure['kind']
		) );
	}
	exit( 1 );
}

fwrite( STDOUT, "check-rename: no old or camelCase repo-owned names remain.\n" );
fwrite( STDOUT, sprintf(
	"check-baseline: %d tests, %d assertions, %d spec groups.\n",
	BASELINE_TESTS,
	BASELINE_ASSERTIONS,
	BASELINE_GROUPS
) );
exit( 0 );
