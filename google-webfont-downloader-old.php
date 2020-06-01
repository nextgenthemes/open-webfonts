#!/usr/bin/env php
<?php

define( 'MOST_POPULAR', 66 );

get_webfonts();

function get_webfonts() {

	$fonts = json_decode( shell_exec( 'curl https://google-webfonts-helper.herokuapp.com/api/fonts' ) );

	echo PHP_EOL . 'Font-count: ' . count( $fonts ) . PHP_EOL . PHP_EOL;

	$download_dir = 'downloads';

	if ( ! file_exists($download_dir)) {
		mkdir($download_dir, 0700);
	}

	$i = 1;

	foreach ( $fonts as $font ) :

		foreach ( $font->subsets as $subset ) :

			$url = "https://google-webfonts-helper.herokuapp.com/api/fonts/{$font->id}?download=zip&subsets={$subset}&formats=woff2";
			$url = escapeshellarg($url);

			$font_id       = str_replace('-', '', $font->id);
			$license       = detect_license( $font_id );
			$license_txt   = getcwd() . "/$license/$font_id/LICENSE.txt";
			$ofl_txt       = getcwd() . "/$license/$font_id/OFL.txt";
			$webfont_dir   = getcwd() . "/webfonts/$license/$font_id/$subset";
			$download_file = getcwd() . "/downloads/$font_id-$subset.zip";

			echo $webfont_dir . PHP_EOL;

			if ( is_file("$webfont_dir/LICENSE.txt") || is_file("$webfont_dir/OFL.txt") ) {
				echo 'already ready' . PHP_EOL . PHP_EOL;
				continue;
			}
			if ( ! file_exists($webfont_dir)) {
				mkdir($webfont_dir, 0700, true);
			}
			if ( ! file_exists($download_file)) {
				echo shell_exec( "wget -N -q --output-document $download_file $url" );
			}

			$zip = new ZipArchive();
			$res = $zip->open($download_file);
			if (true === $res) {
				$zip->extractTo($webfont_dir);
				$zip->close();
			} else {

				echo "Doh! I couldn't open $download_file" . PHP_EOL . PHP_EOL;

				if ( file_exists($download_file)) {
					echo "$download_file exists";
					unlink($download_file);
				} else {
					echo "$download_file does not exists";
				}
				continue;
			}

			if ( is_file( $license_txt ) ) {
				copy( $license_txt, "$webfont_dir/LICENSE.txt" );
			}
			if ( is_file( $ofl_txt ) ) {
				copy( $ofl_txt, "$webfont_dir/OFL.txt" );
			}

		endforeach;

		if ( $i > MOST_POPULAR ) {
			break;
		}
		$i++;

	endforeach;
}

function detect_license( $font_id ) {

	foreach ( array( 'apache', 'ofl', 'ufl' ) as $license ) {

		if ( is_dir( getcwd() . "/{$license}/{$font_id}" ) ) {
			return $license;
		}
	}

	return 'undetected';
}
