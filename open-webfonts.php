<?php
namespace Nextgenthemes\OpenWebfonts;

use ZipArchive;
/**
 * @author  Nicolas Jonas <nextgenthemes.com>
 * @license GPL 3.0
*/
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_errno
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_exec
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_getinfo
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_instance
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- Any reason to?
// php 7.4
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewNullableTypes
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations

init();

function init() {

	if ( is_wp() ) {

		add_action(
			'init',
			function() {
				add_shortcode( 'webfonts', __NAMESPACE__ . '\shortcode' );
			}
		);

	} else {

		global $argv;

		$url_or_int = empty( $argv[1] ) ? 'poop' : $argv[1];
		get_webfonts( $url_or_int );
	}
}

function is_wp() {
	return ( defined( 'ABSPATH' ) || defined( 'WP_CLI' ) );
}

function shortcode( $args = [], $content = '' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- do I need this?
	$url_or_int = empty( $_POST['googlefontsurl'] ) ? 'poop' : \rawurldecode( $_POST['googlefontsurl'] );
	return get_webfonts( $url_or_int );
}

function get_webfonts( string $url_or_int ) {

	$url_or_int = trim( $url_or_int );
	$html       = '';

	if ( ! is_wp() && is_numeric( $url_or_int ) ) {
		most_popular_fonts( (int) $url_or_int );
	} else {
		$html = prepare( $url_or_int );
	}

	curl_close(curl_instance());

	return $html;
}

function most_popular_fonts( int $num ) {

	if ( 'cli' !== php_sapi_name() ) {
		return;
	}

	$i    = 1;
	$json = download('https://google-webfonts-helper.herokuapp.com/api/fonts' );

	if ( ! $json ) {
		return;
	}

	$fonts = json_decode( $json );

	echo PHP_EOL . 'Font-count: ' . count( $fonts ) . PHP_EOL . PHP_EOL;

	foreach ( $fonts as $font ) :

		$family         = str_replace( ' ', '+', $font->family );
		$variants       = 'ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900';
		$google_css_url = "https://fonts.googleapis.com/css2?family=$family:$variants&display=swap";

		prepare( $google_css_url, 'cache fonts' );

		echo (int) $i . PHP_EOL;

		if ( $i > $num ) {
			break;
		}
		$i++;

	endforeach;
}

function filename( string $google_css_url ): string {

	$filename = str_replace(
		[ 'https://fonts.googleapis.com/css2?', '&display=swap', 'family', 'wght', '/', 'ital' ],
		'',
		$google_css_url
	);
	$filename = strtolower( preg_replace('/[^a-z]/i', '', $filename ) );

	if ( 'cli' !== php_sapi_name() ) {
		$filename = sanitize_file_name( $filename ); // probably not needed at all after preg_replace
	}

	return $filename;
}

function prepare( string $google_css_url, bool $storage = false ) {

	$html = '';

	if ( ! starts_with($google_css_url, 'https://fonts.googleapis.com/css2') /* || false !== filter_var($google_css_url, FILTER_VALIDATE_URL) */ ) {

		if ( ! is_wp() ) {
			echo 'You must enter Google Fonts CSS url like: https://fonts.googleapis.com/css2?family=Open+Sans...&display=swap';
			return false;
		} else {
			ob_start();
			?>
			<form class="d-block alignfull" action="" method="post">
				<div class="row mb-2">
					<label for="text">Google Fonts CSS URL <span id="textHelpBlock" class="form-text text-muted">Go to <a href="https://fonts.google.com">Google fonts</a> and select your fonts as normal, then copy the CSS URL for embedding here.</span></label>
				</div>
				<div class="row mb-3 gx-1">
					<div class="col-12 col-sm-10">
						<input id="googlefontsurl" name="googlefontsurl" placeholder="https://fonts.googleapis.com/css2?family=...display=swap" type="text" class="form-control" onClick="this.select();" aria-describedby="textHelpBlock">
					</div>
					<div class="col-12 col-sm-auto">
						<button name="submit" type="submit" class="btn btn-primary">Submit</button>
					</div>
				</div>
			</form>
			<?php
			return ob_get_clean();
		}
	}

	$zipurl = prepare_fonts( $google_css_url, $storage );

	if ( 'cli' !== php_sapi_name() ) {

		$lines = print_line( '', 'get all saved lines' );

		if ( contains( $lines, 'Error' ) ) :
			$html .= '<pre class="alignfull"><code>' . "$lines</code></pre>"; // phpcs:ignore
		else :
			$html .= sprintf(
				'<a class="btn btn-lg btn-primary d-block w-100 mb-1" href="%s">Download Zipfile</a>',
				esc_url( $zipurl )
			);
		endif;

		$html .= '<input
			class="btn btn-secondary d-block w-100"
			action="action"
			onclick="window.history.go(-1); return false;"
			type="submit"
			value="Gimme more fonts!"
		/>';

		return $html;
	}
}

