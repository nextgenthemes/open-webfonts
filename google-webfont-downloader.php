#!/usr/bin/env php
<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_exec
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_instance
/**
 * @author  Nicolas Jonas <nextgenthemes.com>
 * @license GPL 3.0
*/
namespace Nextgenthemes\OpenWebfonts;

if ( 'cli' === php_sapi_name() ) {
	$url_or_num = empty( $argv[1] ) ? 'poop' : $argv[1];
} else {
	$url_or_num = empty( $_GET['googlefontsurl'] ) ? 'poop' : \urldecode( $_GET['googlefontsurl'] );
}

get_webfonts( $url_or_num );

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

	$i     = 1;
	$fonts = json_decode( download('https://google-webfonts-helper.herokuapp.com/api/fonts' ) );

	echo PHP_EOL . 'Font-count: ' . count( $fonts ) . PHP_EOL . PHP_EOL;

	foreach ( $fonts as $font ) :

		$family         = str_replace( ' ', '+', $font->family );
		$variants       = 'ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900';
		$google_css_url = "https://fonts.googleapis.com/css2?family=$family:$variants&display=swap";

		prepare_fonts( $google_css_url, 'cache fonts' );

		echo $i . PHP_EOL;

		if ( $i > $num ) {
			break;
		}
		$i++;

	endforeach;
}

function prepare_fonts( string $google_css_url, bool $storage = false ) {

	if ( ! contains($google_css_url, 'https://fonts.googleapis.com/css2') ) {

		echo 'You must enter Google Fonts CSS url like: https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,600;1,400&family=Shadows+Into+Light&family=Zilla+Slab:ital,wght@0,300;0,400;0,600;0,700;1,400;1,700&display=swap';
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

	$css           = download($google_css_url);
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
			echo maybe_basename( $font_file_absolute ) . ' exists already' . PHP_EOL;
		} else {

			if ( ! is_dir( dirname($font_file_absolute) ) ) {
				mkdir( dirname($font_file_absolute), 0700, true );
			}

			if ( is_file($font_file_stored) ) {
				copy( $font_file_stored, $font_file_absolute );
				echo maybe_basename( $font_file_absolute ) . ' copied from cache' . PHP_EOL;
			} else {
				$font_file_content = download($url);
				file_put_contents( $font_file_absolute, $font_file_content );
				echo maybe_basename( $font_file_absolute ) . ' downloaded' . PHP_EOL;
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

	echo maybe_basename( $font_css_file ) . ' saved' . PHP_EOL;

	if ( $zipfile && ! is_file( $zipfile ) ) {
		create_zip( $zipfile, $dir, $font_dirs );
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

	echo 'Saving ' . maybe_basename( $zipfile ) . '...';

	$zip = new ZipArchive();
	$ret = $zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	if ( true !== $ret ) {
		printf('Failed with code %d', $ret);
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

	echo 'done.' . PHP_EOL;

	if ( ! contains( $dir, 'webfonts' ) ) {
		recursive_delete( $dir );
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
	return 'cli' === php_sapi_name() ? $path : basename( $path );
}

function contains($haystack, $needle) {
	return false !== strpos($haystack, $needle);
}
