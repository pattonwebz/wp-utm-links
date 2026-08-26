<?php
/**
 * Tests for UtmLinkBuilder.
 *
 * @package Pattonwebz\WPUtmLinks
 */

namespace Pattonwebz\WPUtmLinks\Tests;

use PHPUnit\Framework\TestCase;
use Pattonwebz\WPUtmLinks\UtmLinkBuilder;

/**
 * @covers \Pattonwebz\WPUtmLinks\UtmLinkBuilder
 */
class UtmLinkBuilderTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	protected function parse_query( string $url ): array {
		$query = parse_url( $url, PHP_URL_QUERY ) ?? '';
		parse_str( $query, $args );
		return $args;
	}

	public function test_build_link_with_no_config_just_appends_query_args(): void {
		$builder = new UtmLinkBuilder();

		$url  = $builder->build_link( 'https://example.com/docs/', [ 'utm_source' => 'my-plugin' ] );
		$args = $this->parse_query( $url );

		$this->assertStringStartsWith( 'https://example.com/docs/', $url );
		$this->assertSame( 'my-plugin', $args['utm_source'] );
	}

	public function test_build_link_applies_static_defaults(): void {
		$builder = new UtmLinkBuilder(
			[
				'defaults' => [
					'utm_source'   => 'my-plugin',
					'utm_medium'   => 'software',
					'utm_campaign' => 'wordpress-general',
				],
			]
		);

		$url  = $builder->build_link( 'https://example.com/docs/' );
		$args = $this->parse_query( $url );

		$this->assertSame( 'my-plugin', $args['utm_source'] );
		$this->assertSame( 'software', $args['utm_medium'] );
		$this->assertSame( 'wordpress-general', $args['utm_campaign'] );
	}

	public function test_build_link_overrides_defaults(): void {
		$builder = new UtmLinkBuilder(
			[ 'defaults' => [ 'utm_campaign' => 'wordpress-general' ] ]
		);

		$url  = $builder->build_link( 'https://example.com/docs/', [ 'utm_campaign' => 'launch' ] );
		$args = $this->parse_query( $url );

		$this->assertSame( 'launch', $args['utm_campaign'] );
	}

	public function test_build_link_resolves_relative_url_against_default_base(): void {
		$builder = new UtmLinkBuilder(
			[ 'base_links' => [ 'default' => 'https://example.com/docs' ] ]
		);

		$url = $builder->build_link( 'getting-started' );

		$this->assertStringStartsWith( 'https://example.com/docs/getting-started', $url );
	}

	public function test_defaults_support_closures_resolved_at_build_time(): void {
		$state   = [ 'software' => 'free' ];
		$builder = new UtmLinkBuilder(
			[
				'defaults' => [
					'software' => static function () use ( &$state ) {
						return $state['software'];
					},
				],
			]
		);

		$args_before = $this->parse_query( $builder->build_link( 'https://example.com/' ) );
		$this->assertSame( 'free', $args_before['software'] );

		$state['software'] = 'pro';
		$args_after         = $this->parse_query( $builder->build_link( 'https://example.com/' ) );
		$this->assertSame( 'pro', $args_after['software'] );
	}

	public function test_defaults_plain_string_is_not_treated_as_callable(): void {
		$builder = new UtmLinkBuilder(
			[ 'defaults' => [ 'utm_campaign' => 'time' ] ]
		);

		$args = $this->parse_query( $builder->build_link( 'https://example.com/' ) );

		$this->assertSame( 'time', $args['utm_campaign'] );
	}

	public function test_defaults_callable_returning_null_omits_key(): void {
		$builder = new UtmLinkBuilder(
			[
				'defaults' => [
					'ref' => static function () {
						return null;
					},
				],
			]
		);

		$args = $this->parse_query( $builder->build_link( 'https://example.com/' ) );

		$this->assertArrayNotHasKey( 'ref', $args );
	}

	public function test_build_type_link_uses_named_base_link(): void {
		$builder = new UtmLinkBuilder(
			[
				'base_links' => [
					'pro'  => 'https://example.com/pricing/',
					'help' => 'https://example.com/help',
				],
			]
		);

		$url = $builder->build_type_link( [], 'pro' );

		$this->assertStringStartsWith( 'https://example.com/pricing/', $url );
	}

	public function test_build_type_link_append_arg(): void {
		$builder = new UtmLinkBuilder(
			[ 'base_links' => [ 'help' => 'https://example.com/help' ] ]
		);

		$url = $builder->build_type_link( [], 'help', [ 'append' => '/some-article' ] );

		$this->assertStringStartsWith( 'https://example.com/help/some-article', $url );
	}

	public function test_build_type_link_base_link_override(): void {
		$builder = new UtmLinkBuilder();

		$url = $builder->build_type_link( [], 'custom', [ 'base_link' => 'https://example.com/custom/' ] );

		$this->assertStringStartsWith( 'https://example.com/custom/', $url );
	}

	public function test_days_since_returns_whole_days(): void {
		$ten_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) );

		$this->assertSame( 10, UtmLinkBuilder::days_since( $ten_days_ago ) );
	}

	public function test_days_since_returns_null_for_empty_input(): void {
		$this->assertNull( UtmLinkBuilder::days_since( '' ) );
		$this->assertNull( UtmLinkBuilder::days_since( null ) );
	}

	public function test_days_since_wired_through_defaults(): void {
		$activation_date = gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) );

		$builder = new UtmLinkBuilder(
			[
				'defaults' => [
					'days_active' => static function () use ( $activation_date ) {
						return UtmLinkBuilder::days_since( $activation_date );
					},
				],
			]
		);

		$args = $this->parse_query( $builder->build_link( 'https://example.com/' ) );

		$this->assertSame( '3', $args['days_active'] );
	}
}
