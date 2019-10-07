<?php
/**
 * Blurp
 *
 * @package Blurp
 *
 * @wordpress-plugin
 * Plugin Name: Blur Up!
 * Description: Make images terrible and then make them better
 * Author:      Paul Houser
 * Author URI:  https://plaidpowered.com
 * Text Domain: blurp
 * Version:     1.0.22
 */

require_once 'class-blurp.php';

add_action( 'after_setup_theme', array( new Blurp(), 'setup' ) );
