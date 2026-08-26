<?php
/**
 * Minimal WordPress function stub so UtmLinkBuilder can be unit tested
 * without a full WordPress test environment.
 *
 * @package Pattonwebz\WPUtmLinks
 */

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $separator . http_build_query( $args );
	}
}
