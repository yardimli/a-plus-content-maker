# A+ Content Maker — Product, UI, and Data Blueprint

## 1. Purpose and product boundary

A+ Content Maker is a Laravel 10 web application for authors and publishers who want to plan, write, illustrate, preview, and export Amazon KDP A+ Content outside Amazon's editor.

The application is an authoring assistant, not an Amazon publishing integration. It should help users reproduce content in KDP by presenting the correct module structure, image dimensions, copy, alt text, and an export/copy workflow. It must not imply that it can publish to Amazon or guarantee Amazon approval.

Primary outcomes:

- Discover a book by ASIN and use its metadata as project context.
- Begin from an admin-curated template or from a blank canvas.
- Compose a page from Amazon-style modules.
- Upload or AI-generate images and generate/edit copy.
- Preview the result at desktop and mobile widths.
- Autosave without full-page reloads.
- Export a structured content package that is easy to transfer into KDP.

## 2. Technical constraints

- Laravel 10 and PHP 8.1+.
- Laravel Breeze authentication, installed with the Blade stack.
- Breeze views must be rewritten as conventional Blade/HTML. Do not use `<x-...>` Blade components anywhere.
- Tailwind CSS compiled with Vite; production assets must build with `npm run build`.
- Vanilla JavaScript only for the application UI. Use `fetch` for AJAX; no Vue, React, Alpine, Livewire, jQuery, or client framework.
- MySQL/MariaDB-compatible migrations (SQLite should remain usable for automated tests).
- Secrets are server-side environment values and are never rendered into Blade, JSON boot data, or browser requests.
- User-authored rich text is sanitized on the server with a small allowed tag set.

## 3. Roles and permissions

### Guest

- View the marketing landing page and public template examples.
- Register, log in, request/reset a password.

### User

- Manage only their own projects, project modules, assets, exports, and AI generations.
- Browse active public templates and create a project from a template.
- Start a blank project.
- Look up an ASIN through the application's server-side proxy.
- Upload assets, generate copy/images, preview, and export.

### Admin

- All normal user capabilities.
- Create, edit, preview, publish/unpublish, feature, duplicate, and archive templates.
- Define template metadata, cover/preview art, modules, starter copy, and categories.
- View users and project/AI usage summaries; suspend access if later required.

Authorization must use policies and route middleware, not UI hiding alone. A project template is copied into a user's project at creation time so later admin edits do not mutate existing user projects.

## 4. Screenshot analysis

The screenshots show a module-based editor with a consistent interaction model.

### Shared builder behavior

- `Editor` and `Preview` tabs.
- Blank canvas with a central `Add Module` action.
- Full-screen searchable module gallery with visual thumbnails.
- Module cards with a title, optional `AI Ready` badge and info tooltip.
- Module actions: move up, move down, and remove.
- Per-module `Select content to generate` action where AI generation is supported.
- Text fields include headline, subheadline, image caption, bullet point, specification/definition, title, and ASIN.
- Rich text fields support bold, italic, underline, unordered list, and ordered list.
- Image placeholders show required pixel dimensions and whether the image is required.
- Image modal supports drag/drop or file selection, preview, alt text, replace/remove, cancel, and confirm.
- Repeatable fields include bullet points, technical specifications, comparison products, comparison metrics, and multiple image thumbnails.
- Comparison module includes two to six products, up to ten metrics, a highlighted column, and visibility switches for reviews, prices, and add-to-cart indicators.
- Technical specifications offer one- or two-column presentation and require four to sixteen rows.

### Module catalog inferred from the screenshots

