# wp-utm-links

A small, generic helper for building UTM-tagged (or any other query-string
tagged) links from one or more named base URLs. Extracted from duplicated
code in `accessibility-checker`, `accessibility-checker-pro`, and
`archivewp`.

The class has no built-in opinion about *what* your default query args are —
no hardcoded `utm_source`/`software`/`days_active` keys, no WordPress-plugin
assumptions baked in. Everything is driven by config, so it's reusable
outside of that project family too. Anything specific to how a particular
plugin works (a pro/free check, a "days active" counter, reading a `ref`
value off a filter) is wired in by that plugin via `defaults`, not by this
package.

## Install

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/pattonwebz/wp-utm-links"
        }
    ],
    "require": {
        "pattonwebz/wp-utm-links": "^2.0"
    }
}
```

## Usage

Configure one instance per plugin/project, then reuse it anywhere you need
a link.

```php
use Pattonwebz\WPUtmLinks\UtmLinkBuilder;

$links = new UtmLinkBuilder(
    [
        'base_links' => [
            'default' => 'https://example.com/my-plugin/docs/',
            'pro'     => 'https://example.com/my-plugin/pricing/',
            'help'    => 'https://example.com/my-plugin/help/',
        ],
        'defaults' => [
            // Plain scalars are used as-is.
            'utm_source'       => 'my-plugin',
            'utm_medium'       => 'software',
            'utm_campaign'     => 'wordpress-general',
            'php_version'      => PHP_VERSION,
            'software_version' => defined( 'MY_PLUGIN_PRO_VERSION' ) ? MY_PLUGIN_PRO_VERSION : MY_PLUGIN_VERSION,

            // Callables are resolved lazily at build time, so they can read
            // state (constants, options) that isn't available yet when the
            // builder is constructed, and stay current across calls.
            'platform_version' => static function () {
                return get_bloginfo( 'version' );
            },
            'software' => static function () {
                return ( defined( 'MY_PLUGIN_PRO_KEY_VALID' ) && MY_PLUGIN_PRO_KEY_VALID ) ? 'pro' : 'free';
            },

            // A plugin-specific "days since activation" telemetry value —
            // opt in via the days_since() utility, nothing automatic here.
            'days_active' => static function () {
                return UtmLinkBuilder::days_since( get_option( 'my_plugin_activation_date' ) );
            },

            // Returning null from a callable omits that key entirely, e.g.
            // only add `ref` when a filter actually supplies one.
            'ref' => static function () {
                $ref = apply_filters( 'my_plugin_filter_link_ref', '' );
                return '' !== $ref ? $ref : null;
            },
        ],
    ]
);

// Absolute URL, or relative to base_links['default'].
$links->build_link( 'getting-started', [ 'utm_content' => 'sidebar' ] );

// Named base link (base_links['pro']), with overrides.
$links->build_type_link( [ 'utm_campaign' => 'upgrade-nudge' ], 'pro' );

// Named base link with a path segment appended.
$links->build_type_link( [], 'help', [ 'append' => 'some-article' ] );

// One-off base link, bypassing base_links entirely.
$links->build_type_link( [], 'custom', [ 'base_link' => 'https://example.com/one-off/' ] );
```

`defaults` is entirely optional — with none configured, `build_link()` and
`build_type_link()` just resolve a base URL and append whatever
`$query_args` you pass at call time.

## API

- `build_link( string $url = '', array $query_args = [] ): string` — absolute
  URLs are used as given; anything else is treated as relative to
  `base_links['default']`.
- `build_type_link( array $query_args = [], string $type = 'default', array $args = [] ): string`
  — looks up `$type` in `base_links` (falling back to `'default'`);
  `$args['base_link']` overrides the base link entirely, `$args['append']`
  appends a path segment.
- `default_query_args(): array` — the configured defaults, resolved. Useful
  if you want the raw array rather than a built URL.
- `UtmLinkBuilder::days_since( ?string $date ): ?int` — static utility,
  whole days elapsed since `$date` (a `DateTime`-parseable string), or
  `null` if `$date` is empty/unparseable. Not used automatically; wire it
  into `defaults` if you want a "days active" style value.

## Development

```bash
composer install
composer test
composer check-cs
```