function prepare_fonts( string $google_css_url, bool $storage = false ): string {

	$filename = filename( $google_css_url );

	if ( strlen( $filename ) >= 242 ) { // ZipArchive creates a temorary file that adds more then 4 characters so not sure how many
		$filename = hash( 'sha512', $filename );
	}

	$cache_dir = __DIR__ . '/webfonts';
	$dir       = __DIR__ . "/zips/$filename";
	$zipfile   = __DIR__ . "/zips/$filename.zip";

	if ( $storage && ! is_wp() ) {
		$dir     = $cache_dir;
		$zipfile = false;
	} elseif ( 'cli' !== php_sapi_name() ) {
		$zipfile = WP_CONTENT_DIR . "/uploads/webfont-zips/$filename.zip";
		$zipurl  = content_url() . '/uploads/webfont-zips/' . basename($zipfile);
	}

	$font_css_file     = "$dir/css/$filename.css";
	$font_css_file_b64 = "$dir/css/$filename-b64.css";
	$css               = download($google_css_url);
	$css_b64           = $css;

	if ( ! $css ) {
		return '';
	}

	print_line( "Downloded $google_css_url" );

	# https://regex101.com/r/lC52yj/6
	$re = '%/* (?<variant>[^ ]+) \*/\n@font-face {\n  font-family: (?<family>[^;]+);\n  font-style: (?<style>[a-z]+);\n  font-weight: (?<weight>[0-9]+);[\n](  font-stretch: (?<stretch>[0-9]+(\%)?);\n)?  (font-display: swap;)?[\n].*url\((?<url>[^)]+/s/(?<id>[^/]+)/(?<version>[^/]+)/(?<uid>[^)]+))%mi';

	preg_match_all($re, $css, $matches, PREG_SET_ORDER, 0);

	foreach ($matches as $key => $match) {

		if ( empty( $match['variant'] ) ) {
			print_line( 'variant not found' );
			return false;
		}

		$font_dirs[] = $match['id'] . '/' . $match['version'];
		$css_prep    = css_prep_font_download(
			$match,
			$css,
			$css_b64,
			$dir,
			$cache_dir
		);

		if ( ! $css_prep ) {
			return '';
		}

		$css     = $css_prep['url'];
		$css_b64 = $css_prep['b64'];
	}

	if ( ! is_dir( dirname($font_css_file) ) ) {
		mkdir( dirname($font_css_file), 0700, true );
	}

	file_put_contents( $font_css_file, $css );
	print_line( maybe_basename($font_css_file) . ' saved');

	file_put_contents( $font_css_file_b64, $css_b64 );
	print_line( maybe_basename($font_css_file_b64) . ' saved');

	if ( $zipfile ) {
		$zipfile = create_zip( $zipfile, $dir, $font_dirs );

		if ( $zipfile && isset( $zipurl ) ) {
			return $zipurl;
		}
	}

	return '';
}

function css_prep_font_download( array $match, string $css, string $css_b64, string $dir, string $cache_dir ): ?array {

	$id           = $match['id'];
	$uid          = $match['uid'];
	$variant      = $match['variant'];
	$style        = $match['style'];
	$weight       = $match['weight'];
	$version      = $match['version'];
	$url          = $match['url'];
	$license_file = license_file($id, $cache_dir);

	$font_file_relative = "fonts/$id/$version/$uid";
	# possible            "fonts/$id/$variant-$style-$weight-$uid";
	$font_file_absolute = "$dir/$font_file_relative";
	$font_file_stored   = "$cache_dir/$font_file_relative";
	$license_file_dist  = "$dir/fonts/$id/$version/" . basename($license_file);

	if ( is_file($font_file_absolute) ) {
		print_line( maybe_basename($font_file_absolute) . ' exists already');
	} else {
		if ( ! is_dir( dirname($font_file_absolute) ) ) {
			mkdir( dirname($font_file_absolute), 0700, true );
		}

		if ( is_file($font_file_stored) ) {
			copy( $font_file_stored, $font_file_absolute );
			print_line( maybe_basename($font_file_absolute) . ' copied from cache');
		} else {
			$font_file_content = download($url);

			if ( ! $font_file_content ) {
				return null;
			}

			file_put_contents( $font_file_absolute, $font_file_content );
			print_line( maybe_basename($font_file_absolute) . ' downloaded');
		}
	}

	$license_src_is_target = ( is_file($license_file_dist) && $license_file_dist === $license_file ) ? true : false;

	if ( is_file($license_file) && ! $license_src_is_target ) {
		copy( $license_file, $license_file_dist );
	}

	// phpcs:ignore
	$b64 = base64_encode( file_get_contents( $font_file_absolute ) );

	return [
		'url' => str_replace( $url, '../' . $font_file_relative, $css ),
		'b64' => str_replace( $url, 'data:font/woff2;charset=utf-8;base64,' . $b64, $css_b64 ),
	];
}

