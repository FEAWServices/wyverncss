# WyvernCSS Core Configuration

## Required External Services

WyvernCSS Core requires two external services to provide full functionality:

1. **Admin Service** - License validation and customer portal management
2. **MCP Processing Service** - AI tool execution and WordPress operations

## wp-config.php Constants

Add these constants to your `wp-config.php` file before the `/* That's all, stop editing! */` line.

### Admin Service (License Validation)

```php
/**
 * Admin Service Configuration
 */
// Admin Service URL (default: http://localhost:3000)
define( 'WYVERNPRESS_ADMIN_SERVICE_URL', 'http://localhost:3000' );

// Admin Service API Secret (obtain from admin dashboard)
define( 'WYVERNPRESS_ADMIN_SERVICE_API_SECRET', 'your-admin-secret-here' );
```

### MCP Processing Service (AI Tools)

```php
/**
 * MCP Processing Service Configuration
 */
// MCP Service URL (default: http://localhost:8001)
define( 'WYVERNPRESS_MCP_SERVICE_URL', 'http://localhost:8001' );

// MCP Service API Secret (obtain from admin dashboard)
define( 'WYVERNPRESS_MCP_SERVICE_API_SECRET', 'your-mcp-secret-here' );
```

### Circuit Breaker Settings (Optional)

Fine-tune circuit breaker behavior for MCP service resilience:

```php
/**
 * Circuit Breaker Configuration (Optional)
 */
// Number of consecutive failures before opening circuit (default: 5)
define( 'WYVERNPRESS_MCP_FAILURE_THRESHOLD', 5 );

// Cooldown period in seconds before retry attempts (default: 3600 = 1 hour)
define( 'WYVERNPRESS_MCP_COOLDOWN_PERIOD', 3600 );
```

### Cache Settings (Optional)

Configure caching behavior for API responses:

```php
/**
 * Cache Configuration (Optional)
 */
// License validation cache TTL in seconds (default: 86400 = 24 hours)
define( 'WYVERNPRESS_LICENSE_CACHE_TTL', 86400 );

// MCP response cache TTL in seconds (default: 300 = 5 minutes)
define( 'WYVERNPRESS_MCP_CACHE_TTL', 300 );
```

## Environment-Specific Configuration

### Development Environment

For local development with Docker Compose:

```php
// Development configuration
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Admin Service (local)
define( 'WYVERNPRESS_ADMIN_SERVICE_URL', 'http://localhost:3000' );
define( 'WYVERNPRESS_ADMIN_SERVICE_API_SECRET', 'dev-secret-key-change-in-production' );

// MCP Processing Service (local)
define( 'WYVERNPRESS_MCP_SERVICE_URL', 'http://localhost:8001' );
define( 'WYVERNPRESS_MCP_SERVICE_API_SECRET', 'dev-mcp-secret-change-in-production' );
```

### Staging Environment

For staging/testing environments:

```php
// Staging configuration
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Admin Service (staging)
define( 'WYVERNPRESS_ADMIN_SERVICE_URL', 'https://staging-admin.wyverncss.com' );
define( 'WYVERNPRESS_ADMIN_SERVICE_API_SECRET', 'staging-secret-from-dashboard' );

// MCP Processing Service (staging)
define( 'WYVERNPRESS_MCP_SERVICE_URL', 'https://staging-mcp.wyverncss.com' );
define( 'WYVERNPRESS_MCP_SERVICE_API_SECRET', 'staging-mcp-secret-from-dashboard' );
```

### Production Environment

For production deployments:

```php
// Production configuration
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );

// Admin Service (production)
define( 'WYVERNPRESS_ADMIN_SERVICE_URL', 'https://admin.wyverncss.com' );
define( 'WYVERNPRESS_ADMIN_SERVICE_API_SECRET', 'production-secret-from-dashboard' );

// MCP Processing Service (production)
define( 'WYVERNPRESS_MCP_SERVICE_URL', 'https://mcp.wyverncss.com' );
define( 'WYVERNPRESS_MCP_SERVICE_API_SECRET', 'production-mcp-secret-from-dashboard' );

// Production optimizations
define( 'WYVERNPRESS_MCP_FAILURE_THRESHOLD', 3 );
define( 'WYVERNPRESS_MCP_COOLDOWN_PERIOD', 1800 ); // 30 minutes
```

## Fallback Behavior

WyvernCSS Core is designed to degrade gracefully when external services are unavailable:

### License Validation Fallback

1. **Cached Validation**: Uses cached license status (24-hour TTL)
2. **Unlicensed Mode**: Falls back to free tier functionality after cache expires
3. **Pattern Library**: Always available (zero external dependencies)

### MCP Service Fallback

1. **Circuit Breaker**: Opens after 5 consecutive failures (configurable)
2. **Graceful Degradation**: Returns user-friendly error messages
3. **Pattern Library**: Continues to serve 100+ CSS patterns locally
4. **Automatic Recovery**: Circuit breaker resets after cooldown period

### What Still Works When Services Are Down

- Pattern Library (100+ CSS patterns, zero AI calls)
- WordPress admin interface
- Settings pages
- Local content browsing
- Cached API responses (5-minute TTL)

### What Requires External Services

- License activation and validation
- AI-powered tool execution (MCP tools)
- Real-time WordPress operations via AI
- Chat with AI assistant

## Verifying Configuration

### 1. WordPress Admin Dashboard

Navigate to **Settings → WyvernCSS → License Status** to check:

- License activation status
- Admin Service connection status
- Current tier and features

