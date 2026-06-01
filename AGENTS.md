# AGENTS.md — WooCommerce Google Meet Integration

## Project Overview

WordPress plugin that integrates Google Calendar/Meet with WooCommerce. Customers select an available time slot on the product page; on order completion the plugin creates a Google Calendar event (optionally with a Google Meet link) and emails the customer/admin.

- **Plugin slug:** `woo-gmeet`
- **Namespace:** `WGM`
- **Text domain:** `wgm`
- **PHP version:** 8.0+
- **Requires:** WooCommerce plugin active

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.0+ |
| Platform | WordPress 6.x |
| E-commerce | WooCommerce 9.x |
| Google API | `google/apiclient` ^2.18 (Calendar, Oauth2, PeopleService) |
| Dev deps | `php-stubs/wordpress-stubs`, `php-stubs/woocommerce-stubs` |
| Container | Docker (mariadb:10.6 + wordpress:php8.2-apache) |
| Build | `build.sh` — copies files to `build/` and zips |

## Directory Structure

```
woocommerce_meetings/
├── woo-gmeet.php              # Plugin entry point — constants, requires, hooks
├── composer.json              # Dependencies + PSR-4 autoload (WGM\ → includes/)
├── build.sh                   # Production build script → build/woocommerce-meetings.zip
├── docker-compose.yml         # Dev environment (WP + MariaDB)
├── assets/
│   └── product/
│       ├── eventPicker.js     # Frontend JS — year/month picker, fetches availability via REST
│       └── eventPicker.css    # Responsive grid for event picker
├── includes/
│   ├── Settings.php           # Admin settings pages (4 tabs), sanitization, OAuth flow
│   ├── GoogleClient.php       # Google API client wrapper, token management, calendar service
│   ├── Availability.php       # REST endpoint for availability, event reservation logic
│   ├── Checkout.php           # WooCommerce hooks — product metabox, cart/order integration, emails
│   └── Email.php              # Email formatting and delivery (HTML email with template tags)
└── vendor/                    # Composer dependencies (gitignored)
```

## Key Architectural Patterns

### Static Classes with `init()` Pattern

All main classes are static (never instantiated). Each has an `init()` method that registers WordPress hooks:

```php
// woo-gmeet.php
add_action('plugins_loaded', function () {
    \WGM\Settings::init();
    \WGM\GoogleClient::init();
    \WGM\Availability::init();
    \WGM\Checkout::init();
});
```

Do not introduce class instances without a strong reason — static methods match the existing pattern.

### Settings Storage

Four separate WordPress options, each an associative array:

| Constant | Option Key | Content |
|----------|-----------|---------|
| `Settings::OPT_ACCOUNT` | `wgm_account_settings` | `auth_json` (OAuth JSON), `google_token` (access/refresh token array) |
| `Settings::OPT_CALENDAR` | `wgm_calendar_settings` | `calendar_id`, `calendar_reservations_id`, `event_language`, `prefix`, `event_color`, `timezone` |
| `Settings::OPT_MEET` | `wgm_meet_settings` | `enable_meet` (yes/no) |
| `Settings::OPT_EMAIL` | `wgm_email_settings` | Toggles, admin list, subjects, HTML templates |

Access via `Settings::get($key, $default, $option_group)`. Always use the constants for `$option_group`.

### Google API Client

- `GoogleClient::getClient()` — singleton `Google\Client` configured from `auth_json` option. Sets OAuth web application credentials, offline access, scopes (Calendar, Calendar.Events, PeopleService userinfo.email).
- `GoogleClient::getClientWithToken()` — returns client with access token set; auto-refreshes expired tokens using refresh_token and persists updated token to options.
- `GoogleClient::calendarService()` — returns `Google\Service\Calendar` or null if not configured.
- `GoogleClient::getEventById($eventId)` — fetches single event from the source calendar.

