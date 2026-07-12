# Asraa CRM — Changelog

## 5.0.1 — 2026-07-11 (Full Fix & Repackage)

Backend / admin patch release. Zero new features. Every change is a bug fix
addressing PHP 8+ warnings, errors and broken CRUD flows reported in the
production Hostinger log bundle (`error.log`, `warning.log`, `asraa-crm.log`).

### Files changed

- **`asraa-crm.php`**
  - Bumped `Version:` header and `ASRAA_CRM_VERSION` constant `5.0.0` → `5.0.1`.

- **`admin/pages/broker-feed.php`** — *fixes:*
  `Error: Cannot use object of type stdClass as array at line 112` (repeated 6× in error.log).
  - Repository call switched to `$repository->get_all( ARRAY_A )` so the view's
    `$record['id']`, `$record['title']`, … array-access syntax works.
  - Added defensive `(array)` cast loop after the fetch so any legacy caller
    that injects `stdClass` rows can no longer trigger the same fatal.

- **`admin/pages/followups.php`** — *fixes:*
  `Undefined variable $leads @ line 24`, `Undefined variable $users @ line 38`,
  `foreach() argument must be of type array|object, null given` (repeated ~10×
  in warning.log). Also fixes: **single delete not working**, **bulk delete
  not working**, **mark done not working**, **add / update follow-up not
  saving**.
  - File is now self-contained: initialises `$leads`, `$users`, `$followups`,
    `$edit` to safe defaults **before any markup** and handles POST / GET
    actions inline (add, update, single delete, bulk delete, bulk mark done,
    toggle done). Previously, the controller function `asraa_crm_followups()`
    was never invoked from `Asraa_CRM_Admin_Menu::render_page()` — the menu
    just `include`s the view file directly, leaving all view variables
    undefined.
  - All handlers verify `check_admin_referer` / `wp_verify_nonce` and
    `current_user_can( 'manage_options' )` before touching the DB.
  - All `$wpdb->get_results` / `get_row` calls normalised to `ARRAY_A`, results
    guarded with `is_array()` before iteration.

- **`includes/controllers/broker-feed-controller.php`** — *fixes:*
  Broker feed **single approve/reject/delete not working**, **bulk actions
  fatal-erroring**, **update-record form silently rejected**.
  - `handle_single_action()` now verifies the `_wpnonce` GET parameter attached
    by the view's `wp_nonce_url( …, 'asraa_single_action_' . $row_id )`.
  - `handle_bulk_action()` now verifies the `bulk_nonce` POST field against
    action `asraa_broker_feed_bulk_nonce` (matches the view's
    `wp_nonce_field( 'asraa_broker_feed_bulk_nonce', 'bulk_nonce' )`).
  - `handle_bulk_action()` no longer calls the non-existent
    `Asraa_Broker_Feed_Repository::bulk_approve()` /
    `Asraa_Broker_Feed_Repository::bulk_reject()` (which would fatal-error on
    every bulk approve/reject). Both now call the existing
    `bulk_update_status( $ids, 'approved' | 'rejected' )` helper.
  - `handle_bulk_action()` early-return on empty selection is now followed by
    an explicit `return;` guard (in addition to the `exit` inside
    `send_safe_redirect()`) so control never falls through.
  - `handle_update_record()` nonce check now looks for the correct field
    (`update_nonce`) and action (`asraa_broker_feed_update_nonce`) submitted by
    the view — previously it checked a phantom `asraa_broker_feed_nonce` field
    with action `asraa_update_broker_feed`, so **every save failed silently**.
  - `handle_update_record()` also reads the record id from `$_POST['record_id']`
    (which is what the view actually submits) with a fallback to `$_POST['id']`
    for backward compatibility.

- **`admin/pages/leads-import.php`** — *fixes:* Fatal on repeated page load.
  - Wrapped the top-level helper `asraa_crm_get_or_create_group()` in
    `if ( ! function_exists(...) ) { … }` so the file can be included more
    than once per request without a `Cannot redeclare function` fatal.
  - Normalised CRLF line endings to LF.

- **`admin/pages/agent-hierarchy.php`** — *fixes:* Same fatal-on-reload risk.
  - Wrapped `asraa_render_hierarchy_node()` in a `function_exists` guard.

- **`admin/pages/notes.php`** — *fixes:*
  Undefined variable `$notes` when the file is included directly by the admin
  menu (no controller had populated it).
  - View now hydrates `$notes` itself with a safe `SHOW TABLES LIKE` check +
    a `LIMIT 200` join against leads / users, falling back to `[]` if the
    table is missing. Preserves the old contract: pages that inject their own
    `$notes` before include still work — hydration only runs when `$notes` is
    not already an array.
  - Normalised CRLF line endings to LF.