### 2. Health Check Endpoint

Use the REST API health check endpoint (requires admin privileges):

```bash
curl -X GET "https://your-site.com/wp-json/wyverncss/v1/health" \
  -H "X-WP-Nonce: YOUR_NONCE_HERE"
```

Expected response:

```json
{
  "status": "healthy",
  "services": {
    "plugin": "ok",
    "admin_service": "ok",
    "mcp_service": "ok",
    "pattern_library": "ok"
  },
  "timestamp": "2025-10-16 12:00:00"
}
```

Service status values:
- `ok` - Service is reachable and responding
- `not_configured` - Required constant not defined in wp-config.php
- `unreachable` - Service is configured but not responding
- `circuit_open` - Circuit breaker is open (MCP service only)

### 3. PHP Error Log

Check your WordPress debug log for connection issues:

```bash
tail -f /path/to/wp-content/debug.log
```

Look for entries prefixed with `WyvernCSS:`.

## Troubleshooting

### Admin Service Not Reachable

**Symptoms**: License validation fails, shows "Service unavailable"

**Solutions**:
1. Verify `WYVERNPRESS_ADMIN_SERVICE_URL` is correct
2. Check network connectivity from WordPress server to Admin Service
3. Verify API secret is correct
4. Check firewall rules allow outbound HTTPS connections
5. Review Admin Service logs for authentication errors

### MCP Service Not Reachable

**Symptoms**: AI tools fail, circuit breaker opens

**Solutions**:
1. Verify `WYVERNPRESS_MCP_SERVICE_URL` is correct
2. Check network connectivity from WordPress server to MCP Service
3. Verify API secret is correct
4. Check MCP service health: `curl http://localhost:8001/api/v1/health`
5. Review circuit breaker status in health check endpoint
6. Wait for cooldown period (default: 1 hour) or restart WordPress to reset circuit

### Circuit Breaker Stuck Open

**Symptoms**: MCP tools show "temporarily unavailable" message

**Solutions**:
1. Check health endpoint: `/wp-json/wyverncss/v1/health`
2. Verify MCP service is running and healthy
3. Wait for cooldown period to expire
4. Restart PHP-FPM or Apache to reset circuit breaker
5. Adjust `WYVERNPRESS_MCP_FAILURE_THRESHOLD` if too sensitive

### License Shows as Invalid

**Symptoms**: License key rejected, features disabled

**Solutions**:
1. Verify license key is entered correctly (format: `INTP-XXXX-XXXX-XXXX-XXXX`)
2. Check license hasn't expired
3. Verify site URL matches registered domain
4. Check site activation limit hasn't been exceeded
5. Clear license cache: Delete transient `wyverncss_license_*` in database
6. Use "Refresh" button in WordPress admin

## Security Best Practices

### API Secrets

- **NEVER** commit API secrets to version control
- Use environment-specific secrets (different for dev/staging/prod)
- Rotate secrets regularly (recommended: every 90 days)
- Store secrets in secure credential management system
- Use strong, randomly generated secrets (minimum 32 characters)

### Network Security

- Use HTTPS for all production service URLs
- Restrict network access using firewalls
- Implement IP whitelisting if possible
- Enable WordPress authentication for REST API endpoints
- Monitor failed authentication attempts

### WordPress Configuration

- Keep WordPress core, plugins, and themes updated
- Use strong authentication salts in wp-config.php
- Enable HTTPS (SSL/TLS) for WordPress admin
- Implement Web Application Firewall (WAF)
- Regular security audits and vulnerability scans

## Performance Optimization

### Caching Recommendations

```php
// Optimize cache TTLs based on your traffic patterns

// High-traffic sites (reduce API calls)
define( 'WYVERNPRESS_LICENSE_CACHE_TTL', 86400 );  // 24 hours
define( 'WYVERNPRESS_MCP_CACHE_TTL', 600 );        // 10 minutes

// Low-traffic sites (fresher data)
define( 'WYVERNPRESS_LICENSE_CACHE_TTL', 43200 );  // 12 hours
define( 'WYVERNPRESS_MCP_CACHE_TTL', 300 );        // 5 minutes
```

### Circuit Breaker Tuning

```php
// Aggressive (fail fast, quick recovery)
define( 'WYVERNPRESS_MCP_FAILURE_THRESHOLD', 3 );
define( 'WYVERNPRESS_MCP_COOLDOWN_PERIOD', 900 ); // 15 minutes

// Conservative (tolerate failures, slower recovery)
define( 'WYVERNPRESS_MCP_FAILURE_THRESHOLD', 10 );
define( 'WYVERNPRESS_MCP_COOLDOWN_PERIOD', 7200 ); // 2 hours
```

### WordPress Object Cache

For high-traffic sites, use persistent object cache (Redis, Memcached):

```php
// wp-config.php
define( 'WP_CACHE', true );

// Requires Redis Object Cache plugin
define( 'WP_REDIS_HOST', 'localhost' );
define( 'WP_REDIS_PORT', 6379 );
```

## Support

For configuration assistance:

- Documentation: https://docs.wyverncss.com
- Support Portal: https://support.wyverncss.com
- GitHub Issues: https://github.com/wyverncss/wyverncss/issues
- Community Forum: https://community.wyverncss.com

## Changelog

### Version 2.0.0 (2025-10-16)

- External MCP service architecture
- License validation via Admin Service
- Circuit breaker pattern implementation
- Health check REST endpoint
- Comprehensive fallback behavior