**OAuth flow:** `handle_google_login()` in Settings.php redirects to Google → callback with `code` → `fetchAccessTokenWithAuthCode()` → stores token in `OPT_ACCOUNT`.

### REST API

Single endpoint registered in `Availability::init()`:

```
GET wp-json/wc-gmeet/v1/availability?month=6&year=2026
```

- **Public** (`permission_callback => '__return_true'`) — needed for unauthenticated frontend use.
- Returns events from configured source calendar for the given month (or current month + next 30 days).
- Filters out events whose summary starts with the configured `prefix` (these are existing reservations).
- Response: `{start, end, events: { "YYYY-MM-DD": [{id, start, end}] }}`

When adding new REST endpoints, assess whether public access is appropriate.

### Reservation Flow

1. Product page: `_wgm_enabled` product meta = 'yes' → JS event picker loads.
2. Customer selects event slot → `wgm_event_id` posted with add-to-cart.
3. Validation in `validate_event_id_on_add_to_cart()`: checks event exists, not in past, not already in cart.
4. Cart/checkout: `wgm_event_id` stored as hidden cart item data.
5. Order created: `_wgm_event_id` meta saved on order line item.
6. Order completed: `send_customer_meeting_email_on_payment()` fires:
   - Deletes original event from source calendar.
   - Creates new event in reservations calendar with prefix, customer name, attendees, optional Meet link.
   - Sends email to customer (if enabled) and admin list (if enabled).

### Product Meta

- `_wgm_enabled` (yes/no) — controlled by checkbox in Product Data metabox (`woocommerce_product_options_general_product_data`).

## WordPress Hooks Reference

### Actions

| Hook | Callback | Purpose |
|------|----------|---------|
| `plugins_loaded` | `Settings::init`, `GoogleClient::init`, `Availability::init`, `Checkout::init` | Bootstrap all modules |
| `init` | `load_plugin_textdomain` | Load translations |
| `admin_menu` | `Settings::menu` | Register admin menu pages |
| `admin_init` | `Settings::register` | Register settings/sections/fields |
| `admin_init` | `Settings::handle_google_login` | Handle OAuth redirect |
| `rest_api_init` | `Availability::init` closure | Register REST route |
| `wp_enqueue_scripts` | `Checkout::enqueue_event_picker_script` | Load JS/CSS on product pages |
| `woocommerce_before_add_to_cart_button` | `Checkout::product_event_id_field` | Render event picker container |
| `woocommerce_checkout_create_order_line_item` | `Checkout::add_event_id_to_order_item` | Save event ID to order item |
| `woocommerce_order_status_completed` | `Checkout::send_customer_meeting_email_on_payment` | Reserve event + send emails |
| `woocommerce_product_options_general_product_data` | `Checkout::add_wgm_option_to_product` | Add WGM checkbox to product edit |
| `woocommerce_process_product_meta` | `Checkout::save_wgm_option` | Save WGM checkbox |
| `woocommerce_admin_order_actions_end` | `Checkout::add_force_meeting_email_button` | Force email button in order list |
| `admin_post_wgm_force_meeting_email` | `Checkout::handle_force_meeting_email_action` | Handle force email action |

### Filters

| Hook | Callback | Purpose |
|------|----------|---------|
| `woocommerce_add_cart_item_data` | `Checkout::add_event_id_to_cart_item` | Add event ID to cart item |
| `woocommerce_get_item_data` | `Checkout::display_event_id_in_cart` | Display event time in cart |
| `woocommerce_add_to_cart_validation` | `Checkout::validate_event_id_on_add_to_cart` | Validate event before add-to-cart |
| `woocommerce_hidden_order_itemmeta` | `Checkout::hide_wgm_meta` | Hide internal metas in frontend |
| `woocommerce_blocks_order_item_meta` | `Checkout::hide_wgm_meta_blocks` | Hide internal metas in blocks |

## Coding Conventions

### Sanitization & Escaping

