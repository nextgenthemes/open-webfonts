<?php
namespace Nextgenthemes\OpenWebfonts;

use ZipArchive;
/**
 * @author  Nicolas Jonas <nextgenthemes.com>
 * @license GPL 3.0
*/
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_exec
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_instance
if ( 'cli' === php_sapi_name() ) {
	$url_or_num = empty( $argv[1] ) ? 'poop' : $argv[1];
} else {

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- no need WordPress.Security.NonceVerification.Recommended 
	$url_or_num = empty( $_POST['googlefontsurl'] ) ? 'poop' : \rawurldecode( $_POST['googlefontsurl'] );
}

if ( 'cli' === php_sapi_name() ) {
	get_webfonts( $url_or_num );
} else {
	get_webfonts( $url_or_num );
}

function get_webfonts( string $url_or_num ) {

	$url_or_num = trim( $url_or_num );

	if ( is_numeric( $url_or_num ) && 'cli' === php_sapi_name() ) {
		most_popular_fonts( (int) $url_or_num );
	} else {
		prepare_fonts( $url_or_num );
	}

	curl_close(curl_instance());
}

function most_popular_fonts( int $num ) {

	if ( 'cli' !== php_sapi_name() ) {
		return;
	}

	$i     = 1;
	$fonts = json_decode( download('https://google-webfonts-helper.herokuapp.com/api/fonts' ) );

	echo PHP_EOL . 'Font-count: ' . count( $fonts ) . PHP_EOL . PHP_EOL;

	foreach ( $fonts as $font ) :

		$family         = str_replace( ' ', '+', $font->family );
		$variants       = 'ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900';
		$google_css_url = "https://fonts.googleapis.com/css2?family=$family:$variants&display=swap";

		prepare_fonts( $google_css_url, 'cache fonts' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- only cli, just a number
		echo $i . PHP_EOL;

		if ( $i > $num ) {
			break;
		}
		$i++;

	endforeach;
}

function prepare_fonts( string $google_css_url, bool $storage = false ) {

	if ( ! starts_with($google_css_url, 'https://fonts.googleapis.com/css2') /* || false !== filter_var($google_css_url, FILTER_VALIDATE_URL) */ ) {

		if ( 'cli' === php_sapi_name() ) {
			echo 'You must enter Google Fonts CSS url like: https://fonts.googleapis.com/css2?family=Open+Sans...&display=swap';
		} else {
			?>
			<form action="" method="post">
				<div class="form-group">
					<label for="text">Google Fonts CSS URL</label> 
					<input id="googlefontsurl" name="googlefontsurl" placeholder="https://fonts.googleapis.com/css2?family=...display=swap" type="text" class="form-control" aria-describedby="textHelpBlock"> 
					<span id="textHelpBlock" class="form-text text-muted">Go to Google fonts and select your fonts as normal, then copy the CSS URL for embedding here.</span>
				</div>
				<div class="form-group">
					<button name="submit" type="submit" class="btn btn-primary">Submit</button>
				</div>
			</form>
			<?php
		}

		return;
	}

	if ( $storage ) {
		$dir     = __DIR__ . '/webfonts';
		$zipfile = false;
	} else {
		$dirname = str_replace(
			[ 'https://fonts.googleapis.com/css2?', '&display=swap', '/' ],
			'',
			$google_css_url
		);

		$dir     = __DIR__ . "/zips/$dirname";
		$zipfile = __DIR__ . "/zips/$dirname.zip";
	}

	print_line( "Downloding: $google_css_url" );
	$css = download($google_css_url);

	$font_css_file = str_replace(
		'https://fonts.googleapis.com/css2?',
		"$dir/css/",
		$google_css_url
	) . '.css';
	$font_css_file = str_replace( '&display=swap', '', $font_css_file );

	# https://regex101.com/r/lC52yj/5/
	$re = '%/* (?<variant>[^ ]+) \*/\n@font-face {\n  font-family: (?<family>[^;]+);\n  font-style: (?<style>[a-z]+);\n  font-weight: (?<weight>[0-9]+);[\n]  (font-display: swap;)?[\n].*url\((?<url>[^)]+/s/(?<id>[^/]+)/(?<version>[^/]+)/(?<uid>[^)]+))%mi';

	preg_match_all($re, $css, $matches, PREG_SET_ORDER, 0);

	foreach ($matches as $key => $match) {

		if ( empty( $match['variant'] ) ) {
			echo 'variant not found';
			return;
		}

		$id           = $match['id'];
		$uid          = $match['uid'];
		$variant      = $match['variant'];
		$style        = $match['style'];
		$weight       = $match['weight'];
		$version      = $match['version'];
		$url          = $match['url'];
		$license_file = license_file($id);
		$font_dirs[]  = "$id/$version";

		$font_file_relative = "fonts/$id/$version/$uid";
		# possible            "fonts/$id/$variant-$style-$weight-$uid";
		$font_file_absolute = "$dir/$font_file_relative";
		$font_file_stored   = __DIR__ . "/webfonts/$font_file_relative";
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
				file_put_contents( $font_file_absolute, $font_file_content );
				print_line( maybe_basename($font_file_absolute) . ' downloaded');
			}
		}

		if ( is_file($license_file) && ! is_file($license_file_dist) ) {
			copy( $license_file, $license_file_dist );
		}

		$css = str_replace( $url, '../' . $font_file_relative, $css );
	}

	if ( ! is_dir( dirname($font_css_file) ) ) {
		mkdir( dirname($font_css_file), 0700, true );
	}

	file_put_contents( $font_css_file, $css );

	print_line( maybe_basename($font_css_file) . ' saved');

	if ( $zipfile && ! is_file( $zipfile ) ) {
		create_zip( $zipfile, $dir, $font_dirs );
	}

	if ( contains( $dir, 'zips/family' ) ) {
		recursive_delete( $dir );
	}

	if ( 'cli' !== php_sapi_name() ) {
		$url = content_url() . '/uploads/open-webfonts/zips/' . basename($zipfile);
		printf( '<a class="btn btn-xl btn-primary" href="%s">Download Zipfile</a>', esc_url( $url ) );
	} else {
		print_line( "Zipfile at: $zipfile" );
	}
}

