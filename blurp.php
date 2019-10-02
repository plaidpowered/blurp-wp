<?php
/*
Plugin Name: Blur Up!
Description: Make images terrible and then make them better
Author: Paul Houser
Author URI: https://plaidpowered.com
Text Domain: blurp
Version: 1.0.20
*/

global $async_stack;

class Blurp {

	private $images;

	function __construct() {
		$this->images = [];

		add_filter( 'the_content', array( $this, 'replace_images' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this , 'enqueue' ) );
		add_action( 'init', array( $this , 'add_tiny_size' ) );
	}

	function enqueue() {
		wp_enqueue_script( 'blurp', plugins_url( 'blurp-loader.js', __FILE__ ), array(), '1.0.20', true );
		wp_enqueue_style( 'blurp', plugins_url( 'blurp.css', __FILE__ ), array(), '1.0.20' );
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

			if ( strpos( $tag[2], 'no-preload' ) !== false ) {
				continue;
			}

			$img_id = $tag[4];
			$classes = [ 'blurp', 'preloaded-background' ];
			$attrs = [];

			$full_src = get_attached_file( $img_id );
			$ext = substr( $full_src, strrpos( $full_src, '.' ) + 1 );
			$filepath = substr( $full_src, 0, strrpos( $full_src, '/' ) );

			if ( ! in_array( $ext, [ 'gif', 'jpg', 'png', 'jpeg' ] ) ) {
				continue;
			}

			$data = self::get_tinyimg_data( $img_id, $filepath );
			if ( empty( $data ) ) {
				continue;
			}

			

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

			$attrs['style'] = self::get_tinyimg_style( $data, $attrs );
			$newtag = self::make_tinyimg_tag( $tag[0], $classes, $attrs );

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

	static function get_tinyimg_data( $img_id, $folderpath ) {

		$tinyimg_src = image_get_intermediate_size( $img_id, 'tiny' );

		if ( ! $tinyimg_src ) {
			return false;
		}
		$path = $folderpath . substr( $tinyimg_src['url'], strrpos( $tinyimg_src['url'], '/' ) );

		if ( ! file_exists( $path ) ) {
			return false;
		}

		$data = file_get_contents( $path );

		return $data;

	}

	static function get_tinyimg_style( $data, $attrs ) {

		return sprintf( 'background-image:url(%s)',
			'data:image/jpeg;base64,' . base64_encode($data)
		);

	}

	static function make_tinyimg_tag( $original, $classes, $attrs ) {



		$newtag  = str_replace( '<img', '<div', $original );
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

		$newtag .= sprintf( '<div class="blurp-spacer" style="padding-top:%s;width:%s"></div>',
			round( 100 * $attrs['data-height'] / $attrs['data-width'], 2 ) . '%',
			$attrs['data-width'] . 'px'
		);
		$newtag .= '</div>';

		return $newtag;

	}

	static function blurry_thumbnail( $attachment_id, $size = 'thumbnail', $attrs = [] ) {

		$image = wp_get_attachment_image( $attachment_id, $size, false, $attrs );
		$image_src = wp_get_attachment_image_src( $attachment_id, $size );

		if ( empty( $image_src[1] ) ) {
			return $image;
		}

		unset( $attrs['class'] );
		$classes = [
			'blurp',
			'preloaded-background',
			'wp-image-' . $attachment_id,
		];

		$attrs['data-width'] = $image_src[1];
		$attrs['data-height'] = $image_src[2];

		$full_src = get_attached_file( $attachment_id );
		$filepath = substr( $full_src, 0, strrpos( $full_src, '/' ) );

		$data = self::get_tinyimg_data( $attachment_id, $filepath );
		if ( empty( $data ) ) {
			return $image;
		}
		$attrs['style'] = self::get_tinyimg_style( $data, $attrs );

		$thumbnail_path = $filepath . substr( $image_src[1], strrpos( $image_src[1], '/' ) );
		if ( file_exists( $thumbnail_path . '.webp' ) ) {
			$classes[] = 'webp-ok';
		}

		return self::make_tinyimg_tag( $image, $classes, $attrs );

		

	}

}

$blurp = new Blurp();
