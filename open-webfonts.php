<?php declare(strict_types = 1);
/**
 * Plugin Name:       NGT Google Webfont Downloader
 * Plugin URI:        https://nextgenthemes.com/google-webfont-downloader/
 * Description:       .
 * Version:           0.5.0
 * Author:            Nicolas Jonas
 * Author URI:        https://nextgenthemes.com
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Nextgenthemes/GoogleWebfontDownloader
 * @author  Nicolas Jonas
 * @license GPL 3.0
 * @link    https://nextgenthemes.com
 */
namespace Nextgenthemes\OpenWebfonts;

use ZipArchive;

// phpcs:disable WordPress.WP.AlternativeFunctions

// php 7.4
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewNullableTypes
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations
// phpcs:disable PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations

// skip on WP-CLI
if ( defined( '\WP_CLI' ) && \WP_CLI ) {
	return;
}

// Only allow server php from WP, allow php CLI
if ( 'cli' !== php_sapi_name() && ! defined( 'ABSPATH' ) ) {
	exit;
}

new OpenWebfonts();

class OpenWebfonts {

	private \CurlHandle $curl;
	private bool $is_wp;
	private string $lines = '';

	public function __construct() {

		$this->curl  = curl_init();
		$this->is_wp = defined( 'ABSPATH' ) && 'cli' !== php_sapi_name();

		if ( $this->is_wp ) {

			add_action(
				'init',
				function (): void {
					add_shortcode( 'webfonts', [ $this, 'shortcode' ] );
				}
			);

		} else {

			global $argv;

			$url_or_int = empty( $argv[1] ) ? 'poop' : $argv[1];
			$this->get_webfonts( $url_or_int );
		}
	}

	public function shortcode(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- do I need this?
		$url_or_int = empty( $_POST['google_fonts_url'] ) ? 'poop' : \rawurldecode( $_POST['google_fonts_url'] );
		return $this->get_webfonts( $url_or_int );
	}

	private function get_webfonts( string $url_or_int ): string {

		$url_or_int = trim( $url_or_int );
		$html       = '';

		if ( ! $this->is_wp && is_numeric( $url_or_int ) ) {
			$this->most_popular_fonts( (int) $url_or_int );
		} else {
			$html = $this->prepare( $url_or_int );
		}

		curl_close($this->curl);

		return $html;
	}

	private function most_popular_fonts( int $num ): void {

		if ( $this->is_wp ) {
			return;
		}

		$i    = 1;
		$json = $this->download('https://gwfh.mranftl.com/api/fonts' );

		if ( ! $json ) {
			return;
		}

		$fonts = json_decode( $json );

		echo PHP_EOL . 'Font-count: ' . count( $fonts ) . PHP_EOL . PHP_EOL;

		foreach ( $fonts as $font ) :

			$family         = str_replace( ' ', '+', $font->family );
			$variants       = 'ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900';
			$google_css_url = "https://fonts.googleapis.com/css2?family=$family:$variants&display=swap";

			$this->prepare( $google_css_url, true );

			echo (int) $i . PHP_EOL;

			if ( $i > $num ) {
				break;
			}
			$i++;

		endforeach;
	}

	private function filename( string $google_css_url ): string {

		$filename = str_replace(
			[ 'https://fonts.googleapis.com/css2?', '&display=swap', 'family', 'wght', '/', 'ital' ],
			'',
			$google_css_url
		);
		$filename = strtolower( preg_replace('/[^a-z]/i', '', $filename ) );

		if ( $this->is_wp ) {
			$filename = \sanitize_file_name( $filename ); // probably not needed at all after preg_replace
		}

		return $filename;
	}