function license_file( string $font_id, string $cache_dir ): string {

	foreach ( [ 'apache', 'ofl', 'ufl', 'missing-licenses' ] as $license ) :

		foreach ( [ 'LICENSE.txt', 'LICENCE.txt', 'OFL.txt', 'UFL.txt' ] as $file ) {

			$file = __DIR__ . "/$license/$font_id/$file";

			if ( is_file($file) ) {
				return $file;
			}
		}

	endforeach;

	foreach ( [ 'LICENSE.txt', 'LICENCE.txt', 'OFL.txt', 'UFL.txt' ] as $file ) {

		$file = "$cache_dir/$font_id/$file";

		if ( is_file($file) ) {
			return $file;
		}
	}

	return __DIR__ . '/missing-licenses/UNKNOWN-LICENSE.txt';
}

function curl_instance() {

	static $curl = null;

	if ( null === $curl ) {
		$curl = curl_init();
	}

	return $curl;
}

function download( string $url ): string {

	$ua_win = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36';
	$ua_mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.192 Safari/537.36';

	$ch = curl_instance();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, $ua_win);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
	$result      = curl_exec($ch);
	$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	if ( 200 !== $http_status ) {
		print_line( "Error: HTTP status code: $http_status" );
		return '';
	}

	return $result;
}

function create_zip( string $zipfile, string $dir, array $font_dirs ): string {

	if ( ! is_dir( dirname($zipfile) ) ) {
		mkdir( dirname($zipfile), 0750, true );
	}

	$zip = new ZipArchive();
	$ret = $zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	if ( true !== $ret ) {
		print_line("Failed with code $ret");
		$zipfile = '';
	} else {
		foreach ( $font_dirs as $fontdir ) {

			$options = array(
				'add_path'        => "fonts/$fontdir/",
				'remove_all_path' => true,
			);
			$zip->addGlob( "$dir/fonts/$fontdir" . '/*.{woff2,txt}', GLOB_BRACE, $options);
		}

		$options = array(
			'add_path'        => 'css/',
			'remove_all_path' => true,
		);
		$zip->addGlob( $dir . '/css/*.css', GLOB_BRACE, $options);
		$zip->close();
		print_line( maybe_basename( $zipfile ) . ' created' );
	}

	if ( ! contains( $dir, 'webfonts/webfonts' ) ) {
		recursive_delete( $dir );
	}

	return $zipfile;
}

function recursive_delete( string $dir ): void {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) :
			if ( '.' !== $object && '..' !== $object ) {
				if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir . '/' . $object)) {
					recursive_delete($dir . DIRECTORY_SEPARATOR . $object);
				} else {
					unlink($dir . DIRECTORY_SEPARATOR . $object);
				}
			}
		endforeach;
		rmdir($dir);
	}
}

function maybe_basename( $path ) {
	return ! is_wp() ? $path : esc_html( basename( $path ) );
}

function contains($haystack, $needle) {
	return false !== strpos($haystack, $needle);
}

function starts_with( $haystack, $needle ) {
	return $haystack[0] === $needle[0] ? strncmp( $haystack, $needle, strlen( $needle ) ) === 0 : false;
}

function print_line( string $line, bool $return = false ) {

	static $lines = '';

	if ( $return ) {
		return $lines;
	}

	if ( ! is_wp() ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $line . PHP_EOL;
	} else {
		$lines .= esc_html( $line ) . '<br>';
	}
}
