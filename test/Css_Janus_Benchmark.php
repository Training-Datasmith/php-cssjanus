<?php

declare( strict_types=1 );

class Css_Janus_Benchmark {
	private array $fixtures;

	/**
	 * @param null|array<string,string> $fixtures Map from label to input CSS
	 */
	public function __construct( ?array $fixtures = null ) {
		$this->fixtures = $fixtures ?? self::get_fixtures();
	}

	public function run() {
		foreach ( $this->fixtures as $name => $data ) {
			$iterations = 10_000;
			$total = 0;
			$max = -INF;
			for ( $i = 1; $i <= $iterations; $i++ ) {
				$start = microtime( true );
				Css_Janus::transform( $data, [ 'transformDirInUrl' => true ] );
				$took = ( microtime( true ) - $start ) * 1000;
				$max = max( $max, $took );
				$total += ( microtime( true ) - $start ) * 1000;
			}
			$this->output_stat( $name, $data, $iterations, $total, $max );
		}
	}

	protected function output_stat( string $name, string $data, int $iterations, float $total, float $max ): void {
		$version = hash( 'fnv132', $data );
		$mean = $total / $iterations; // in milliseconds
		$rate_per_second = 1.0 / ( $mean / 1000.0 );

		echo sprintf(
			"* %-35s iterations=%d max=%.2fms mean=%.2fms rate=%.0f/s\n",
			"$name ($version)",
			$iterations,
			$max,
			$mean,
			$rate_per_second
		);
	}

	private function format_size( int $size ): string {
		$i = (int)floor( log( $size, 1024 ) );
		return round( $size / pow( 1024, $i ), [ 0, 0, 2, 2, 3 ][$i] ) . ' ' . [ 'B', 'KB', 'MB', 'GB', 'TB' ][$i];
	}

	protected function get_fixtures(): array {
		$fixtures = [
			'mediawiki-legacy-shared' => [
				'version' => '1064426',
				'src' => 'https://github.com/wikimedia/mediawiki/raw/1064426'
					. '/resources/src/mediawiki.legacy/shared.css',
			],
			'ooui-core' => [
				'version' => '130344b',
				'src' => 'https://github.com/wikimedia/mediawiki/raw/130344b'
					. '/resources/lib/oojs-ui/oojs-ui-core-wikimediaui.css',
			],
		];
		$result = [];
		$dir = __DIR__;
		foreach ( $fixtures as $name => $desc ) {
			$file = "{$dir}/data-fixture-{$name}.{$desc['version']}.css";
			if ( !is_readable( $file ) ) {
				array_map( 'unlink', glob( "{$dir}/data-fixture-{$name}.*" ) );
				$data = file_get_contents( $desc['src'] );
				if ( $data === false ) {
					throw new Exception( "Failed to fetch fixture: {$name}" );
				}
				file_put_contents( $file, $data );
			} else {
				$data = file_get_contents( $file );
			}
			$result[$name] = $data;
		}
		return $result;
	}
}