- **All settings output must be escaped.** Use `esc_attr()` for attributes, `esc_html()` for text content, `esc_url()` for URLs, `esc_textarea()` for textareas.
- **All `register_setting()` calls must have `sanitize_callback`.** Follow existing pattern: dedicated sanitize method per option group.
- **Descriptions containing HTML** must pass through `wp_kses()` with allowed tags (`a` with `href`, `target`, `rel`).
- **Colors from Google API** must pass through `sanitize_hex_color()` before output.
- **REST API input** must be type-cast at retrieval (e.g., `(int)$req->get_param()`).

### Security Requirements

- **Capability checks:** Admin pages use `manage_options`; WooCommerce actions use `manage_woocommerce`.
- **Nonces:** All admin GET/POST actions must have nonce verification. Use `wp_nonce_url()` for links, `check_admin_referer()` or `wp_verify_nonce()` in handlers.
- **OAuth tokens:** Stored in WordPress options table (only accessible to admins). The `auth_json` field contains the full OAuth web client JSON. Treat as sensitive.
- **Email templates:** Sanitized via `wp_kses_post()` (allows safe HTML). Template placeholders (`[EVENT_SUMMARY]`, `[MEETING_URL]`, etc.) are replaced server-side before sending.

### Naming

- PHP classes: `WGM\` namespace, PascalCase.
- WordPress options: `wgm_` prefix, snake_case keys within arrays.
- Post meta keys: `_wgm_` prefix (underscore = hidden from custom fields UI).
- Cart/order item meta: `wgm_event_id`, `_wgm_event_id`, `_wgm_reserved_event_id`.
- JS globals: `WGM_AVAIL` (localized via `wp_localize_script`).

## Build & Install

### Development

```bash
# Start Docker environment
docker-compose up -d

# Install dependencies
php composer.phar install

# The plugin is mounted at wp-content/plugins/woo-gmeet
```

### Production Build

```bash
./build.sh
# Output: build/woocommerce-meetings.zip
```

The build script copies `woo-gmeet.php`, `includes/`, `assets/`, `vendor/` into `build/woocommerce-meetings/` and zips it. Does NOT include dev dependencies (php-stubs) — those are require-dev only.

### WordPress Dependencies

- WooCommerce must be active (declared via `Requires Plugins` header).
- Google API client is bundled via Composer (vendor directory must be included in build).

## Known Considerations

1. **REST endpoint is public:** `wc-gmeet/v1/availability` has no authentication. Required for frontend event picker. Consider rate-limiting or caching if abused.
2. **Google API calls on admin_init:** `Settings::register()` calls `GoogleClient::getCalendarsList()` and `getCalendarColors()` which make live API calls. If not authenticated, they silently return empty arrays. Settings pages may load slowly.
3. **Token refresh race condition:** If two requests simultaneously detect an expired token, both may attempt refresh. The Google client library handles this at the HTTP level but it's worth noting.
4. **Calendar prefix filtering:** Events whose summary starts with the configured `prefix` are excluded from availability (treated as existing reservations). Ensure the prefix is unique enough to avoid false positives.
5. **Event deletion during reservation:** The original event is deleted from the source calendar and recreated in the reservations calendar. If the insert fails after deletion, the slot is lost. The code does not roll back the deletion.

## Auto-Update Clause

**Future AI agents working on this codebase MUST keep this AGENTS.md current.** Update this file whenever you:

- Add, remove, or rename files/directories
- Add or upgrade dependencies (composer.json changes)
- Introduce new architectural patterns or deviate from existing ones
- Add new WordPress hooks, REST endpoints, or shortcodes
- Change the settings storage schema or option keys
- Discover undocumented behavior, edge cases, or gotchas
- Modify the build/deploy process
- Add new capability requirements or change the security model

If a change is structural (new class, new integration, new data flow), add a section describing it. If a change is incremental (new hook, new option key), update the relevant table. Keep the document accurate over time — stale documentation is worse than none.