	private function prepare( string $google_css_url, bool $storage = false ) {

		$html = '';

		if ( ! str_starts_with($google_css_url, 'https://fonts.googleapis.com/css2') /* || false !== filter_var($google_css_url, FILTER_VALIDATE_URL) */ ) {

			if ( ! $this->is_wp ) {
				echo 'You must enter Google Fonts CSS url like: https://fonts.googleapis.com/css2?family=Open+Sans...&display=swap';
				return false;
			} else {
				ob_start();
				?>
				<form class="d-block alignfull" action="" method="post">
					<div class="mb-2">
						<label for="text">Google Fonts CSS URL <span id="textHelpBlock" class="form-text text-muted">Go to <a href="https://fonts.google.com">Google fonts</a> and select your fonts as normal, then copy the CSS URL for embedding here.</span></label>
					</div>
					<div class="grid gap-1 mb-3 gx-1">
						<div class="g-col-12 g-col-sm-10">
							<input id="google_fonts_url" name="google_fonts_url" placeholder="https://fonts.googleapis.com/css2?family=...display=swap" type="text" class="form-control" onClick="this.select();" aria-describedby="textHelpBlock">
						</div>
						<div class="g-col-12 g-col-sm-2">
							<button name="submit" type="submit" class="btn btn-primary">Submit</button>
						</div>
					</div>
				</form>
				<?php
				return ob_get_clean();
			}
		}

		$zip_url = $this->prepare_fonts( $google_css_url, $storage );

		if ( $this->is_wp ) {

			$lines = $this->print_line( '', true );

			if ( str_contains( $lines, 'Error' ) ) :
				$html .= '<pre class="alignfull"><code>' . "$lines</code></pre>"; // phpcs:ignore
			else :
				$html .= sprintf(
					'<a class="btn btn-lg btn-primary d-block w-100 mb-1" href="%s">Download Zipfile</a>',
					esc_url( $zip_url )
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

	private function prepare_fonts( string $google_css_url, bool $storage = false ): string {

		$filename = self::filename( $google_css_url );

		if ( strlen( $filename ) >= 242 ) { // ZipArchive creates a temporary file that adds more then 4 characters so not sure how many
			$filename = hash( 'sha512', $filename );
		}

		$cache_dir = __DIR__ . '/webfonts';
		$dir       = __DIR__ . "/zips/$filename";
		$zipfile   = __DIR__ . "/zips/$filename.zip";

		if ( $this->is_wp ) {
			$zipfile = WP_CONTENT_DIR . "/uploads/webfont-zips/$filename.zip";
			$zip_url = content_url() . '/uploads/webfont-zips/' . basename($zipfile);
		} elseif ( $storage ) {
			$dir     = $cache_dir;
			$zipfile = false;
		}

		$font_css_file     = "$dir/css/$filename.css";
		$font_css_file_b64 = "$dir/css/$filename-b64.css";
		$css               = $this->download($google_css_url);
		$css_b64           = $css;

		if ( ! $css ) {
			return '';
		}

		$this->print_line( "Downloaded $google_css_url" );

		# https://regex101.com/r/lC52yj/7
		$re = '%/* (?<variant>[^ ]+) \*/\n@font-face {\n  font-family: (?<family>[^;]+);\n  font-style: (?<style>[a-z]+);\n  font-weight: (?<weight>[0-9 ]+);[\n](  font-stretch: (?<stretch>[0-9]+(\%)?);\n)?  (font-display: swap;)?[\n].*url\((?<url>[^)]+/s/(?<id>[^/]+)/(?<version>[^/]+)/(?<uid>[^)]+))%mi';

		preg_match_all($re, $css, $matches, PREG_SET_ORDER, 0);

		foreach ( $matches as $key => $match ) {

			if ( empty( $match['variant'] ) ) {
				$this->print_line( 'variant not found' );
				return false;
			}

			$font_dirs[] = $match['id'] . '/' . $match['version'];
			$css_prep    = $this->css_prep_font_download(
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
		$this->print_line( $this->maybe_basename($font_css_file) . ' saved');

		file_put_contents( $font_css_file_b64, $css_b64 );
		$this->print_line( $this->maybe_basename($font_css_file_b64) . ' saved');

		if ( $zipfile ) {
			$zipfile = $this->create_zip( $zipfile, $dir, $font_dirs );

			if ( $zipfile && isset( $zip_url ) ) {
				return $zip_url;
			}
		}

		return '';
	}

	private function css_prep_font_download( array $match, string $css, string $css_b64, string $dir, string $cache_dir ): ?array {

		$id           = $match['id'];
		$uid          = $match['uid'];
		$variant      = $match['variant'];
		$style        = $match['style'];
		$weight       = $match['weight'];
		$version      = $match['version'];
		$url          = $match['url'];
		$license_file = $this->license_file($id, $cache_dir);

		$font_file_relative = "fonts/$id/$version/$uid";
		# possible            "fonts/$id/$variant-$style-$weight-$uid";
		$font_file_absolute = "$dir/$font_file_relative";
		$font_file_stored   = "$cache_dir/$font_file_relative";
		$license_file_dist  = "$dir/fonts/$id/$version/" . basename($license_file);

		if ( is_file($font_file_absolute) ) {
			$this->print_line( $this->maybe_basename($font_file_absolute) . ' exists already');
		} else {
			if ( ! is_dir( dirname($font_file_absolute) ) ) {
				mkdir( dirname($font_file_absolute), 0700, true );
			}

			if ( is_file($font_file_stored) ) {
				copy( $font_file_stored, $font_file_absolute );
				$this->print_line( $this->maybe_basename($font_file_absolute) . ' copied from cache');
			} else {
				$font_file_content = $this->download($url);

				if ( ! $font_file_content ) {
					return null;
				}

				file_put_contents( $font_file_absolute, $font_file_content );
				$this->print_line( $this->maybe_basename($font_file_absolute) . ' downloaded');
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

	private static function license_file( string $font_id, string $cache_dir ): string {

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

	private function download( string $url ): string {

		$ua_win = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36';
		$ua_mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.192 Safari/537.36';

		$ch = $this->curl;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, $ua_win);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
		$result      = curl_exec($ch);
		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( 200 !== $http_status ) {
			$this->print_line( "Error: HTTP status code: $http_status" );
			$this->print_line( "URL: $url" );
			return '';
		}

		return $result;
	}

	private function create_zip( string $zipfile, string $dir, array $font_dirs ): string {

		if ( ! is_dir( dirname($zipfile) ) ) {
			mkdir( dirname($zipfile), 0750, true );
		}

		$zip = new ZipArchive();
		$ret = $zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ( true !== $ret ) {
			$this->print_line("Failed with code $ret");
			$zipfile = '';
		} else {
			foreach ( $font_dirs as $font_dir ) {

				$options = array(
					'add_path'        => "fonts/$font_dir/",
					'remove_all_path' => true,
				);
				$zip->addGlob( "$dir/fonts/$font_dir" . '/*.{woff2,txt}', GLOB_BRACE, $options);
			}

			$options = array(
				'add_path'        => 'css/',
				'remove_all_path' => true,
			);
			$zip->addGlob( $dir . '/css/*.css', GLOB_BRACE, $options);
			$zip->close();
			$this->print_line( $this->maybe_basename( $zipfile ) . ' created' );
		}

		if ( ! str_contains( $dir, 'webfonts/webfonts' ) ) {
			self::recursive_delete( $dir );
		}

		return $zipfile;
	}

	private static function recursive_delete( string $dir ): void {
		if ( is_dir($dir) ) {
			$objects = scandir($dir);
			foreach ( $objects as $object ) :
				if ( '.' !== $object && '..' !== $object ) {
					if ( is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir . '/' . $object) ) {
						self::recursive_delete($dir . DIRECTORY_SEPARATOR . $object);
					} else {
						unlink($dir . DIRECTORY_SEPARATOR . $object);
					}
				}
			endforeach;
			rmdir($dir);
		}
	}

	private function maybe_basename( string $path ) {
		return ! $this->is_wp ? $path : esc_html( basename( $path ) );
	}

	private function print_line( string $line, bool $return = false ) {

		if ( $return ) {
			return $this->lines;
		}

		if ( ! $this->is_wp ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $line . PHP_EOL;
		} else {
			$this->lines .= esc_html( $line ) . '<br>';
		}
	}
}