| Key | Display name | Main field/layout contract | AI ready |
|---|---|---|---|
| `company_logo` | Standard Company Logo | One 600×180 image with alt text | No |
| `comparison_chart` | Standard Comparison Chart | 2–6 product columns, 150×300 images, ASIN/title/highlight, display toggles, 1–10 metrics | No |
| `four_image_text` | Standard Four Image & Text | Optional module headline plus four 220×220 image/headline/body groups | Yes |
| `four_image_quadrant` | Standard Four Image/Text Quadrant | Four 135×135 image/headline/body groups in a 2×2 layout | Yes |
| `dark_text_overlay` | Standard Image & Dark Text Overlay | Background image plus right-side headline/body with dark text treatment | Yes |
| `light_text_overlay` | Standard Image & Light Text Overlay | Background image plus right-side headline/body with light text treatment | Yes |
| `image_header_text` | Standard Image Header With Text | Headline, 970×600 hero image, lower headline/body | No |
| `multiple_image_a` | Standard Multiple Image Module A | One active 300×300 image, headline/description, four selectable images/captions | Yes |
| `product_description_text` | Standard Product Description Text | One large rich-text field | No |
| `single_image_highlights` | Standard Single Image & Highlights | 300×300 image, three subheadline/body sections, highlight headline/bullets | Yes |
| `single_image_sidebar` | Standard Single Image & Sidebar | 300×400 primary image/caption, main headline/subheadline/body/bullets, 350×175 sidebar image/headline/body/bullets | Yes |
| `single_image_specs_detail` | Standard Single Image & Specs Detail | Module headline, 300×300 image, multiple headline/subheadline/body or bullet regions | Yes |
| `single_left_image` | Standard Single Left Image | Required 300×300 image left, headline/body right | Yes |
| `single_right_image` | Standard Single Right Image | Headline/body left, required 300×300 image right | Yes |
| `technical_specifications` | Standard Technical Specifications | Optional headline, 4–16 specification/definition pairs, one/two-column layout | No |
| `standard_text` | Standard Text | Optional headline and required rich body | Yes |
| `three_images_text` | Standard Three Images & Text | Optional module headline plus three 300×300 image/headline/body groups | Yes |

The module gallery should use these definitions as the initial built-in catalog. Template gallery items are different: a template is a complete ordered collection of configured modules for a genre or campaign.

## 5. Information architecture and UI

### Public landing page

Visual direction: editorial and book-oriented, using a high-contrast serif display face for headings and a clean sans-serif face for controls/body copy. Suggested palette: warm paper, ink/navy, muted burgundy or amber accents, and restrained shadows.

Sections:

1. Header: wordmark, Templates, How it works, Examples, Log in, and `Start building`.
2. Hero: clear KDP A+ value proposition, primary registration CTA, secondary template CTA, and a layered builder mockup.
3. Problem/solution: explain how the tool turns scattered copy and imagery into reusable modules.
4. Three-step flow: find book by ASIN, choose template/build modules, export and transfer to KDP.
5. Example showcase: fiction, nonfiction, children's, series, and author-brand examples.
6. Module/tool highlights: drag-free ordered modules, image specifications, AI assistance, live preview, autosave.
7. Template gallery preview with genre filters.
8. Trust/clarity note: independent tool; not affiliated with Amazon; users publish manually in KDP.
9. Final CTA and full footer with privacy/terms/contact placeholders.

### Authentication

- Split-screen or centered editorial card design with book-spine/folio motifs.
- Restyle login, registration, forgot-password, reset-password, email verification, and confirm-password views.
- Conventional `<form>`, `<label>`, `<input>`, and error markup only; no Blade component tags.
- Keep keyboard focus, error announcements, password manager semantics, and responsive behavior.

### Authenticated application shell

- Desktop: slim left navigation plus top bar. Mobile: off-canvas navigation built with vanilla JS.
- Navigation: Dashboard, My Projects, Templates, Assets, Exports; Admin section when authorized.
- Top bar: ASIN quick lookup, help, user menu.
- Dashboard: greeting, `New project`, recent projects, completion status, last saved time, and quick templates.

### New project flow

1. Choose `Use a template` or `Start fresh`.
2. Search an ASIN (optional but encouraged).
3. Display the returned cover, title, price/rating context, and product link; user confirms the match.
4. Enter project name, marketplace (initially `amazon.com`), genre/category, author/brand name, tone, and audience.
5. Choose a template if applicable and create the project.

An ASIN lookup must never overwrite unsaved work without confirmation. Store a metadata snapshot because third-party data can change or become unavailable.

### Template gallery