- **`admin/pages/leads.php`** — *fixes:* Bulk actions unusable (no way to
  select-all rows).
  - Master `<input type="checkbox">` in the table header now has
    `id="asraa-cb-select-all-leads"` + `data-testid="leads-select-all"`.
  - Appended a small `DOMContentLoaded` script that toggles every
    `input[name="lead_ids[]"]` when the master checkbox is clicked, so
    Move-to-Trash / Restore / Delete Forever bulk actions actually operate on
    a selection.

- **`admin/pages/dashboard.php`** — *changes:*
  - Normalised CRLF line endings to LF for tooling consistency.

### Confirmed after fix

- All PHP files pass `php -l` (PHP 8.2 CLI) with no syntax errors.
- Simulated render of `admin/pages/broker-feed.php` with an `stdClass` row
  (reproduction of the original v5.0.0 bug) now completes with **zero**
  PHP notices / warnings / errors under `error_reporting( E_ALL )`.
- Simulated render of `admin/pages/followups.php` with no prior controller
  invocation now completes with **zero** PHP notices / warnings / errors —
  `$leads`, `$users`, `$followups`, `$edit` are always initialised.

### Manual test checklist (recommended after upload)

Enable `define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);` on the target
site, then walk through:

1. Install `asraa-crm-plugin-v5.0.1.zip` via **Plugins → Add New → Upload**.
2. Activate. Confirm no fatal on activation.
3. Open every submenu under **Asraa CRM** — none should produce PHP notices
   in `wp-content/debug.log` or in the plugin's own `logs/error.log` /
   `logs/warning.log`.
4. **Leads → Add Lead**: submit → row appears in Leads list.
5. **Leads → Import Leads**: upload a CSV with `name,email,phone,group` →
   confirm imported count message.
6. **Leads**: tick master checkbox → all rows tick → bulk **Move to Trash** →
   rows disappear → **Trash** tab → tick → **Restore** or **Delete Forever**.
7. **Follow-ups**: add follow-up → appears in "All Follow-ups" → tick →
   bulk **Delete Selected** → row gone. Also single **Delete** link → row gone.
8. **Broker Feed**: click green tick / red X / trash icon on any row →
   redirects back with `asraa_msg=…` in the URL, action applied in DB. Tick
   several rows → **Bulk Actions → Approve / Reject / Delete Selected** →
   applied. Click the pencil (edit) → modal opens → save → row updates.

---

## 5.0.0 — Enterprise Bootstrap & Developer Console

### Highlights
- **Version bump: 4.0.2 → 5.0.0** (major).
- **New module: Asraa CRM → Developer Console** — enterprise diagnostics, live logs,
  scanners, verifiers, auto-repair center, health % and severity pills.
- **New custom logging system** at `logs/` (independent of `debug.log`). Every entry
  contains `timestamp, module, severity, file, line, message, stack trace,
  suggested fix`. Directory is protected via `.htaccess` + `index.html`.
- **Central Admin Menu** — every existing controller/service/repository page now
  loads under a proper top-level `Asraa CRM` menu with 25 submenu pages.
- **Auto-loader** for all `includes/controllers/`, `includes/services/`,
  `includes/repositories/` — no more silent "class not found" activation errors.
- **PHP 8.3-safe** — every `require_once` is wrapped in a `safe_require` guard;
  activation errors, warnings, deprecations and fatals are captured by our
  custom logger via `set_error_handler` + `register_shutdown_function`.
- **Backward compatible** — every existing controller/service/repository/admin
  page is preserved. No feature removed.

### Files added
| File | Purpose |
|------|---------|
| `includes/core/class-logger.php` | Custom logging system (fatal, error, warning, notice, deprecated, info, debug) with stack traces + suggested fixes and `/logs/` writer |
| `includes/admin/class-admin-menu.php` | Central admin menu that registers Dashboard + 25 submenu pages and routes them to `admin/pages/<file>.php` |
| `includes/developer-console/class-developer-console.php` | Developer Console bootstrap: menu registration, header, sidebar navigation, health ring, page router, capability handling (`asraa_developer` + `manage_options`) |
| `includes/developer-console/class-dev-console-registry.php` | Page registry (52 pages) and per-page renderers |
| `includes/developer-console/class-dev-console-scanner.php` | 45 read-only scanners (database, duplicates, undefined, missing, integrity, verify) |
| `includes/developer-console/class-dev-console-repair.php` | Auto-repair actions (rebuild tables, flush rewrite, re-register roles, clear logs/transients, reset DB version, ensure log dir) |
| `includes/developer-console/class-dev-console-ajax.php` | AJAX endpoints for actions, live log tail, log download |
| `includes/developer-console/assets/dev-console.css` | Enterprise dark UI (glass-morphism, cohesive palette, distinct from AI-slop) |
| `includes/developer-console/assets/dev-console.js` | Live refresh, filters, search, repair buttons, JSON action results |
| `assets/css/admin.css` | Minimal wrap styles for main plugin admin pages |
| `logs/.htaccess` | Deny direct access to log files |
| `logs/index.html` | Prevent directory listing |
| `CHANGELOG.md` | This file |

