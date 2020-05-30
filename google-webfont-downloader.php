#!/usr/bin/env php
<?php

$most_popular_n = 99999999;

$fonts = json_decode( shell_exec( 'curl https://google-webfonts-helper.herokuapp.com/api/fonts' ) );

$i = 1;

foreach ( $fonts as $font ) {

	$url = "https://google-webfonts-helper.herokuapp.com/api/fonts/{$font->id}?download=zip&subsets=latin&formats=woff,woff2";
	$url = escapeshellarg($url);

	$folder_name = get_folder_name( $font->id );

	echo $folder_name . PHP_EOL;

	shell_exec( "wget -N -q --output-document {$font->id}.zip $url" );
	shell_exec( "mkdir -p {$folder_name}" );
	shell_exec( "unzip -o {$font->id}.zip -d {$folder_name}" );
	shell_exec( "rm {$font->id}.zip" );

	if( $i > $most_popular_n ) {
		break;
	}
	$i++;
}

function get_folder_name( $font_id ) {
	$font_id_no_dash = str_replace('-', '', $font_id);

	foreach ( array( 'apache', 'ofl', 'ufl' ) as $value ) {

		if ( is_dir( getcwd() . "/{$value}/{$font_id_no_dash}" ) ) {
			$folder_name = "{$value}/{$font_id_no_dash}/webfonts";
			break;
		}
	}

	if ( empty( $folder_name ) ) {
		$folder_name = "undetected/{$font_id}";
	}

	return $folder_name;
}