- Search by name/description; filters for genre/category, featured, and module count.
- Cards show preview image, title, short description, category tags, module count, and `Preview`/`Use template` actions.
- Preview opens a read-only desktop/mobile rendering plus module list.
- Empty and error states are designed, not browser alerts.

### Builder layout

Desktop uses a three-part workspace:

- Top sticky bar: back, editable project name, book context, save state, undo/redo (phase 2), preview, and export.
- Left rail: `Add module`, module outline with ordered titles, and assets.
- Main canvas: ordered edit cards matching the screenshots.
- Optional right inspector/drawer: project context, AI generation controls, and validation issues.

On small screens, rails become drawers and module cards use a single-column layout. The editor must remain usable, but exact image composition is best validated in desktop preview.

Each module card provides:

- Human-readable module title and AI capability badge.
- Move up/down and remove with confirmation/undo toast.
- Validation badges and field-specific errors.
- Image selectors with exact dimensions and preview.
- Rich text controls implemented via `contenteditable` or a small local editor wrapper; HTML is normalized and sanitized before storage.
- Debounced autosave. The UI shows `Saving…`, `Saved`, `Offline`, or `Save failed`.

### Preview and export

- Preview renders from the same normalized module data as the editor.
- Desktop and mobile width toggles.
- Validation panel identifies missing required fields, dimensions, alt text, count limits, and copy limits.
- Export generates a ZIP containing normalized images, a project manifest JSON, copy sheet (HTML or printable view), and a transfer checklist. A PDF contact sheet can be a later enhancement.
- Provide field-level copy buttons and `Copy all copy` in addition to the ZIP.
- Do not claim to create importable KDP HTML; the package is a transfer aid.

### Admin template studio

- Template index with draft/published/archived states.
- Create/edit metadata and preview art.
- Reuse the normal builder in `template mode` for composing starter modules.
- Publish validation requires title, slug, preview image, at least one module, and valid module data.
- `Duplicate` creates a draft copy. Archiving is preferred to hard deletion when a template has usages.

## 6. Data model

Use standard Laravel timestamps on all tables unless explicitly unnecessary. JSON is appropriate for module payloads because every module has a different field contract; relational tables should retain ownership, ordering, assets, API usage, and lifecycle data.

### `users`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string unique | |
| `email_verified_at` | timestamp nullable | |
| `password` | string | Hashed |
| `role` | enum/string | `user`, `admin`; default `user`, indexed |
| `is_active` | boolean | Default true |
| `remember_token` | string nullable | |

### `templates`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `created_by` | FK users | Admin owner |
| `name` | string | |
| `slug` | string unique | |
| `summary` | string nullable | Card copy |
| `description` | text nullable | Detail copy |
| `category` | string nullable/indexed | Initial simple taxonomy |
| `tags` | json nullable | Genre/use-case tags |
| `preview_asset_id` | FK assets nullable | Gallery image |
| `status` | enum/string | `draft`, `published`, `archived` |
| `is_featured` | boolean | |
| `published_at` | timestamp nullable | |

### `template_modules`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `template_id` | FK templates cascade | |
| `module_type` | string/indexed | One of the catalog keys |
| `position` | unsigned int | Unique with `template_id` |
| `content` | json | Validated module payload |
| `settings` | json nullable | Presentation flags/layout variant |

### `projects`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade/indexed | Owner |
| `source_template_id` | FK templates nullable/null-on-delete | Provenance only |
| `name` | string | |
| `slug` | string | Unique per user, or use UUID route key |
| `status` | enum/string | `draft`, `ready`, `archived` |
| `marketplace` | string | Default `amazon.com` |
| `asin` | string(10) nullable/indexed | Normalized uppercase |
| `product_snapshot` | json nullable | API response subset + lookup time |
| `author_name` | string nullable | AI/project context |
| `genre` | string nullable | |
| `audience` | string nullable | |
| `tone` | string nullable | |
| `brand_notes` | text nullable | |
| `last_saved_at` | timestamp nullable | |

