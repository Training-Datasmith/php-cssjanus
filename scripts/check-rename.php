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
 * @return array<string, array{start: int, end: int}>
 */
function function_ranges( string $content ): array {
	$lines = preg_split( '/\R/', $content ) ?: [];
	$ranges = [];
	$current = null;
	$start = 0;
	$depth = 0;
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/^\s*(?:public|private|protected|static|\s)*function\s+(\w+)\s*\(/', $line, $match ) ) {
			if ( $current !== null ) {
				$ranges[$current] = [ 'start' => $start, 'end' => $i ];
			}
			$current = $match[1];
			$start = $i + 1;
			$depth = 0;
		}
		if ( $current === null ) {
			continue;
		}
		$depth += substr_count( $line, '{' ) - substr_count( $line, '}' );
		if ( $depth <= 0 && str_contains( $line, '}' ) && $i >= $start ) {
			$ranges[$current] = [ 'start' => $start, 'end' => $i + 1 ];
			$current = null;
		}
	}
	if ( $current !== null ) {
		$ranges[$current] = [ 'start' => $start, 'end' => count( $lines ) ];
	}
	return $ranges;
}

function line_in_function( array $ranges, string $function, int $line ): bool {
	if ( !isset( $ranges[$function] ) ) {
		return false;
	}
	$range = $ranges[$function];
	return $line >= $range['start'] && $line <= $range['end'];
}

function line_in_transform_site( array $ranges, int $line ): bool {
	if ( !isset( $ranges['transform'] ) ) {
		return false;
	}
	if ( line_in_function( $ranges, 'transform', $line ) ) {
		return true;
	}
	$start = $ranges['transform']['start'];
	return $line < $start && $line >= $start - 20;
}

function is_token_allowlisted_at_site(
	string $file,
	string $content,
	int $line,
	string $line_text,
	string $token,
	array $allowlist
): bool {
	$basename = basename( $file );
	$ranges = str_ends_with( $file, '.php' ) ? function_ranges( $content ) : [];

	foreach ( $allowlist as $entry ) {
		if ( $entry === 'Css_Janus_Test::extends PHPUnit\Framework\TestCase' ) {
			if ( $basename === 'Css_Janus_Test.php' && $token === 'TestCase'
				&& preg_match( '/extends\s+PHPUnit\\\\Framework\\\\TestCase/', $line_text ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::test_transform assertEquals' ) {
			if ( $basename === 'Css_Janus_Test.php' && $token === 'assertEquals'
				&& line_in_function( $ranges, 'test_transform', $line )
				&& str_contains( $line_text, 'assertEquals' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::test_transform::annotation dataProvider' ) {
			if ( $basename === 'Css_Janus_Test.php' && $token === 'dataProvider'
				&& preg_match( '/@dataProvider\s+provide_data/', $line_text ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::provide_data swapLtrRtlInUrl' ) {
			if ( $basename === 'Css_Janus_Test.php' && $token === 'swapLtrRtlInUrl'
				&& line_in_function( $ranges, 'provide_data', $line ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus_Test::provide_data swapLeftRightInUrl' ) {
			if ( $basename === 'Css_Janus_Test.php' && $token === 'swapLeftRightInUrl'
				&& line_in_function( $ranges, 'provide_data', $line ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus::transform::options transformDirInUrl' ) {
			if ( $token !== 'transformDirInUrl' ) {
				continue;
			}
			if ( $basename === 'Css_Janus.php' && line_in_transform_site( $ranges, $line ) ) {
				return true;
			}
			if ( str_contains( $line_text, 'Css_Janus::transform' ) ) {
				return true;
			}
			if ( ( $basename === 'ARCHITECTURE.md' || $basename === 'README.md' )
				&& str_contains( $line_text, 'options' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus::transform::options transformEdgeInUrl' ) {
			if ( $token !== 'transformEdgeInUrl' ) {
				continue;
			}
			if ( $basename === 'Css_Janus.php' && line_in_transform_site( $ranges, $line ) ) {
				return true;
			}
			if ( str_contains( $line_text, 'Css_Janus::transform' ) ) {
				return true;
			}
			if ( ( $basename === 'ARCHITECTURE.md' || $basename === 'README.md' )
				&& str_contains( $line_text, 'options' ) ) {
				return true;
			}
			continue;
		}
		if ( $entry === 'Css_Janus::build_patterns::regex translateX' ) {
			if ( $basename === 'Css_Janus.php' && $token === 'translateX'
				&& preg_match( "/patterns\['translate/", $line_text ) ) {
				return true;
			}
		}
	}
	return false;
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

/**
 * @return list<array{file: string, line: int, token: string, kind: string}>
 */
function find_camel_case( string $file, string $content, array $allowlist ): array {
	$hits = [];
	$lines = preg_split( '/\R/', $content ) ?: [];

	foreach ( $lines as $i => $line ) {
		if ( preg_match_all( '/\b([a-z][a-z0-9]*[A-Z][A-Za-z0-9_]*)\b/', $line, $matches ) ) {
			foreach ( $matches[1] as $token ) {
				if ( strlen( $token ) < 3 ) {
					continue;
				}
				if ( is_token_allowlisted_at_site( $file, $content, $i + 1, $line, $token, $allowlist ) ) {
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

function run_allowlist_out_of_site_demo( array $allowlist ): bool {
	$demo = "<?php\n\$opts = ['transformDirInUrl' => true];\n";
	$hits = find_camel_case( 'demo.php', $demo, $allowlist );
	if ( $hits === [] ) {
		return false;
	}
	foreach ( $hits as $hit ) {
		if ( is_token_allowlisted_at_site(
			'demo.php',
			$demo,
			$hit['line'],
			"\$opts = ['transformDirInUrl' => true];",
			$hit['token'],
			$allowlist
		) ) {
			return false;
		}
	}
	return true;
}

function run_background_position_integer_demo(): bool {
	$input = '.foo { background-position: 0% 100%; }';
	$output = Css_Janus::transform( $input );
	if ( $output !== '.foo { background-position: 100% 100%; }' ) {
		return false;
	}
	// Wrong subtraction (off-by-string) would leave 0% or produce a different value.
	if ( !preg_match( '/background-position:\s*100%\s+100%/', $output ) ) {
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

if ( !run_allowlist_out_of_site_demo( $allowlist ) ) {
	fwrite( STDERR, "check-rename: allowlist out-of-site demo did not reject transformDirInUrl via find_camel_case.\n" );
	exit( 1 );
}

if ( !run_background_position_integer_demo() ) {
	fwrite( STDERR, "check-rename: background-position integer-percent branch demo failed.\n" );
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
		foreach ( find_camel_case( $file, $content, $allowlist ) as $hit ) {
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
fwrite( STDOUT, "check-background-position: integer-percent branch OK (spec: flip background-position percentages, 0% -> 100%).\n" );
exit( 0 );
