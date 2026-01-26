=== WyvernCSS ===
Contributors: feaw
Tags: css, gutenberg, ai, styling, blocks
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.0.14
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered CSS styling for Gutenberg blocks. Select a block, describe how you want it to look, done.

== Description ==

**WyvernCSS** transforms how you style WordPress content. Simply select a Gutenberg block, describe the design you want in plain English, and WyvernCSS generates the CSS automatically—no coding required.

**Perfect for:**
* Content creators who want beautiful designs without learning CSS
* Developers who want to speed up their styling workflow
* Agencies managing multiple WordPress sites
* Anyone who finds CSS frustrating or time-consuming

= How It Works =

1. **Select** - Click any Gutenberg block in the editor
2. **Describe** - Tell WyvernCSS how you want it to look ("Make this button blue with rounded corners and a shadow")
3. **Done** - CSS is generated and applied instantly

**That's it. No CSS knowledge required.**

= Key Features =

= Smart Pattern Library (Zero-Cost Styling) =

WyvernCSS includes 100+ pre-built CSS patterns that handle ~60% of design requests instantly:

* **Common Designs**: Colors, typography, spacing, borders, shadows, animations
* **Instant Response**: Pattern matches return in <50ms
* **Zero Cost**: No AI requests needed for pattern-matched designs
* **Smart Matching**: Advanced keyword extraction and fuzzy matching algorithm

Examples that use the Pattern Library:
* "Make the heading blue and centered"
* "Add 20px padding to this paragraph"
* "Make the button rounded with a shadow"
* "Add a hover effect that changes the color"

= AI-Powered CSS Generation =

For complex designs beyond patterns, WyvernCSS uses AI:

* **Natural Language**: Describe designs in plain English
* **Context-Aware**: Understands your theme's existing styles
* **Smart Fallback**: Only uses AI when patterns don't match (40% of requests)
* **Works Out of the Box**: No API key or configuration needed

**Free Tier (Included):**
* 20 AI requests per day
* Pattern Library (100+ instant CSS patterns, unlimited)
* All core styling features - no artificial restrictions
* Works immediately after activation

**Premium Tier (Coming Soon):**
* Unlimited AI requests
* Better AI models (Claude 3.5 Sonnet, GPT-4o)
* Faster response times
* Priority email support

Premium plans will be available in an upcoming release.

= Gutenberg Block Editor Integration =

* **Editor Sidebar**: Style blocks directly in the Gutenberg editor
* **Live Preview**: See CSS changes in real-time
* **Block Context**: Automatically knows which block you're styling
* **Undo/Redo**: Full WordPress history integration
* **Accessible**: WCAG 2.1 AA compliant interface

= Security & Performance =

* **CSS Validation**: Whitelist-based property validation for security
* **Multi-Layer Caching**: WordPress Object Cache + Transients (5-min TTL)
* **Input Sanitization**: All user inputs sanitized before processing
* **Safe Rendering**: Secure HTML output with wp_kses
* **Rate Limiting**: Protects against abuse
* **WordPress VIP Standards**: Production-ready for high-traffic sites

= Design Examples =

**Typography Styling:**
* "Make this heading use Playfair Display font, 48px, bold, with letter spacing"
* "Style this paragraph with 1.6 line height and justify text"

**Button Styling:**
* "Create a blue gradient button with white text, rounded corners, and a shadow"
* "Add a hover effect that lifts the button slightly and darkens it"

**Layout & Spacing:**
* "Add 40px top margin and 20px padding on all sides"
* "Make this section full width with a light gray background"

**Visual Effects:**
* "Add a subtle shadow and rounded corners to this card"
* "Create a smooth fade-in animation on scroll"

**Color & Backgrounds:**
* "Use a gradient from purple to blue as the background"
* "Make the text white with a semi-transparent dark overlay"

== External Services ==

This plugin connects to external services for AI-powered CSS generation.

= WyvernCSS AI Service =

This plugin connects to the WyvernCSS AI Service for AI-powered CSS generation.

**Service URL**: https://wyvernpress-proxy.feaw-account.workers.dev
**Purpose**: Process natural language design prompts using AI models (Claude, GPT-4) to generate CSS code.

**Data Sent**:
* Your design prompt (the text you type describing the styling you want)
* Element context (the HTML tag name and CSS class names of the selected block)

**When Data is Sent**:
* Only when you submit a styling request that doesn't match a local pattern
* The Pattern Library (100+ CSS patterns) works entirely offline without any service connection
* Approximately 40% of requests use the AI service; 60% are handled locally

**Rate Limits**:
* Free tier: 20 AI requests per day (enforced by the service)
* Premium tier: Unlimited requests (requires subscription)