### `project_modules`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Prefer UUID exposed to JS |
| `project_id` | FK projects cascade/indexed | |
| `module_type` | string/indexed | Catalog key |
| `position` | unsigned int | Unique with `project_id` |
| `content` | json | Module-specific field data |
| `settings` | json nullable | Layout toggles/active indexes |

### `assets`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade/indexed | Owner; admin assets can back templates |
| `project_id` | FK projects nullable/null-on-delete | Optional project grouping |
| `source` | enum/string | `upload`, `ai`, `asin`, `template` |
| `disk` | string | Storage disk |
| `path` | string | Never trust client-provided path |
| `original_name` | string nullable | |
| `mime_type` | string | Allow-list only |
| `extension` | string | |
| `size_bytes` | unsigned bigint | |
| `width` / `height` | unsigned int | Verified server-side |
| `alt_text` | string nullable | Required when assigned to required slot |
| `checksum` | string nullable/indexed | Deduplication/integrity |
| `metadata` | json nullable | Prompt/model/transform data |

### `module_assets`

This pivot keeps asset references relational while the content JSON stores only slot identifiers and text.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `project_module_id` | FK project_modules cascade | |
| `asset_id` | FK assets restrict/cascade by policy | |
| `slot` | string | e.g. `hero`, `items.0.image`, `background` |
| `position` | unsigned int nullable | For repeatable images |
| `crop_data` | json nullable | Future non-destructive crop/focal point |
| `alt_text` | string nullable | Slot-specific override |

Template assets can either use a parallel `template_module_assets` pivot or be duplicated into the new project during template instantiation. A parallel pivot is recommended for clean ownership.

### `asin_lookups`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users nullable/null-on-delete | |
| `asin` | string(10)/indexed | |
| `marketplace` | string | |
| `was_successful` | boolean | |
| `status_code` | unsigned smallint nullable | Provider response |
| `response_snapshot` | json nullable | Sanitized subset, no credentials |
| `error_code` | string nullable | Safe internal category |
| `expires_at` | timestamp nullable/indexed | Cache policy |

### `ai_generations`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade/indexed | |
| `project_id` | FK projects nullable/null-on-delete | |
| `project_module_id` | FK project_modules nullable/null-on-delete | |
| `kind` | enum/string | `text`, `image` |
| `model` | string | Server-selected env model |
| `prompt` | text | Consider retention/redaction policy |
| `request_payload` | json nullable | Must not contain keys |
| `response_payload` | json nullable | Sanitized provider result |
| `status` | enum/string | `pending`, `succeeded`, `failed` |
| `input_tokens` / `output_tokens` | unsigned int nullable | |
| `cost_estimate` | decimal nullable | |
| `error_message` | text nullable | Safe message |
| `completed_at` | timestamp nullable | |

### `exports`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users cascade | |
| `project_id` | FK projects cascade | |
| `status` | enum/string | `pending`, `processing`, `ready`, `failed`, `expired` |
| `format` | string | Initially `kdp_transfer_zip` |
| `disk` / `path` | strings nullable | Private storage |
| `size_bytes` | unsigned bigint nullable | |
| `manifest` | json nullable | Validation/version summary |
| `expires_at` | timestamp nullable | Signed download lifetime |

### Optional phase-2 tables

- `project_versions`: immutable snapshots for undo/history/restore.
- `activity_logs`: security/admin auditing.
- `usage_limits`: plans/quotas if monetization is added.
- `categories` and pivots: replace simple template/category strings if taxonomy becomes managed.

## 7. Canonical module payloads

All module payloads share an envelope:

```json
{
  "schema_version": 1,
  "headline": "Optional module headline",
  "fields": {},
  "repeaters": {},
  "options": {}
}
```

Examples:

```json
{
  "schema_version": 1,
  "headline": "Why readers love this world",
  "repeaters": {
    "items": [
      {
        "image_slot": "items.0.image",
        "headline": "A vast adventure",
        "body_html": "<p>Explore...</p>"
      }
    ]
  }
}
```

