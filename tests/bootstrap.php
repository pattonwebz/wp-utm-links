<?php
/**
 * Minimal WordPress function stubs so UtmLinkBuilder can be unit tested
 * without a full WordPress test environment.
 *
 * @package Pattonwebz\WPUtmLinks
 */

$GLOBALS['__wp_utm_links_test_filters'] = [];

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $separator . http_build_query( $args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value ) {
		if ( isset( $GLOBALS['__wp_utm_links_test_filters'][ $tag ] ) ) {
			return $GLOBALS['__wp_utm_links_test_filters'][ $tag ];
		}
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		return '6.5';
	}
}
