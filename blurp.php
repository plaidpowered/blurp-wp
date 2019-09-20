<?php
/*
Plugin Name: Blur Up!
Description: Make images terrible and then make them better
Author: Paul Houser
Author URI: https://oneupweb.com
Text Domain: blurp
Version: 1.0.10
*/

global $async_stack;

class Blurp {

	private $images;

	function __construct() {
		$this->$images = [];

		add_filter( 'the_content', array( $this, 'replace_images' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this , 'enqueue' ) );
		add_action( 'init', array( $this , 'add_tiny_size' ) );
	}

	function enqueue() {
		wp_enqueue_script( 'blurp', plugins_url( 'blurp-loader.js', __FILE__ ), array(), '1.0.10', true );
		wp_enqueue_style( 'blurp', plugins_url( 'blurp.css', __FILE__ ), array(), '1.0.10' );
	}

	function add_tiny_size() {
		add_image_size( 'tiny', 40, 0, false );
	}

	function replace_images( $content ) {

		$matches = preg_match_all(
			'/<img([^>]*?)class="([^"]*?)(wp-image-[0-9]*?)([^"]*?)"([^>]*?)>/ms',
			$content,
			$images,
			PREG_SET_ORDER
		);

		$replacements = [];

		foreach( $images as $tag ) {

			$img_id = $tag[4];
			$classes = [ 'blurp', 'preloaded-background' ];
			$attrs = [];

			$full_src = get_attached_file( $img_id );
			$ext = substr( $full_src, strrpos( $full_src, '.' ) + 1 );
			$filepath = substr( $full_src, 0, strrpos( $full_src, '/' ) );
			
			if ( ! in_array( $ext, [ 'gif', 'jpg', 'png', 'jpeg' ] ) ) {
				continue;
			}

			$tinyimg_src = image_get_intermediate_size( $img_id, 'tiny' );
			if ( ! $tinyimg_src ) {
				continue;
			}
			$path = $filepath . substr( $tinyimg_src['url'], strrpos( $tinyimg_src['url'], '/' ) );
			
			if ( ! file_exists( $path ) ) {
				continue;
			}
			
			$data = file_get_contents( $path );

			$src = preg_match( '/src="([^"]*?)"/', $tag[0], $fileurl );

			if ( preg_match( "/-([0-9]*?)x([0-9]*?)\\.$ext/", $fileurl[1], $size ) ) {
				$attrs['data-width']  = $size[1];
				$attrs['data-height'] = $size[2];
			} else {
				$info = wp_get_attachment_image_src( $img_id, 'full' );
				$attrs['data-width']  = $info[1];
				$attrs['data-height'] = $info[2];
			}

			$src_path = $filepath . substr( $fileurl[1], strrpos( $fileurl[1], '/' ) );

			if ( file_exists( $src_path . '.webp' ) ) {
				$classes[] = 'webp-ok';
			}

			$attrs['style'] = sprintf( 'background-image:url(%s);width:%s',
				'data:image/jpeg;base64,' . base64_encode($data),
				$attrs['data-width'] . 'px'
			);
			$newtag  = str_replace( '<img', '<div', $tag[0] );
			$newtag  = str_replace( '/>', '>', $newtag );

			$newtag = str_replace( 'class="', 'class="' . implode( ' ', $classes ) . ' ', $newtag );

			$newtag = str_replace( 'src=', 'data-src=', $newtag );
			$newtag = str_replace( 'srcset=', 'data-srcset=', $newtag );
			$newtag = str_replace( 'sizes=', 'data-sizes=', $newtag );

			$attrs_string = '';
			foreach ( $attrs as $key => $value ) {
				$attrs_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
			$newtag = str_replace( '<div', '<div' . $attrs_string, $newtag );

			$newtag .= sprintf( '<div class="blurp-spacer" style="padding-top:%s"></div>',
				round( 100 * $attrs['data-height'] / $attrs['data-width'], 2 ) . '%'
			);
			$newtag .= '</div>';

			$replacements[] = array(
				'old' => $tag[0],
				'new' => $newtag
			);

		}

		foreach ( $replacements as $img ) {
			$content = str_replace( $img['old'], $img['new'], $content );
		}

		return $content;

	}

}

$blurp = new Blurp();

/*

add_filter( 'the_content', 'replace_images_with_tinybg', 8 );

function replace_images_with_tinybg( $content ) {

	if ( function_exists( 'Oneupweb\base\is_amp' ) && Oneupweb\base\is_amp() ) {
		return $content;
	}

	$matches = preg_match_all(
		'/<!-- wp:(media-text|image) (.*?)-->(.*?)<!-- \/wp:\1 -->/ms',
		$content,
		$blocks,
		PREG_SET_ORDER
	);

	foreach ( $blocks as $block ) {

		$settings = trim( $block[2] );
		if ( ! empty( $settings ) ) {
			$settings = json_decode( $settings );
		} else {
			continue;
		}

		$img_id = $block[1] === 'media-text' ? $settings->mediaId : ($settings->id ?? false);
		if ( ! $img_id ) {
			continue;
		}

		if ( isset( $settings->className ) && strpos( $settings->className, 'no-preload' ) !== false ) {
			continue;
		}

		$full_src = null;
		$inside = $block[0];

		$tinyimg_src = image_get_intermediate_size( $img_id, 'tiny' );
		if ( ! $tinyimg_src ) {
			continue;
		}

		$tinyfile_name = WP_CONTENT_DIR . '/uploads/' . $tinyimg_src['path'];
		if ( ! file_exists( $tinyfile_name ) ) {
			continue;
		}

		if ( $block[1] === 'media-text' ) {
			$src = wp_get_attachment_image_src( $img_id, 'full' );
			$srcset = wp_get_attachment_image_srcset( $img_id, 'full' );
		} else {
			$src = wp_get_attachment_image_src( $img_id, $settings->sizeSlug ?? 'full' );
			$srcset = wp_get_attachment_image_srcset( $img_id, $settings->sizeSlug ?? 'full' );
		}

		if ( ! empty( $settings->sizeSlug ) ) {
			$full_src = image_get_intermediate_size( $img_id, $settings->sizeSlug );
		}
		if ( empty( $full_src ) ) {
			$full_src = get_attached_file( $img_id );
		} else {
			$full_src = WP_CONTENT_DIR . '/uploads/' . $full_src['path'];
		}

		$webp_ok = $full_src && file_exists( $full_src . '.webp' );

		if ( empty( $src[1] ) || empty( $src[2] ) ) {
			// probably an svg file
			continue;
		}



		$width  = $src[1];
		$height = $src[2];

		$ratio = round( $height / $width * 100, 2 );

		$data = file_get_contents( $tinyfile_name );

		preg_match( '/<figure class="([A-Za-z0-9-_\s]*?)"([^>]*)>(<img[^>]*>)<\/figure>/mi', $inside, $match );

		if ( empty( $match ) ) {
			continue;
		}

		$replacement  = sprintf( '<figure class="%s tinybg"%s>', $match[1], $match[2] );
		$replacement .= sprintf(
			'<div class="preloaded-background %s" style="background-image:url(data:image/jpeg;base64,%s);padding-top:%s;width:%s" data-src="%s" data-srcset="%s">',
			$webp_ok ? 'webp-ok' : 'no-webp',
			base64_encode( $data ),
			$ratio . '%',
			$block[1] === 'media-text' ? '100%' : $width . 'px',
			$src[0],
			$srcset ?: ''
		);

		//$replacement .= str_replace( [ 'img', 'src','srcset','sizes' ], [ 'span', 'data-src','data-srcset','data-sizes' ], $match[3] );
		$replacement .= '</div></figure>';

		$content = str_replace( $match[0], $replacement, $content );
	}

	return $content;

}