**Service Provider**: FEAW Services Limited
**Terms of Service**: https://feaw.co.uk/terms
**Privacy Policy**: https://feaw.co.uk/privacy

No personal data beyond the design prompt and element context is transmitted. Your prompts are processed and discarded; they are not stored long-term or used to train AI models.

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or greater
* PHP version 8.1 or greater
* MySQL version 5.7 or greater OR MariaDB version 10.3 or greater

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to **Plugins > Add New**
3. Search for "WyvernCSS"
4. Click "Install Now" and then "Activate"
5. Start styling! Open any post/page in Gutenberg editor and look for the WyvernCSS panel in the sidebar

= Manual Installation =

1. Download the WyvernCSS plugin zip file
2. Log in to your WordPress dashboard
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Choose the downloaded zip file and click "Install Now"
5. Click "Activate Plugin"
6. You're ready to start styling!

= Configuration =

**Free Tier (No Setup Required):**

The free tier works out of the box with 20 AI requests per day. Just activate and start styling!

**Premium Tier:**

Upgrade from the plugin settings page to unlock unlimited AI requests and better models ($5/month or $49/year).

**Optional: Advanced Configuration**

For advanced users, configure via `wp-config.php`:

`
// Pattern Library Settings
define( 'WYVERNCSS_PATTERN_CONFIDENCE_THRESHOLD', 70 ); // 0-100
define( 'WYVERNCSS_CACHE_TTL', 300 ); // 5 minutes

// Performance Tuning
define( 'WYVERNCSS_ENABLE_OBJECT_CACHE', true );
define( 'WYVERNCSS_MAX_PROMPT_LENGTH', 500 );
`

= Upgrading =

Automatic updates should work smoothly, but we recommend backing up your site before upgrading.

Your settings and styling history will be preserved during upgrades.

== Frequently Asked Questions ==

= Do I need to pay for WyvernCSS? =

**No!** WyvernCSS is free and includes:
* 20 AI requests per day
* Pattern Library (100+ instant designs, unlimited use)
* All core CSS styling features

Premium ($5/month or $49/year) offers unlimited AI requests and faster models.

= Do I need to know CSS? =

**No!** That's the point. Just describe what you want in plain English:
* "Make this button blue with rounded corners"
* "Add a shadow to this card"
* "Center this heading"

WyvernCSS translates your description into CSS automatically.

= What is the Pattern Library? =

The Pattern Library is a built-in database of 100+ common CSS designs. When you describe a design, WyvernCSS first checks if it matches a known pattern. If it does (70%+ confidence), the CSS is returned instantly without using any AI—meaning zero cost and <50ms response time.

This handles ~60% of real-world styling requests, including:
* Colors and backgrounds
* Typography and text alignment
* Spacing (padding, margins)
* Borders and shadows
* Basic animations and hover effects

**Pattern Library requests are FREE and unlimited** - they don't count against your daily AI limit!

= How does the free tier work? =

1. **Activate** the plugin - no account or API key needed
2. **Use unlimited Pattern Library** requests (60% of styling needs)
3. **Use 20 AI requests per day** for complex designs
4. **Resets daily** at midnight UTC

Most users find the free tier is plenty for their needs!

= How much does Premium cost? =

**Premium:** $5/month or $49/year (save 18%)
* Unlimited AI requests
* Better AI models (Claude 3.5 Haiku, GPT-4o-mini)
* Faster responses
* Priority email support

That's cheaper than Elementor Pro ($59/year) and Divi AI ($89/year).

Upgrade from Settings → WyvernCSS in your WordPress dashboard.

= Does WyvernCSS work with my theme? =

**Yes!** WyvernCSS is theme-agnostic and works with any WordPress theme that supports Gutenberg. The AI understands your theme's existing styles and generates compatible CSS.

= Does WyvernCSS work with page builders like Elementor or Divi? =

WyvernCSS is designed specifically for the **Gutenberg block editor**. It does not currently support other page builders.

However, if you use Gutenberg alongside Elementor/Divi, you can use WyvernCSS to style Gutenberg blocks.

= Can I style any Gutenberg block? =

Yes! WyvernCSS works with:
* **Core WordPress blocks** (Paragraph, Heading, Button, Image, etc.)
* **Third-party blocks** (from plugins like Kadence, Spectra, etc.)
* **Custom blocks** (from your theme or custom development)

Just select the block and describe the styling you want.

= What happens if I deactivate the plugin? =

Your CSS is applied directly to blocks via Gutenberg's block attributes, so **your styling remains even if you deactivate WyvernCSS**.

However, you won't be able to generate new styles without the plugin active.

= Is my data private and secure? =

**Yes.** WyvernCSS takes security seriously:

* **Design prompts only**: Only your prompt text and element context are sent
* **No personal data**: We don't collect names, emails, or tracking data
* **Encryption**: All data transmitted over HTTPS
* **No storage**: Prompts are processed and discarded, not stored
* **WordPress security**: Uses nonces, capability checks, and input sanitization
* **CSS validation**: Only safe CSS properties are allowed

= Can I use WyvernCSS on client sites? =

**Yes!** WyvernCSS is licensed under GPLv2, which means you can:
* Use it on unlimited sites (personal or client)
* Modify the code for your needs
* Redistribute it

Premium licenses are per-site. See Settings → WyvernCSS for pricing.

= What AI models are available? =

**Free Tier:**
* Efficient AI model via WyvernCSS cloud

**Premium Tier:**
* Claude 3.5 Haiku (fast, affordable)
* GPT-4o-mini (OpenAI's efficient model)

= Does WyvernCSS work with classic editor? =

No. WyvernCSS is designed for the **Gutenberg block editor** (WordPress 5.0+). If you're using the classic editor, you'll need to enable Gutenberg for posts/pages where you want to use WyvernCSS.

= How do I report bugs or request features? =

We welcome feedback!

* **Bug reports**: https://github.com/FEAWServices/wyverncss/issues
* **Feature requests**: https://github.com/FEAWServices/wyverncss/discussions
* **Support forum**: https://wordpress.org/support/plugin/wyverncss/
* **Email**: support@feaw.co.uk

= Is WyvernCSS compatible with WordPress VIP? =

**Yes!** WyvernCSS is built to WordPress VIP coding standards:
* PHPStan Level 8 static analysis
* WordPress Coding Standards (WPCS)
* Strict typing (PHP 8.1+)
* No direct database queries (uses WordPress APIs)
* Comprehensive test coverage (PHPUnit, Jest)

Production-ready for high-traffic WordPress sites.

== Screenshots ==

1. **WyvernCSS Sidebar in Gutenberg** - Select a block and describe the styling you want
2. **Pattern Suggestions** - Quick-access buttons for common designs
3. **Live CSS Preview** - See your styles applied in real-time
4. **Style History** - Re-use previous design prompts
5. **Settings Dashboard** - Configure preferences (optional)

== Changelog ==

= 1.0.0 - 2025-12-15 =

**Initial Release**

Core Features:
* Pattern Library with 100+ CSS patterns for instant styling
* AI-powered CSS generation from natural language descriptions
* Gutenberg block editor sidebar integration
* Element selection and context awareness
* Live CSS preview in the editor
* Style history for reusing previous prompts
* Multi-layer caching for performance (Object Cache + Transients)
* CSS validation and security (whitelist-based)
* Accessibility compliant (WCAG 2.1 AA)

Free Tier:
* 20 AI requests per day
* Unlimited Pattern Library (zero-cost designs)
* All core features included
* Works out of the box - no configuration needed

Premium:
* $5/month or $49/year (unlimited AI requests, better models)

Technical:
* PHP 8.1+ with strict typing
* WordPress 6.4+ compatibility
* React 18 + TypeScript frontend
* WordPress VIP standards compliance
* Comprehensive test suite (PHPUnit, Jest)
* PSR-4 autoloading
* REST API endpoints
* Internationalization ready (i18n)

== Upgrade Notice ==

= 1.0.0 =
Initial release of WyvernCSS. Requires WordPress 6.4+ and PHP 8.1+. Free tier includes 20 AI requests per day plus unlimited Pattern Library.

== Privacy Policy ==

WyvernCSS respects your privacy:

* **Design prompts**: Sent to secure WyvernCSS cloud infrastructure for AI processing
* **No data retention**: Prompts are processed and discarded, not stored long-term
* **No tracking**: WyvernCSS does not use cookies, analytics, or tracking scripts
* **No data selling**: We never sell or share your data with third parties
* **HTTPS only**: All communication is encrypted

For full details, see our Privacy Policy: https://feaw.co.uk/privacy

== Support ==

Need help? We're here for you:

* **Support Forum**: https://wordpress.org/support/plugin/wyverncss/
* **GitHub**: https://github.com/FEAWServices/wyverncss
* **Premium Support**: Available with paid licenses (priority response)

== Contributing ==

WyvernCSS is open source! Contributions are welcome:

* **GitHub Repository**: https://github.com/FEAWServices/wyverncss
* **Coding Standards**: WordPress VIP, PSR-4, PHPStan Level 8
* **Testing**: PHPUnit (PHP), Jest (JavaScript), Playwright (E2E)
* **License**: GPLv2 or later

See CONTRIBUTING.md in the repository for developer guidelines.
