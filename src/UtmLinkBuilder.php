<?php
/**
 * UTM link builder.
 *
 * @package Pattonwebz\WPUtmLinks
 */

namespace Pattonwebz\WPUtmLinks;

use DateTime;

/**
 * Builds UTM-tagged links with a consistent set of default query args
 * (source/medium/campaign, platform info, pro/free status, days active, etc).
 *
 * One instance is configured per plugin via the constructor args; the same
 * instance can then be reused to build any number of links for that plugin.
 */
class UtmLinkBuilder {

	/**
	 * UTM source value, typically the plugin slug.
	 *
	 * @var string
	 */
	protected $utm_source;

	/**
	 * UTM medium value.
	 *
	 * @var string
	 */
	protected $utm_medium;

	/**
	 * UTM campaign value.
	 *
	 * @var string
	 */
	protected $utm_campaign;

	/**
	 * Name of the option that stores the plugin's activation date.
	 *
	 * Used to calculate `days_active`. Pass an empty string to omit
	 * `days_active` from generated links.
	 *
	 * @var string
	 */
	protected $activation_option;

	/**
	 * Whether the "pro" version of the plugin is active, or a callable
	 * that resolves to a bool. Evaluated lazily so it's safe to pass a
	 * closure that reads constants which may not be defined yet at
	 * construction time.
	 *
	 * @var bool|callable
	 */
	protected $is_pro;

	/**
	 * The software version string to report (already resolved by the
	 * caller, e.g. the pro version if pro is active, otherwise free).
	 *
	 * @var string
	 */
	protected $software_version;

	/**
	 * Named base links, e.g. [ 'default' => 'https://.../docs/', 'pro' => 'https://.../pricing/' ].
	 *
	 * The 'default' entry (if set) is also used to resolve relative URLs
	 * passed to build_link().
	 *
	 * @var array<string, string>
	 */
	protected $base_links;

	/**
	 * Name of a WordPress filter that can supply a one-off `ref` query
	 * arg. Pass an empty string to disable.
	 *
	 * @var string
	 */
	protected $ref_filter;

	/**
	 * Constructor.
	 *
	 * @param array $config {
	 *     Configuration for this builder instance.
	 *
	 *     @type string          $utm_source        Required. UTM source, typically the plugin slug.
	 *     @type string          $utm_medium        UTM medium. Default 'software'.
	 *     @type string          $utm_campaign      UTM campaign. Default 'wordpress-general'.
	 *     @type string          $activation_option Option name storing the activation date, used for
	 *                                               `days_active`. Default '' (days_active omitted).
	 *     @type bool|callable   $is_pro             Whether pro is active, or a callable returning bool.
	 *                                               Default false.
	 *     @type string          $software_version   Version string to report. Default ''.
	 *     @type array           $base_links         Named base links, e.g. [ 'default' => '...', 'pro' => '...' ].
	 *     @type string          $ref_filter         WordPress filter name used to fetch an optional `ref`
	 *                                               query arg. Default ''.
	 * }
	 */
	public function __construct( array $config = [] ) {
		$this->utm_source        = isset( $config['utm_source'] ) ? (string) $config['utm_source'] : '';
		$this->utm_medium        = isset( $config['utm_medium'] ) ? (string) $config['utm_medium'] : 'software';
		$this->utm_campaign      = isset( $config['utm_campaign'] ) ? (string) $config['utm_campaign'] : 'wordpress-general';
		$this->activation_option = isset( $config['activation_option'] ) ? (string) $config['activation_option'] : '';
		$this->is_pro            = $config['is_pro'] ?? false;
		$this->software_version  = isset( $config['software_version'] ) ? (string) $config['software_version'] : '';
		$this->base_links        = isset( $config['base_links'] ) && is_array( $config['base_links'] ) ? $config['base_links'] : [];
		$this->ref_filter        = isset( $config['ref_filter'] ) ? (string) $config['ref_filter'] : '';
	}

	/**
	 * Build a link from a URL, adding the standard UTM/telemetry query args.
	 *
	 * If $url is empty, the 'default' base link is used. If $url doesn't
	 * start with http(s), it's treated as relative and appended to the
	 * 'default' base link.
	 *
	 * @param string $url        Absolute or relative URL. Optional.
	 * @param array  $query_args Extra/override query args, e.g. [ 'utm_content' => 'sidebar' ].
	 *
	 * @return string
	 */
	public function build_link( $url = '', array $query_args = [] ) {
		$url = is_string( $url ) ? $url : '';

		if ( 0 !== strpos( $url, 'http' ) ) {
			$default_base = $this->base_links['default'] ?? '';
			$relative     = ltrim( $url, '/' );
			$url          = '' !== $default_base ? rtrim( $default_base, '/' ) . '/' . $relative : $url;
		}

		return $this->merge_and_apply( $url, $query_args );
	}

