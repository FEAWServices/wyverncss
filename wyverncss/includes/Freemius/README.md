# Freemius Integration

This directory contains the WyvernPress wrapper for the Freemius SDK, providing strongly-typed license validation, premium feature checks, and checkout flow management.

## Overview

The Freemius Integration wrapper provides a clean, type-safe interface to the Freemius SDK with:

- **License Validation**: Check if user has active premium license
- **Plan Management**: Get current plan name (free, premium, professional)
- **Feature Checks**: Verify if user can access premium features
- **Tier Integration**: Seamless integration with WyvernPress Tier_Config
- **Caching**: Automatic license data caching (5-minute TTL)
- **Checkout URLs**: Generate upgrade and pricing page URLs

## Architecture

```
WyvernPress Plugin
├── freemius/                         # Freemius SDK (third-party)
│   └── start.php
├── includes/
│   ├── freemius-init.php             # SDK initialization (global functions)
│   ├── Freemius/
│   │   ├── class-freemius-integration.php  # OOP wrapper (THIS FILE)
│   │   └── README.md                 # This documentation
│   └── Config/
│       └── class-tier-config.php     # Tier limits and features
```

## Setup Instructions

### 1. Freemius Account Setup

1. **Create Freemius Account**: Sign up at [freemius.com](https://freemius.com)
2. **Add Your Plugin**: Create a new plugin product in your Freemius dashboard
3. **Configure Plans**: Set up your pricing plans (Free, Premium, etc.)
4. **Get Credentials**: Copy your Plugin ID and Public Key

### 2. Update SDK Configuration

Edit `/workspace/wyvernpress/plugins/wyvernpress/includes/freemius-init.php` and update the SDK initialization:

```php
$wyvernpress_fs = fs_dynamic_init(
    array(
        'id'                  => 'YOUR_PLUGIN_ID',        // From Freemius dashboard
        'slug'                => 'wyvernpress',
        'premium_slug'        => 'wyvernpress-premium',
        'type'                => 'plugin',
        'public_key'          => 'pk_YOUR_PUBLIC_KEY',    // From Freemius dashboard
        'is_premium'          => false,
        'has_premium_version' => true,
        'has_paid_plans'      => true,
        // ... other config
    )
);
```

**Current Configuration** (as of December 2025):
- Plugin ID: `22259`
- Public Key: `pk_5cad950fed79e06553e6b464645ed`
- Status: `is_live` = true (production ready)

### 3. Plan Mapping

Ensure your Freemius plans map to WyvernPress tiers in `/workspace/wyvernpress/plugins/wyvernpress/config/tiers.json`:

```json
{
  "tiers": {
    "free": {
      "name": "Free",
      "limit": 20,
      "period": "day"
    },
    "premium": {
      "name": "Premium",
      "limit": 500,
      "period": "month"
    }
  }
}
```

### 4. Deploy Freemius SDK

The Freemius SDK is already installed at `/workspace/wyvernpress/plugins/wyvernpress/freemius/`.

To update to the latest version:

```bash
# Download latest SDK
cd /tmp
wget https://github.com/Freemius/wordpress-sdk/archive/refs/heads/master.zip
unzip master.zip

# Replace existing SDK
rm -rf /workspace/wyvernpress/plugins/wyvernpress/freemius
mv wordpress-sdk-master /workspace/wyvernpress/plugins/wyvernpress/freemius
```

## Usage Examples

### Basic Usage

```php
use WyvernPress\Freemius\Freemius_Integration;

// Get singleton instance
$freemius = Freemius_Integration::get_instance();

// Check if user is premium
if ( $freemius->is_premium() ) {
    // Enable premium features
}

// Get current plan
$plan = $freemius->get_plan(); // 'free', 'premium', 'professional'

// Get license key
$license_key = $freemius->get_license_key();
```

### Tier-Based Feature Checks

```php
// Get rate limit for current user's tier
$limit = $freemius->get_rate_limit(); // 20 for free, 500 for premium, -1 for unlimited

// Check if unlimited
if ( $freemius->is_unlimited() ) {
    // No rate limiting
}

// Get AI configuration for current tier
$ai_config = $freemius->get_ai_config();
// Returns: ['provider' => 'openrouter', 'model' => 'claude-3.5-haiku']
```

### Feature Gating

```php
// Check specific feature availability
if ( $freemius->has_feature( 'advanced-css-patterns' ) ) {
    // Show advanced patterns UI
}

// Get all features for current tier
$features = $freemius->get_tier_features();
// Returns: ['basic-styling', 'pattern-library', 'openrouter-ai', ...]
```

### Upgrade Flow

```php
// Get upgrade URL
$upgrade_url = $freemius->get_upgrade_url();

// Get upgrade URL for specific plan
$upgrade_url = $freemius->get_upgrade_url( '12345' ); // Plan ID

// Get pricing page URL
$pricing_url = $freemius->get_pricing_url();

// Get account management URL
$account_url = $freemius->get_account_url();
```

### License Information

```php
// Get full license data
$license = $freemius->get_license_data();
/*
Returns:
array(
    'key'           => 'xxxx-xxxx-xxxx-xxxx',
    'status'        => 'active',
    'plan'          => 'Premium',
    'plan_id'       => 12345,
    'expires'       => 1735689600,
    'is_active'     => true,
    'is_expired'    => false,
    'activations'   => 1,
    'max_sites'     => 5,
    'premium'       => true,
)
*/

// Check license expiration
if ( $freemius->is_license_expired() ) {
    // Show renewal notice
}

// Get days until expiration
$days = $freemius->get_days_until_expiration(); // Returns negative if expired

// Get expiration date
$expiration = $freemius->get_license_expiration(); // 'Y-m-d' format
```

### Admin UI Integration

```php
// Display upgrade message
if ( ! $freemius->is_premium() ) {
    $message = $freemius->get_upgrade_message( 'at_limit' );
    echo '<div class="notice notice-warning">' . esc_html( $message ) . '</div>';
}

// Trigger checkout dialog (JavaScript)
$checkout_js = $freemius->get_checkout_js( '12345', true ); // plan_id, is_annual
echo '<script>' . $checkout_js . '</script>';
```

### REST API Integration

```php
/**
 * Example REST endpoint that uses Freemius integration
 */
class Style_Controller extends WP_REST_Controller {

    private Freemius_Integration $freemius;

    public function __construct() {
        $this->freemius = Freemius_Integration::get_instance();
    }

    public function create_style( WP_REST_Request $request ): WP_REST_Response {
        // Check rate limit
        $limit = $this->freemius->get_rate_limit();
        $usage = $this->get_user_usage();

        if ( $limit !== -1 && $usage >= $limit ) {
            return new WP_REST_Response(
                array(
                    'message'      => 'Rate limit exceeded',
                    'upgrade_url'  => $this->freemius->get_upgrade_url(),
                    'upgrade_text' => $this->freemius->get_upgrade_message( 'at_limit' ),
                ),
                429
            );
        }

        // Generate CSS based on tier
        $ai_config = $this->freemius->get_ai_config();
        $css = $this->generate_css( $request->get_param( 'prompt' ), $ai_config );

        return new WP_REST_Response( array( 'css' => $css ) );
    }
}
```

## Caching Strategy

The integration implements a two-tier caching strategy:

1. **In-Memory Cache**: License data cached in class property for request lifetime
2. **WordPress Transient**: 5-minute TTL for license data across requests

Cache is automatically cleared when:
- License is activated/deactivated
- Plan changes (upgrade/downgrade)
- License status changes

Manual cache clearing:

```php
$freemius = Freemius_Integration::get_instance();
$freemius->clear_license_cache();
```

## Security Considerations

### Capability Checks

Always verify user capabilities before checking premium status:

```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions' );
}

$freemius = Freemius_Integration::get_instance();
if ( $freemius->is_premium() ) {
    // Allow admin to configure premium features
}
```

### License Key Handling

**NEVER** expose license keys to frontend JavaScript:

```php
// ✅ CORRECT - Server-side only
$license_key = $freemius->get_license_key();
$this->activate_premium_feature( $license_key );

// ❌ WRONG - Never do this
wp_localize_script( 'my-script', 'wyvernpress', array(
    'license_key' => $freemius->get_license_key(), // SECURITY RISK!
) );
```

### Input Validation

When handling plan IDs from user input:

```php
$plan_id = sanitize_text_field( $_GET['plan_id'] ?? '' );
if ( ! is_numeric( $plan_id ) ) {
    wp_die( 'Invalid plan ID' );
}

$upgrade_url = $freemius->get_upgrade_url( $plan_id );
```

## Testing

### Unit Tests

```php
<?php
declare(strict_types=1);

namespace WyvernPress\Tests\Freemius;

use WP_UnitTestCase;
use WyvernPress\Freemius\Freemius_Integration;

class Freemius_Integration_Test extends WP_UnitTestCase {

    private Freemius_Integration $freemius;

    public function setUp(): void {
        parent::setUp();
        $this->freemius = Freemius_Integration::get_instance();
    }

    public function test_get_plan_returns_string(): void {
        $plan = $this->freemius->get_plan();
        $this->assertIsString( $plan );
        $this->assertContains( $plan, [ 'free', 'premium', 'professional' ] );
    }

    public function test_is_premium_returns_bool(): void {
        $is_premium = $this->freemius->is_premium();
        $this->assertIsBool( $is_premium );
    }

    public function test_free_user_has_correct_rate_limit(): void {
        // Mock free user
        $rate_limit = $this->freemius->get_rate_limit();
        $this->assertEquals( 20, $rate_limit );
    }
}
```

### Integration Tests

Test with actual Freemius sandbox:

1. Set up Freemius sandbox account
2. Use sandbox credentials in development environment
3. Test checkout flow end-to-end
4. Verify license activation/deactivation
5. Test plan upgrades/downgrades

## WP-CLI Compatibility

The integration handles WP-CLI gracefully. When running in CLI mode, all methods return safe defaults:

```bash
# These commands won't hang or error
wp eval 'var_dump( WyvernPress\Freemius\Freemius_Integration::get_instance()->get_plan() );'
# Output: string(4) "free"
```

See `/workspace/wyvernpress/plugins/wyvernpress/includes/freemius-init.php` for stub implementations.

## Troubleshooting

### "Call to undefined function wyvernpress_fs()"

**Cause**: Freemius SDK not loaded or initialized too late.

**Solution**: Ensure `includes/freemius-init.php` is loaded in main plugin file before autoloader:

```php
// wyvernpress.php
if ( file_exists( WYVERNPRESS_PLUGIN_DIR . 'freemius/start.php' ) ) {
    require_once WYVERNPRESS_PLUGIN_DIR . 'includes/freemius-init.php';
}
require_once WYVERNPRESS_PLUGIN_DIR . 'includes/autoload.php'; // After Freemius
```

### "Freemius API calls hang in WP-CLI"

**Cause**: Freemius makes API calls that block in CLI mode.

**Solution**: Already handled - see stub functions in `freemius-init.php`.

### Cache not clearing after license change

**Cause**: Hooks not registered properly.

**Solution**: Ensure hooks are registered after Freemius loads:

```php
add_action( 'wyvernpress_fs_loaded', function() {
    $freemius = Freemius_Integration::get_instance();
    $freemius->setup_license_hooks();
} );
```

### "Invalid plugin ID" error

**Cause**: Using placeholder credentials from initial setup.

**Solution**: Update `freemius-init.php` with real credentials from Freemius dashboard.

## WordPress.org Compliance

WyvernPress follows WordPress.org guidelines for freemium plugins:

- ✅ Free version has NO premium code (uses `is_premium()` checks)
- ✅ No time-limited trials (WordPress.org prohibits)
- ✅ Clear feature differentiation (free: 20 req/day, premium: unlimited)
- ✅ No "nag screens" - only contextual upgrade prompts
- ✅ All features work without Freemius (graceful degradation)

**Deployment**: Use Freemius deployment tool to strip premium code before submitting to WordPress.org.

## API Reference

### Core Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `get_instance()` | `Freemius_Integration` | Get singleton instance |
| `is_premium()` | `bool` | Check if user has premium license |
| `is_configured()` | `bool` | Check if Freemius is properly configured |
| `get_plan()` | `string` | Get current plan name |
| `get_license_key()` | `?string` | Get license key |
| `get_license_data()` | `array` | Get full license data (cached) |

### Tier Integration

| Method | Return Type | Description |
|--------|-------------|-------------|
| `get_rate_limit()` | `int` | Get rate limit for current tier |
| `get_period()` | `string` | Get reset period (day/month/unlimited) |
| `is_unlimited()` | `bool` | Check if current plan is unlimited |
| `get_ai_config()` | `array` | Get AI provider config for tier |
| `get_tier_features()` | `array` | Get features list for tier |
| `has_feature()` | `bool` | Check if tier has specific feature |

### URLs & Checkout

| Method | Return Type | Description |
|--------|-------------|-------------|
| `get_upgrade_url()` | `string` | Get checkout URL |
| `get_pricing_url()` | `string` | Get pricing page URL |
| `get_account_url()` | `string` | Get account management URL |
| `get_checkout_js()` | `string` | Get JavaScript for checkout dialog |

### License Status

| Method | Return Type | Description |
|--------|-------------|-------------|
| `is_license_expired()` | `bool` | Check if license is expired |
| `get_license_expiration()` | `?string` | Get expiration date (Y-m-d) |
| `get_days_until_expiration()` | `int` | Days until expiration (negative if expired) |
| `is_trial()` | `bool` | Check if user is in trial period |
| `get_trial_days_remaining()` | `int` | Get days remaining in trial |

### Cache Management

| Method | Return Type | Description |
|--------|-------------|-------------|
| `clear_license_cache()` | `void` | Clear license data cache |

## Resources

- **Freemius Documentation**: https://freemius.com/help/documentation/wordpress-sdk/
- **Freemius GitHub**: https://github.com/Freemius/wordpress-sdk
- **WyvernPress Tier Config**: `/workspace/wyvernpress/plugins/wyvernpress/config/tiers.json`
- **Integration Init File**: `/workspace/wyvernpress/plugins/wyvernpress/includes/freemius-init.php`

## Support

For Freemius-specific issues:
- Freemius Support: https://freemius.com/help/
- Freemius SDK Issues: https://github.com/Freemius/wordpress-sdk/issues

For WyvernPress integration issues:
- Create issue in WyvernPress repository
- Check documentation in `/workspace/docs/`

## Changelog

### 1.0.0 (December 2025)
- Initial Freemius integration wrapper
- Singleton pattern with strong typing
- Tier_Config integration
- Two-tier caching (in-memory + transient)
- WP-CLI compatibility
- WordPress.org compliance
