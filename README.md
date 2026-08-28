# pollora/hidden-login

Serves the WordPress login screen from a secret URL, and answers `wp-login.php`
and `wp-admin/` with a genuine 404.

Unlike the usual "rename wp-login" plugins, this one does not reimplement the
login screen: it includes the WordPress file itself from the secret URL. Every
authentication flow — password recovery, two-factor, single sign-on, privacy
request confirmation — therefore keeps working exactly as WordPress and your
plugins intend, at a different address.

## Installation

```bash
composer require pollora/hidden-login
```

That is the whole installation. The package registers itself through Composer's
`autoload.files`, which on a WordPress installation runs from `wp-config.php` —
well before `add_action()` even exists. It therefore schedules its own boot on
`muplugins_loaded` through WordPress' pre-initialised hook array, the mechanism
`wp-settings.php` normalises with `WP_Hook::build_preinitialized_hooks()`. No
must-use plugin, no service provider, no call to add anywhere.

Hosts that need control over the moment, or want to inject their own adapters,
can still call the composition root explicitly — it is idempotent, so doing both
is harmless:

```php
\Pollora\HiddenLogin\HiddenLogin::boot();
```

## Configuration

Two values, read from a PHP constant first and from the environment as a
fallback:

```dotenv
HIDDEN_LOGIN_SLUG=acces-prive
HIDDEN_LOGIN_ENABLED=false   # optional kill switch, enabled by default
```

`HIDDEN_LOGIN_ENABLED` switches the package off entirely, even when a slug is
configured — useful when an environment inherits a shared `.env`. It defaults to
enabled: an installation that pulled the package in has opted in, so turning it
off has to be deliberate. An unrecognised value counts as enabled, because a
typo must not silently drop a security control.

On Bedrock, expose it as a constant in `config/application.php`:

```php
Config::define('HIDDEN_LOGIN_SLUG', env('HIDDEN_LOGIN_SLUG') ?: null);
```

The slug must be a single URL segment: lowercase letters, digits, hyphens and
underscores, at least 5 characters, not one of the paths WordPress already owns.

**Nothing is stored in the database, on purpose.** An option would travel with
production dumps restored on staging and local machines, where it would either
leak the production secret or lock developers out.

**No slug means no interception.** The package stays completely dormant, and
WordPress behaves as it always did. That fail-open is deliberate: a freshly
provisioned environment or a missing `.env` entry must never lock everybody out
of an installation nobody can reach a terminal on.

## Behaviour

| Request | Response |
| --- | --- |
| `/<slug>` | The native login screen, with every `action` it supports |
| `wp-login.php` | 404, for everyone |
| `wp-admin/*`, anonymous | 404, emitted **before** `auth_redirect()` could leak the slug |
| `wp-admin/*`, authenticated | Untouched |
| `wp-admin/admin-ajax.php`, `admin-post.php` | Untouched — the public site depends on them |
| WP-CLI, WP-Cron | Untouched |

The following keep working through the secret URL, because WordPress builds all
of them with `site_url()` / `network_site_url()` and the `login` scheme:

`?action=lostpassword` · `?action=rp` and `?action=resetpass` (the link in the
reset email) · `?action=logout` · `?action=register` · `?action=postpass`
(password-protected posts) · `?action=confirmaction` (privacy requests) ·
`?checkemail=confirm|registered` · `interim-login=1` (the expired-session modal)
· the `wp_new_user_notification` email.

## Recovery

WP-CLI is never intercepted — it is the way back in when the slug is lost:

```bash
wp hidden-login url      # prints the effective login URL
wp hidden-login status   # prints what is currently enforced
```

## Extension points

| Hook | Purpose |
| --- | --- |
| `hidden_login/allowed_default_actions` | Actions still tolerated on `wp-login.php`, for third-party code that posts to it with a hard-coded URL. Empty by default. |
| `hidden_login/public_admin_scripts` | `wp-admin/` scripts that stay reachable anonymously. `admin-ajax.php` and `admin-post.php` by default. |
| `hidden_login/render_theme_404` | Set to `false` to answer blocked requests with a minimal document instead of the theme's 404 template. |

To read the slug from somewhere else — an option, a settings screen, a secret
manager — implement `SlugProviderPort` and inject it:

```php
\Pollora\HiddenLogin\HiddenLogin::boot(new MyOptionSlugProvider());
```

The same applies to the kill switch (`FeatureTogglePort`) and to the hook system
(`HookRegistrarPort`).

## Hook system

The package never calls `add_action()` or `apply_filters()` itself. Hook
registration sits behind `HookRegistrarPort`, with two implementations picked at
runtime:

- `PolloraHookRegistrar` — used when `Pollora\Support\Facades\Action` and
  `Filter` are loadable **and** the facade container is set, so that hooks take
  part in the framework's own lifecycle instead of bypassing it.
- `WordPressHookRegistrar` — the plain plugin API, used everywhere else.

Pollora is not a dependency: the adapter is only autoloaded once
`PolloraHookRegistrar::isAvailable()` says so, which keeps the package installable
on a bare Bedrock site and its PHP floor at 8.1.

The single exception is `Bootstrap`, which by definition runs before either
implementation could work.

## Architecture

Hexagonal, following the Pollora layout. The Domain and Application layers
contain no WordPress call at all, which is what makes the security-critical
decisions unit-testable without booting WordPress.

```
src/
├── Domain/              LoginSlug, RequestPath, DefaultEndpoint, FeatureState — pure value objects
├── Application/         ResolveLoginSlug, MatchHiddenLoginRequest,
│                        GuardDefaultEndpoints, RewriteLoginUrl — pure decisions
├── Port/Out/            SlugProvider, FeatureToggle, HookRegistrar, RequestContext,
│                        LoginScreenRenderer, NotFoundResponder
├── Adapter/In/          Router, URL rewriter, admin notice, WP-CLI command
├── Adapter/Out/WordPress/  Plugin API, superglobals, wp-login.php, theme 404
├── Adapter/Out/Pollora/    Hook registrar backed by the framework's facades
├── Bootstrap.php        Composer self-registration
└── HiddenLogin.php      Composition root
```

### Why two hooks

The order of `wp-settings.php` dictates the split:

- **`plugins_loaded`, priority 1** is the earliest point where
  `is_user_logged_in()` is usable (`pluggable.php` is loaded just above), and it
  is still early enough to rewrite the request environment before any plugin
  reads it. It also runs *before* `wp-admin/admin.php` reaches
  `auth_redirect()`, which would otherwise redirect anonymous visitors straight
  to the secret URL.
- **`wp_loaded`, last priority** is where the response is produced. Rendering
  needs `$wp`, `$wp_query` and `$wp_rewrite`, which only exist after
  `plugins_loaded`, and running *last* is what reproduces the native ordering:
  `wp-blog-header.php` calls `wp()` once `wp_loaded` has fully completed, and
  `wp-login.php` renders once `wp-load.php` has returned. Rendering earlier
  skips whatever registers on `wp_loaded` — on a Sage theme, Acorn would not
  have bound its `template_include` filter yet and the 404 template fatals
  instead of rendering.

### Three details that are load-bearing

- **The canonical request path has no trailing slash.** The password reset
  screen scopes its `wp-resetpass-*` cookie on the current request path, while
  the form it renders posts to the rewritten URL. A slash on one side only makes
  the cookie invisible to the POST, and the reset fails with an "expired link"
  error that is close to impossible to diagnose.
- **`wp_redirect` is filtered, not just `site_url`.** `wp-login.php` redirects to
  *relative* locations in several branches —
  `wp_safe_redirect( 'wp-login.php?checkemail=confirm' )` after a lost password
  request is the one users hit first. Left alone, the browser resolves it
  against the secret slug and lands on `/<slug>/wp-login.php`.
- **The blocked request keeps its own path.** Rendering the 404 against a decoy
  path would be simpler, but WordPress echoes the current URL into the page —
  a login link's `redirect_to`, for instance — so the decoy would show up in the
  markup and hand a scanner exactly the signal this package exists to withhold.

### Known caveat

Blocked `wp-admin/` requests are rendered with `WP_ADMIN` already defined — the
admin bootstrap sets it before WordPress is even loaded, so it cannot be undone.
`is_admin()` is therefore `true` while the theme's 404 template renders, with two
consequences:

- The admin bar is unhooked explicitly. `is_admin_bar_showing()` returns `true`
  *unconditionally* under `is_admin()` — it never reaches the `show_admin_bar`
  filter — and the bar then fatals on a `null` `get_current_screen()`, because
  the administration bootstrap is interrupted long before `set_current_screen()`
  runs. Unhooking the `default-filters.php` callbacks is the only lever that
  works.
- Plugins that skip front-end output under `is_admin()` do not contribute. With
  Yoast SEO, for instance, the `wp-admin/` 404 has a plain `<head>` where a
  front-end 404 carries the SEO meta. The page renders correctly and returns
  404, but it is not byte-identical to a front-end 404 the way the
  `wp-login.php` one is.

If a theme or plugin misbehaves in that context, opt out with
`hidden_login/render_theme_404`.

## Quality

```bash
composer test          # pest + phpstan + pint --test
composer test:unit
composer phpstan
composer lint
```