	/**
	 * Build a link by a named "type", looked up in the configured base_links.
	 *
	 * Mirrors a common pattern of generating links to a handful of known
	 * destinations (a pricing page, a help center, etc) by short name,
	 * with the option to override the base link entirely via
	 * `$args['base_link']` and to append a path segment via `$args['append']`.
	 *
	 * @param array  $query_args Extra/override query args.
	 * @param string $type       Key into the configured base_links. Falls back to 'default'
	 *                           if the type isn't found and no base_link override is given.
	 * @param array  $args       {
	 *     Optional.
	 *
	 *     @type string $base_link Overrides the base link entirely, ignoring $type.
	 *     @type string $append    Path segment appended to the resolved base link.
	 * }
	 *
	 * @return string
	 */
	public function build_type_link( array $query_args = [], $type = 'default', array $args = [] ) {
		if ( ! empty( $args['base_link'] ) && is_string( $args['base_link'] ) ) {
			$base_link = $args['base_link'];
		} elseif ( isset( $this->base_links[ $type ] ) ) {
			$base_link = $this->base_links[ $type ];
		} else {
			$base_link = $this->base_links['default'] ?? '';
		}

		if ( ! empty( $args['append'] ) && is_string( $args['append'] ) ) {
			$base_link = rtrim( $base_link, '/' ) . '/' . ltrim( $args['append'], '/' );
		}

		return $this->merge_and_apply( $base_link, $query_args );
	}

	/**
	 * The default query args applied to every generated link, before any
	 * caller-supplied $query_args are merged in on top.
	 *
	 * @return array<string, mixed>
	 */
	public function default_query_args() {
		$defaults = [
			'utm_source'       => $this->utm_source,
			'utm_medium'       => $this->utm_medium,
			'utm_campaign'     => $this->utm_campaign,
			'php_version'      => PHP_VERSION,
			'platform'         => 'wordpress',
			'platform_version' => $this->wp_version(),
			'software'         => $this->is_pro() ? 'pro' : 'free',
			'software_version' => $this->software_version,
		];

		if ( '' !== $this->activation_option ) {
			$defaults['days_active'] = $this->days_active();
		}

		return $defaults;
	}

	/**
	 * Merge defaults + overrides + an optional filtered `ref` arg, then
	 * add them to the base URL as a query string.
	 *
	 * @param string $base_link  Base URL.
	 * @param array  $query_args Caller-supplied overrides.
	 *
	 * @return string
	 */
	protected function merge_and_apply( $base_link, array $query_args ) {
		if ( '' !== $this->ref_filter && function_exists( 'apply_filters' ) ) {
			$ref = apply_filters( $this->ref_filter, '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHookName
			if ( ! empty( $ref ) && is_string( $ref ) ) {
				$query_args['ref'] = $ref;
			}
		}

		$query_args = array_merge( $this->default_query_args(), $query_args );

		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( $query_args, $base_link );
		}

		$separator = ( false === strpos( $base_link, '?' ) ) ? '?' : '&';
		return $base_link . $separator . http_build_query( $query_args );
	}

	/**
	 * Resolve whether pro is active.
	 *
	 * @return bool
	 */
	protected function is_pro() {
		if ( is_callable( $this->is_pro ) ) {
			return (bool) call_user_func( $this->is_pro );
		}

		return (bool) $this->is_pro;
	}

	/**
	 * Days since the configured activation option was set.
	 *
	 * @return int
	 */
	protected function days_active() {
		try {
			$now             = new DateTime( gmdate( 'Y-m-d H:i:s' ) );
			$activation_date = function_exists( 'get_option' )
				? get_option( $this->activation_option, gmdate( 'Y-m-d H:i:s' ) )
				: gmdate( 'Y-m-d H:i:s' );
			$activation      = new DateTime( $activation_date );

			return $now->diff( $activation )->days;
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Current WordPress version, if available.
	 *
	 * @return string
	 */
	protected function wp_version() {
		if ( function_exists( 'get_bloginfo' ) ) {
			return get_bloginfo( 'version' );
		}

		return isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '';
	}
}