function license_file( string $font_id ) {

	foreach ( [ 'apache', 'ofl', 'ufl' ] as $license ) :

		foreach ( [ 'LICENSE.txt', 'OFL.txt' ] as $file ) {

			$file = __DIR__ . "/$license/$font_id/$file";

			if ( is_file($file) ) {
				return $file;
			}
		}

	endforeach;

	return __DIR__ . '/UNKNOWN-LICENSE.txt';
}

function curl_instance() {

	static $curl = null;

	if ( null === $curl ) {
		$curl = curl_init();
	}

	return $curl;
}

function download( string $url ) {
	$curl = curl_instance();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36');
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
	$result = curl_exec($curl);
	return $result;
}

function create_zip( string $zipfile, string $dir, array $font_dirs ) {

	$zip = new ZipArchive();
	$ret = $zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	if ( true !== $ret ) {
		print_line("Failed with code $ret");
		return;
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
	}
}

function recursive_delete($dir) {
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
	return 'cli' === php_sapi_name() ? $path : esc_html( basename( $path ) );
}

function contains($haystack, $needle) {
	return false !== strpos($haystack, $needle);
}
function starts_with( $haystack, $needle ) {
	return $haystack[0] === $needle[0] ? strncmp( $haystack, $needle, strlen( $needle ) ) === 0 : false;
}
function print_line( $line ) {

	if ( 'cli' === php_sapi_name() ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $line . PHP_EOL;
	} else {
		echo esc_html( $line ) . '<br>';
	}
}
