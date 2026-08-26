# wp-utm-links

Shared helper for building UTM-tagged links (docs/marketing URLs, telemetry
query args) across WordPress plugins. Extracted from duplicated code in
`accessibility-checker`, `accessibility-checker-pro`, and `archivewp`.

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
        "pattonwebz/wp-utm-links": "^1.0"
    }
}
```

## Usage

Configure one instance per plugin, then reuse it anywhere you need a link.

```php
use Pattonwebz\WPUtmLinks\UtmLinkBuilder;

$links = new UtmLinkBuilder(
    [
        'utm_source'        => 'my-plugin',
        'activation_option' => 'my_plugin_activation_date',
        'is_pro'            => static function () {
            return defined( 'MY_PLUGIN_PRO_KEY_VALID' ) && MY_PLUGIN_PRO_KEY_VALID;
        },
        'software_version'  => defined( 'MY_PLUGIN_PRO_VERSION' ) ? MY_PLUGIN_PRO_VERSION : MY_PLUGIN_VERSION,
        'ref_filter'        => 'my_plugin_filter_link_ref',
        'base_links'        => [
            'default' => 'https://example.com/my-plugin/docs/',
            'pro'     => 'https://example.com/my-plugin/pricing/',
            'help'    => 'https://example.com/my-plugin/help/',
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

Every generated link automatically gets `utm_source`, `utm_medium`,
`utm_campaign`, `php_version`, `platform`, `platform_version`, `software`
(`pro`/`free`), `software_version`, and (if `activation_option` is set)
`days_active`. Any of these can be overridden per-call via `$query_args`.

## Development

```bash
composer install
composer test
composer check-cs
```
