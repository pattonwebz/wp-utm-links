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

	public function test_build_link_applies_defaults(): void {
		$builder = new UtmLinkBuilder(
			[
				'utm_source'       => 'my-plugin',
				'software_version' => '1.2.3',
			]
		);

		$url  = $builder->build_link( 'https://example.com/docs/' );
		$args = $this->parse_query( $url );

		$this->assertStringStartsWith( 'https://example.com/docs/', $url );
		$this->assertSame( 'my-plugin', $args['utm_source'] );
		$this->assertSame( 'software', $args['utm_medium'] );
		$this->assertSame( 'wordpress-general', $args['utm_campaign'] );
		$this->assertSame( 'free', $args['software'] );
		$this->assertSame( '1.2.3', $args['software_version'] );
		$this->assertArrayNotHasKey( 'days_active', $args, 'days_active is omitted when no activation_option is configured.' );
	}

	public function test_build_link_overrides_defaults(): void {
		$builder = new UtmLinkBuilder( [ 'utm_source' => 'my-plugin' ] );

		$url  = $builder->build_link( 'https://example.com/docs/', [ 'utm_campaign' => 'launch' ] );
		$args = $this->parse_query( $url );

		$this->assertSame( 'launch', $args['utm_campaign'] );
	}

	public function test_build_link_resolves_relative_url_against_default_base(): void {
		$builder = new UtmLinkBuilder(
			[
				'utm_source' => 'my-plugin',
				'base_links' => [ 'default' => 'https://example.com/docs' ],
			]
		);

		$url = $builder->build_link( 'getting-started' );

		$this->assertStringStartsWith( 'https://example.com/docs/getting-started', $url );
	}

	public function test_is_pro_accepts_callable(): void {
		$builder = new UtmLinkBuilder(
			[
				'utm_source' => 'my-plugin',
				'is_pro'     => static function () {
					return true;
				},
			]
		);

		$args = $this->parse_query( $builder->build_link( 'https://example.com/' ) );

		$this->assertSame( 'pro', $args['software'] );
	}

	public function test_build_type_link_uses_named_base_link(): void {
		$builder = new UtmLinkBuilder(
			[
				'utm_source' => 'my-plugin',
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
			[
				'utm_source' => 'my-plugin',
				'base_links' => [ 'help' => 'https://example.com/help' ],
			]
		);

		$url = $builder->build_type_link( [], 'help', [ 'append' => '/some-article' ] );

		$this->assertStringStartsWith( 'https://example.com/help/some-article', $url );
	}

	public function test_build_type_link_base_link_override(): void {
		$builder = new UtmLinkBuilder( [ 'utm_source' => 'my-plugin' ] );

		$url = $builder->build_type_link( [], 'custom', [ 'base_link' => 'https://example.com/custom/' ] );

		$this->assertStringStartsWith( 'https://example.com/custom/', $url );
	}

	public function test_ref_filter_adds_ref_arg(): void {
		\Pattonwebz\WPUtmLinks\Tests\set_next_filter_value( 'my_plugin_ref', 'newsletter' );

		$builder = new UtmLinkBuilder(
			[
				'utm_source' => 'my-plugin',
				'ref_filter' => 'my_plugin_ref',
			]
		);

		$args = $this->parse_query( $builder->build_link( 'https://example.com/' ) );

		$this->assertSame( 'newsletter', $args['ref'] );
	}
}

/**
 * Tiny filter registry so test_ref_filter_adds_ref_arg can control the
 * stubbed apply_filters() return value without a real WP hooks system.
 */
function set_next_filter_value( string $tag, $value ): void {
	$GLOBALS['__wp_utm_links_test_filters'][ $tag ] = $value;
}