```json
{
  "schema_version": 1,
  "repeaters": {
    "products": [
      {
        "asin": "B099LJNCHQ",
        "title": "Backyard Starship",
        "image_slot": "products.0.image",
        "highlighted": true,
        "values": {"format": "Kindle", "series_order": "Book 1"}
      }
    ],
    "metrics": [
      {"key": "format", "label": "Format"},
      {"key": "series_order", "label": "Series order"}
    ]
  },
  "options": {
    "show_reviews": true,
    "show_prices": true,
    "show_add_to_cart": false
  }
}
```

Create a PHP module registry as the source of truth for:

- Name, description, category, and AI-ready flag.
- Field definitions, defaults, required status, copy limits, and repeat limits.
- Asset slots, required dimensions/aspect ratio, MIME types, and required alt text.
- Editor renderer key and preview renderer key.
- Server validation rules.

Expose only the safe registry subset to JavaScript. Never accept a client-provided module schema as authoritative.

## 8. Routes and server endpoints

Page routes return normal Blade views. JSON endpoints return a consistent envelope such as `{ "ok": true, "data": ..., "message": ... }` and validation errors with HTTP 422.

### Public/auth pages

- `GET /`
- Breeze auth/password/email-verification routes
- `GET /templates`
- `GET /templates/{template:slug}`

### Projects and builder

- `GET /dashboard`
- `GET|POST /projects`
- `GET|PATCH|DELETE /projects/{project}`
- `GET /projects/{project}/builder`
- `POST /projects/{project}/modules`
- `PATCH|DELETE /projects/{project}/modules/{module}`
- `POST /projects/{project}/modules/reorder` (single transaction, complete ordered UUID list)
- `POST /projects/{project}/assets`
- `PATCH|DELETE /assets/{asset}`
- `GET /projects/{project}/preview`
- `POST /projects/{project}/validate`
- `POST /projects/{project}/exports`
- `GET /exports/{export}/download` (authorized signed URL)

### Integrations and AI

- `POST /api/asin/lookup` with `{asin, marketplace}`.
- `POST /projects/{project}/ai/text` with module/field selection and user instructions.
- `POST /projects/{project}/ai/image` with target asset slot, prompt, and optional source asset.
- `GET /ai/generations/{generation}` for queued-job polling if generation is asynchronous.

### Admin

- `/admin/templates` resource pages and JSON module endpoints.
- Publish, unpublish, duplicate, archive, preview, and reorder actions.
- `GET /admin/users` and `GET /admin/usage` can follow after the core template studio.

Use CSRF tokens, authenticated session cookies, ownership policies, rate limiting, and idempotency where repeat submission is expensive.

## 9. ASIN integration

Server-side service: `AmazonProductDataService`.

Flow:

1. Normalize and validate ASIN as 10 uppercase alphanumeric characters.
2. Check a short-lived database/cache snapshot.
3. Laravel HTTP client calls `https://amazon-product-data-api.p.rapidapi.com/product/{asin}`.
4. Send `Content-Type`, `x-rapidapi-host`, and `x-rapidapi-key` from `config/services.php`.
5. Apply connect/request timeouts, limited retries only for transient errors, and per-user/IP throttling.
6. Map the provider response to an internal DTO containing only ASIN, title, price, rating, review count, features, image URL, and product URL.
7. Return a safe error state for not found, throttled, provider unavailable, malformed response, and configuration missing.

The browser must call the Laravel endpoint, never RapidAPI directly. Do not log headers or secrets. Remote image URLs should not be blindly imported; if the user elects to use a cover image, fetch it server-side with SSRF protections, MIME/size verification, and attribution/source metadata.

## 10. OpenRouter AI integration

Server-side service: `OpenRouterService`, split behind text and image generation interfaces so providers can be changed later.

Text generation:

- Build prompts from the confirmed book snapshot, author-supplied genre/audience/tone/brand notes, module contract, field limits, and selected target fields.
- Return structured JSON keyed by editable field names.
- Validate the structure and length, then present a diff/selection screen; never silently replace copy.
- Sanitize generated rich text before saving.

Image generation:

- Prompt uses explicit target dimensions/aspect ratio and the author's direction.
- Store outputs as normal owned assets with source/model/prompt metadata.
- Require the user to add/review alt text and crop/fit before assigning.
- Provider/model failures are recoverable and do not damage existing module content.