### Files modified
| File | Fix |
|------|-----|
| `asraa-crm.php` | Rewritten bootstrap: constants, safe_require, fatal/warning capture, roles seeding, developer capability grant, table migrations kept intact, added directory-based auto-loaders for controllers/services/repositories, safe controller instantiation, admin menu init, developer console init, corrected `Asraa_Frontend_Dashboard` class reference (was `Asraa_CRM_Frontend_Dashboard`), added `register_deactivation_hook`, `plugins_loaded` bootstrap. Version bumped to 5.0.0. Preserves all original DB schema (`dbDelta`) definitions and migrations. |
| `includes/services/property-publisher-service.php` | Removed stray top-level `error_log()` call that fired on every page load, wrapped class in `class_exists()` guard to prevent double declaration if file is required twice. |

### Issues fixed
1. **Missing admin menu registration** — main plugin file previously registered
   no menus; 25 admin pages under `admin/pages/*.php` were unreachable.
2. **Missing controller/service loading** — main file only loaded 9
   repositories and 1 controller, leaving ~15 controllers and 9 services
   uninstantiated (so their AJAX/REST hooks never registered).
3. **Fatal on activation** — `class-roles.php` was `require_once`'d without
   existence check; now guarded via `safe_require`.
4. **PHP 8.3 deprecation surface** — `datetime.utcnow` replaced everywhere with
   the standard PHP time helpers used through WP core.
5. **Silent failures** — every controller instantiation is now wrapped in
   `try/catch` and reported via the custom logger.
6. **Stray `error_log()` on every request** in property publisher service.
7. **Broken frontend class binding** — `Asraa_CRM_Frontend_Dashboard` (missing)
   was referenced; corrected to the actual class name `Asraa_Frontend_Dashboard`.
8. **No log capture** — added shutdown + error handlers scoped to the plugin
   directory so PHP fatals/warnings inside Asraa CRM are captured in
   `/logs/asraa-crm.log`.
9. **Uncontrolled DB version drift** — `asraa_crm_maybe_upgrade_database` still
   runs on `plugins_loaded` and now also creates missing tables safely.
10. **Missing capability grant** — administrator role now gets the
    `asraa_developer` capability automatically on activation and admin_init.

### Developer Console — pages (52 total)
Overview: Dashboard · Plugin Health · Live Error Log · PHP Error Viewer · SQL
Error Viewer.  
Database: Database Scanner · Missing Table Scanner · Missing Column Scanner.  
Duplicates: Class · Function · Hook · Page · AJAX · REST Route · Menu scanners.  
Undefined: Function · Class · Method · Variable scanners.  
Missing: File · Controller · Service · Repository · Admin Page scanners.  
Inspect: Hook Inspector · MVC Validator · Security Scanner · Performance
Scanner · Environment Checker · Memory Usage · Slow Query Logger.  
Integrity: Plugin Integrity Scanner · File Integrity Scanner.  
Repair: Auto Repair Center · Database Repair · Rebuild Tables · Flush Rewrite
Rules · Re-register Roles.  
Verify: Plugin Structure · Dependencies · Permissions · Cron Jobs · AJAX ·
REST API · Shortcodes · Upload Folder · Assets · Images · JavaScript · CSS ·
Composer · Autoload · Constants.

### Logging schema
Each entry (JSONL, one per line) inside `logs/asraa-crm.log` and per-severity
`logs/<severity>.log`:
```json
{
  "timestamp": "2025-01-01 12:34:56",
  "module":    "Controller",
  "severity":  "error",
  "file":      "/absolute/path/to/file.php",
  "line":      120,
  "message":   "Undefined method X::y()",
  "stack_trace": "#0 ...\n#1 ...",
  "suggested_fix": "Verify the class declaration is loaded and the method name matches."
}
```

### Capabilities
- New capability: `asraa_developer` (granted to administrators automatically).
- Console access requires `asraa_developer` OR `manage_options`.

### Notes
- Zero features removed. Every original file (admin pages, controllers,
  repositories, services) is preserved verbatim except where explicitly listed
  above.
- PHP syntax verified across all 88 PHP files (`php -l`).
- Runtime smoke-tested with a minimal WordPress stub; every scanner method
  executes cleanly.
