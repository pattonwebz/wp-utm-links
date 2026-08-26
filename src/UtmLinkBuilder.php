<?php
/**
 * UTM link builder.
 *
 * @package Pattonwebz\WPUtmLinks
 */

namespace Pattonwebz\WPUtmLinks;

/**
 * Builds links with a configurable, per-instance set of default query args
 * (UTM params, telemetry, or anything else) merged on top of one or more
 * named base URLs.
 *
 * The class itself has no opinion about what those defaults are — pro/free
 * status, activation-based "days active" counters, WordPress version, etc
 * are all things a specific plugin may want, not things every consumer of
 * this package wants. Configure them via `defaults`; a value can be a plain
 * scalar or a callable resolved lazily at build time (so it can read
 * constants/options that aren't available yet at construction time, and so
 * it reflects current state on every call rather than being frozen once).
 *
 * One instance is configured per plugin/project; the same instance can then
 * be reused to build any number of links for it.
 */
class UtmLinkBuilder {

	/**
	 * Default query args: key => scalar value, or key => callable resolved
	 * lazily at build time. Always included.
	 *
	 * @var array<string, mixed>
	 */
	protected $defaults;

	/**
	 * Same shape as $defaults, but only included when consent() is true.
	 * Intended for anything that amounts to collecting data about the
	 * install/user — telemetry, usage stats, environment info — that a
	 * plugin may need to let people opt out of.
	 *
	 * @var array<string, mixed>
	 */
	protected $consented_defaults;

	/**
	 * Whether $consented_defaults are allowed to be sent: a plain bool, or
	 * a callable resolved lazily at build time (e.g. reading a "usage
	 * tracking" option). Defaults to true — this class doesn't enforce an
	 * opt-out by itself, it just gives you the mechanism to wire one up.
	 *
	 * @var bool|callable
	 */
	protected $consent;

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
	 * Constructor.
	 *
	 * @param array $config {
	 *     Configuration for this builder instance. Everything is optional;
	 *     an empty config produces a builder that just appends whatever
	 *     $query_args you pass at call time.
	 *
	 *     @type array<string, mixed>  $defaults           Default query args, applied to every generated
	 *                                                      link before per-call $query_args are merged on
	 *                                                      top. Each value may be a scalar, or a callable
	 *                                                      (Closure, [$obj, 'method'], etc — plain strings
	 *                                                      are never treated as callable, so a value like
	 *                                                      'time' is used literally) resolved at build
	 *                                                      time. A callable returning null omits that key
	 *                                                      entirely.
	 *     @type array<string, mixed>  $consented_defaults Same shape as $defaults, but only merged in when
	 *                                                      `consent` resolves truthy. Use this for anything
	 *                                                      that amounts to collecting data about the site/
	 *                                                      user (telemetry, usage stats, environment info)
	 *                                                      so it can be switched off independently of the
	 *                                                      plain UTM params in `defaults`.
	 *     @type bool|callable         $consent            Whether `consented_defaults` are allowed to be
	 *                                                      sent, or a callable resolved at build time (e.g.
	 *                                                      reading a "usage tracking" option/filter).
	 *                                                      Default true.
	 *     @type array<string, string> $base_links         Named base links, e.g. [ 'default' => '...',
	 *                                                      'pro' => '...' ].
	 * }
	 */
	public function __construct( array $config = [] ) {
		$this->defaults           = isset( $config['defaults'] ) && is_array( $config['defaults'] ) ? $config['defaults'] : [];
		$this->consented_defaults = isset( $config['consented_defaults'] ) && is_array( $config['consented_defaults'] ) ? $config['consented_defaults'] : [];
		$this->consent            = $config['consent'] ?? true;
		$this->base_links         = isset( $config['base_links'] ) && is_array( $config['base_links'] ) ? $config['base_links'] : [];
	}

	/**
	 * Build a link from a URL, adding the configured default query args.
	 *
	 * If $url doesn't start with http(s), it's treated as relative and
	 * appended to the 'default' base link.
	 *
	 * @param string $url        Absolute or relative URL. Optional; if omitted, the
	 *                           'default' base link is used as-is.
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
	 * Resolve the configured `defaults` (and, if consent is given,
	 * `consented_defaults`) into a plain array.
	 *
	 * @return array<string, mixed>
	 */
	public function default_query_args() {
		$resolved = $this->resolve_map( $this->defaults );

		if ( $this->has_consent() ) {
			$resolved = array_merge( $resolved, $this->resolve_map( $this->consented_defaults ) );
		}

		return $resolved;
	}

	/**
	 * Whether `consented_defaults` are currently allowed to be sent.
	 *
	 * @return bool
	 */
	public function has_consent() {
		return (bool) $this->resolve( $this->consent );
	}

	/**
	 * Merge resolved defaults with caller overrides, then add them to the
	 * base URL as a query string.
	 *
	 * @param string $base_link  Base URL.
	 * @param array  $query_args Caller-supplied overrides.
	 *
	 * @return string
	 */
	protected function merge_and_apply( $base_link, array $query_args ) {
		$query_args = array_merge( $this->default_query_args(), $query_args );

		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( $query_args, $base_link );
		}

		$separator = ( false === strpos( $base_link, '?' ) ) ? '?' : '&';
		return $base_link . $separator . http_build_query( $query_args );
	}

	/**
	 * Resolve every value in a defaults-shaped map, dropping any key whose
	 * resolved value is null.
	 *
	 * @param array<string, mixed> $map A `defaults`- or `consented_defaults`-shaped map.
	 *
	 * @return array<string, mixed>
	 */
	protected function resolve_map( array $map ) {
		$resolved = [];

		foreach ( $map as $key => $value ) {
			$value = $this->resolve( $value );

			if ( null === $value ) {
				continue;
			}

			$resolved[ $key ] = $value;
		}

		return $resolved;
	}

	/**
	 * Resolve a single defaults value: call it if it's callable, otherwise
	 * return it as-is. Plain strings are never invoked, so a default like
	 * 'utm_campaign' => 'time' is used literally rather than calling time().
	 *
	 * @param mixed $value Raw value from the `defaults` config.
	 *
	 * @return mixed
	 */
	protected function resolve( $value ) {
		if ( $value instanceof \Closure ) {
			return call_user_func( $value );
		}

		if ( is_array( $value ) && is_callable( $value ) ) {
			return call_user_func( $value );
		}

		if ( is_object( $value ) && method_exists( $value, '__invoke' ) ) {
			return call_user_func( $value );
		}

		return $value;
	}
}