AI routes require quotas and rate limiting. Keys and model IDs stay on the server. The front end may display friendly configured model labels returned by the server, but must not receive the API key.

## 11. Environment and configuration contract

Commit only placeholders to `.env.example`. Place real secrets in the local deployment `.env`, which remains ignored by Git.

```dotenv
RAPIDAPI_KEY=
RAPIDAPI_AMAZON_PRODUCT_HOST=amazon-product-data-api.p.rapidapi.com
RAPIDAPI_AMAZON_PRODUCT_BASE_URL=https://amazon-product-data-api.p.rapidapi.com

OPENROUTER_API_KEY=
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_TEXT_MODEL=
OPENROUTER_IMAGE_MODEL=
OPENROUTER_APP_NAME="A+ Content Maker"
OPENROUTER_SITE_URL=

ADMIN_NAME="A+ Content Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=
```

Map these through `config/services.php` and dedicated config keys. Application code should call `config(...)`, not `env(...)`, so `php artisan config:cache` works in production.

The admin seeder uses `updateOrCreate` by email, hashes the environment password, sets `role=admin`, and refuses to create a production admin with a missing/known placeholder password. Automated/local test environments can use a documented test default.

The RapidAPI credential supplied in the request must be treated as compromised because it was shared in plaintext. Rotate it before deployment and store only the rotated value in `.env`.

## 12. Vanilla JavaScript architecture

Suggested entry files:

- `resources/js/app.js`: shared shell, menus, dialogs, toasts, CSRF-aware fetch helper.
- `resources/js/builder/index.js`: builder bootstrap and orchestration.
- `resources/js/builder/store.js`: normalized local state and dirty tracking.
- `resources/js/builder/modules.js`: module registry client view and render dispatch.
- `resources/js/builder/autosave.js`: debounced PATCH queue, retry, conflict/error state.
- `resources/js/builder/editor.js`: safe rich-text toolbar behavior.
- `resources/js/builder/assets.js`: upload/drop modal and previews.
- `resources/js/builder/ai.js`: generation dialog, polling, and apply-selection.
- `resources/js/builder/preview.js`: editor/preview switching and responsive frame.

Render initial project state as escaped JSON in a `<script type="application/json">` element. Use event delegation on the builder root, stable module IDs, `AbortController` for replaceable requests, and `FormData` for uploads. Avoid inline event handlers.

Autosave behavior:

- Update local state immediately.
- Debounce per module (~600–1000 ms).
- Serialize saves for the same module to prevent stale responses winning.
- Flush on explicit Save, tab change, and when practical before navigation.
- Warn on unload only while a failed/unsent change exists.
- Send a version/timestamp token to detect conflicting edits; return HTTP 409 rather than overwrite.

## 13. Validation and security

- Validate all module payloads through the server module registry.
- Enforce ownership with policies for nested resources; do not trust a matching route project ID alone.
- Sanitize HTML to an allow-list such as `p`, `br`, `strong`, `em`, `u`, `ul`, `ol`, and `li`; strip attributes except those explicitly needed.
- Validate decoded image contents, not only file extension. Set file size and pixel limits and re-encode images where appropriate.
- Store originals/exports privately; expose via authorized temporary URLs.
- Escape all normal Blade output. Only output sanitized rich text with `{!! !!}`.
- Rate-limit ASIN and AI calls independently and record safe usage metadata.
- Never return provider errors that contain headers, tokens, or raw request data.
- Prevent SSRF when importing remote cover images: HTTPS only, allow expected hosts or resolve/block private/reserved IP ranges, limit redirects and bytes.
- Use database transactions for template cloning and module reordering.
- Add CSP/security headers where compatible with Vite and selected fonts.
- Include privacy/retention language for prompts and generated data before public launch.

## 14. Accessibility and responsive requirements

- Every control has an accessible name and visible focus state.
- Dialogs trap focus, close on Escape, restore focus to the opener, and set correct ARIA semantics.
- Reordering is available with buttons and keyboard; do not rely only on drag/drop.
- Upload areas are real buttons/labels usable without dragging.
- Rich-text toolbar exposes pressed state and keyboard behavior.
- Status changes (saving, generation complete, errors) use an `aria-live` region.
- Color is never the only validation or state indicator.
- Preview images use stored alt text; decorative imagery uses empty alt text.
- Main workflows target WCAG 2.1 AA contrast and interaction expectations.

## 15. Testing plan

### Feature tests

- Breeze auth lifecycle and restyled conventional Blade views.
- User/admin route separation and project/template policies.
- Admin seeder behavior and password hashing.
- Template creation, publish validation, cloning, and archival.
- Blank and template-based project creation.
- ASIN validation, success mapping, caching, throttling, timeouts, and safe failure using `Http::fake`.
- Module add/update/delete/reorder, count limits, invalid schema rejection, and cross-user denial.
- Upload validation, dimensions, ownership, alt text, and deletion behavior.
- Text/image AI success/failure using fakes; assert keys never appear in output/loggable payloads.
- Export authorization, validation, manifest, ZIP contents, and expiry.

### Unit tests

- Every module definition's defaults and validation rules.
- Rich-text sanitizer.
- ASIN normalizer/DTO mapper.
- Template-to-project deep copy.
- Export filename and manifest generation.

### Browser/manual QA

- Landing/auth/dashboard responsive layouts.
- Keyboard-only builder and dialogs.
- Autosave under latency/failure and rapid consecutive edits.
- Every screenshot-derived module at desktop/mobile preview widths.
- `npm run build`, fresh migration/seed, and production config cache.

## 16. Recommended implementation sequence

1. Scaffold Laravel 10, database config, Breeze Blade stack, Tailwind/Vite, and base tests.
2. Remove/refactor all Breeze `<x-...>` views into normal Blade/HTML and create the editorial public/auth/application shells.
3. Add roles, admin seeder, policies, and user/admin navigation.
4. Implement module registry, migrations/models, template cloning, and seed the built-in module catalog metadata.
5. Build landing page, dashboard, project wizard, and public template gallery.
6. Implement builder canvas, searchable add-module dialog, field editors, module ordering, deletion, and AJAX autosave.
7. Implement asset library/upload modal, dimension validation, alt text, private storage, and image slots.
8. Implement preview renderers for all screenshot-derived modules and responsive validation.
9. Implement ASIN proxy/caching and project book context.
10. Implement admin template studio and publish workflow.
11. Implement OpenRouter text generation, review/apply flow, then image generation and polling/queue handling.
12. Implement validation summary, copy helpers, ZIP export, and KDP transfer checklist.
13. Complete accessibility, security hardening, responsive QA, tests, build verification, and deployment documentation.

## 17. Seeded publishing template library

The admin seeder owns and publishes twelve cloneable visual campaigns: three each for Romance, Science Fiction, Fantasy, and Action & Thriller. Every genre includes two single-book campaigns and one three-book series. Series templates use the comparison module as a multi-book shelf; each row has its own ASIN lookup action that imports the returned title and cover into that project without changing the other books.

Generated campaign art lives in `public/images/templates`. When a user clones a template, referenced artwork is copied into a project-owned `assets` record, so later replacements and exports stay isolated from the source template.

## 18. Definition of done for the first production-ready release

- A fresh clone can be configured, migrated, seeded, tested, and built using documented commands.
- No Blade component tags (`<x-...>`) exist in application views.
- An environment-defined admin can manage and publish templates.
- A user can register, locate a book by ASIN, create a blank/template project, add/reorder/remove modules, edit every supported module, upload images with alt text, and recover from save errors.
- All 17 screenshot-derived module variants render in editor and preview with their specified image dimensions and repeater limits.
- AI text and image requests remain server-side, use environment-selected models, and require user review before applying.
- Users can validate and export a private KDP transfer package containing their copy and assets.
- Authorization, input validation, sanitization, throttling, provider fakes, and key user journeys have automated coverage.
- Landing and authentication pages have a polished book/editorial identity and responsive, accessible UI.
