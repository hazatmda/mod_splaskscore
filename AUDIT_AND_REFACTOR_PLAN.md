# SPLaSK Score Joomla 5 Audit and Refactor Plan

This document captures the requested pre-refactor audit for `mod_splaskscore` and proposes an implementation sequence that avoids a large, high-risk rewrite. It is based on the current repository state, where the module is an administrator module with a single PHP entry file, inline presentation assets, and a browser-side API call.

## Scope reviewed

- `mod_splaskscore.php`: module entry point, markup, inline CSS, inline JavaScript, grading logic, client-side API request, and token handling.
- `mod_splaskscore.xml`: Joomla extension manifest, module parameters, files list, update server metadata, and version metadata.
- `mod_splaskscore_update.xml`: update server metadata.
- `README.md`: installation and release notes.

## Current architecture snapshot

The current module is intentionally small but has reached the point where feature growth will be difficult:

- `mod_splaskscore.php` owns data access, rendering, styling, behavior, date formatting, and state updates.
- CSS and JavaScript are inline, so they cannot be versioned, cached, combined, or selectively loaded through Joomla asset APIs.
- The configured SPLaSK token is escaped before injection, but it is still emitted to the browser and sent from JavaScript.
- The manifest only packages `mod_splaskscore.php`; it does not include helper, template, media, or language folders.
- There is no layout abstraction, so adding five visual presets would require branching markup and CSS in the same file.
- There is no server-side response validation or cache boundary around the upstream SPLaSK API.

## Joomla 5 best-practice findings

### Findings

1. **Entry file does too much.** Joomla modules should keep the entry/dispatcher layer thin and pass prepared data to `tmpl` layout files. The current single-file design prevents template overrides and makes each future preset increase complexity.
2. **No `tmpl/` layout separation.** Joomla layout loading via `ModuleHelper::getLayoutPath()` allows extension layouts and template overrides to work consistently; this is the correct seam for a preset system.
3. **Assets are not managed through WebAssetManager.** Joomla 5 WebAssetManager supports dependency tracking, versioning, deferred scripts, and asset presets. Inline CSS/JS should be extracted into `media/` and loaded per selected design preset.
4. **Language strings are hard-coded.** Labels such as score title, loading text, error messages, and verification link text should move to `language/en-GB/` files before adding multilingual or enterprise deployments.
5. **Manifest is incomplete for the target structure.** The manifest must include `helper.php` or namespaced `src/`, `tmpl`, `media`, and `language` entries, plus new parameters for design preset, cache duration, and optional display controls.
6. **Backward compatibility needs an explicit default.** Existing installations should keep their `splask_token` parameter and default to a layout visually equivalent to the current circular score meter.

### Recommended Joomla 5 direction

Use an incremental Joomla-standard module structure first, then evolve to namespaced services if the module gains more server-side responsibilities:

```txt
mod_splaskscore/
├── mod_splaskscore.php
├── helper.php
├── tmpl/
│   ├── default.php
│   ├── modern_circle.php
│   ├── minimal_card.php
│   ├── glassmorphism.php
│   ├── gauge_meter.php
│   └── corporate_dashboard.php
├── media/
│   ├── joomla.asset.json
│   ├── css/
│   │   ├── base.css
│   │   ├── modern_circle.css
│   │   ├── minimal_card.css
│   │   ├── glassmorphism.css
│   │   ├── gauge_meter.css
│   │   └── corporate_dashboard.css
│   └── js/
│       └── splaskscore.js
├── language/
│   └── en-GB/
│       ├── mod_splaskscore.ini
│       └── mod_splaskscore.sys.ini
└── mod_splaskscore.xml
```

For a future Joomla 5-native larger refactor, consider this second-stage structure:

```txt
mod_splaskscore/
├── services/provider.php
├── src/
│   ├── Dispatcher/Dispatcher.php
│   ├── Helper/SplaskScoreHelper.php
│   ├── Service/SplaskApiClient.php
│   └── Value/ScoreResult.php
├── tmpl/
├── media/
├── language/
└── mod_splaskscore.xml
```

## Security hardening review

### High-priority concerns

1. **Client-side token exposure.** The token is currently embedded into JavaScript and visible to any administrator browser, browser extension, proxy, or collected page source. Escaping reduces XSS risk but does not protect the secret.
2. **Browser-origin API dependency.** The upstream API request is made from the browser, which exposes network details and makes the module depend on browser CORS, client connectivity, and client-side error behavior.
3. **Response trust is too broad.** The script trusts `status`, `final_score`, `last_check`, and `verification_url` without strong type, range, date, or URL validation.
4. **Potential unsafe link assignment.** `verification_url` is assigned to an anchor. It should be validated server-side to allow only expected HTTPS URLs before any output.
5. **Error handling is only presentation-level.** There is no server-side logging, no distinction between invalid token, timeout, bad schema, and upstream outage, and no recoverable cached fallback.
6. **No timeout or retry strategy.** Fetch defaults can leave poor UX under slow upstream conditions. Server-side HTTP should enforce short timeouts and predictable failures.

### Recommended hardening

- Move SPLaSK API calls to `helper.php` with Joomla HTTP client APIs or a small dedicated API client class.
- Never expose `splask_token` through script options, HTML data attributes, inline scripts, logs, or client-side API payloads.
- Return already-sanitized display data to layouts: numeric score, grade label key, safe color key, formatted timestamps, verified URL, and status enum.
- Validate response schema:
  - `status` must be boolean-like and positive before score fields are trusted.
  - `final_score` must be numeric and clamped or rejected outside `0..100`.
  - `last_check` must parse as the documented SPLaSK date format before display.
  - `verification_url` must be HTTPS and match an allowlist if the domain is known.
- Escape all layout output with Joomla escaping helpers.
- Add logging for upstream failures without logging the token.
- Add a defensive error status to layouts so UI presets can render consistent, localized messages.

## Performance review

### Risks

1. **Every module render can trigger an API request.** This creates admin dashboard latency and upstream load.
2. **Inline CSS and JS prevent browser caching.** All styling and behavior is re-sent with every admin page containing the module.
3. **DOM updates are fully client-driven.** This is acceptable for one module but becomes harder to scale if dashboard widgets expand.
4. **No stale data policy.** Under upstream outage the module has no cached last-known-good score.

### Recommended caching strategy

- Add module parameters:
  - `cache_enabled` default `1`.
  - `cache_lifetime` default `3600` seconds or match expected SPLaSK update frequency.
  - `allow_stale_on_error` default `1`.
- Cache by a stable key derived from the module id plus a non-reversible hash of the token, not the raw token.
- Cache normalized result objects, not raw upstream payloads.
- Store and display cache metadata such as fetched time and stale state when helpful.
- Invalidate cache when token or relevant module parameters change by including a params hash in the cache key.
- Keep JavaScript only for progressive visual enhancement; render the score server-side so cached HTML is useful immediately.

## Maintainability and scalability review

### Findings

- Current grading logic exists only in JavaScript, making server-side rendering and testing impossible without duplication.
- Current IDs such as `progress-circle`, `score-text`, and `evaluation-text` assume one module instance per page. Multiple admin dashboard instances would conflict.
- CSS selectors are global enough to risk collisions once more presets are added.
- Preset-specific DOM structures need a shared data contract or every layout will reimplement defensive checks differently.
- No testable helper layer exists for grading, validation, response normalization, or URL safety.

### Recommendations

- Move grading and color decisions to PHP helper methods.
- Use module-id-scoped classes and `data-module-id` attributes instead of global IDs.
- Define one normalized view model shared by all layouts:

```php
[
    'status' => 'ok|not_found|error|stale',
    'score' => 0,
    'scoreLabel' => '0%',
    'gradeKey' => 'MOD_SPLASHSCORE_GRADE_A',
    'gradeClass' => 'grade-a',
    'lastCheckLabel' => '...',
    'nextCheckLabel' => '...',
    'verificationUrl' => 'https://...',
    'isStale' => false,
    'messageKey' => null,
]
```

- Keep `tmpl/default.php` as an alias or wrapper for `modern_circle.php` to preserve template override behavior.
- Use a base CSS file for common typography, spacing, status badges, and accessibility utilities; keep only visual differences in preset CSS.
- Add a tiny JavaScript enhancement layer only if animation is required. The module should remain readable without JavaScript.

## Multi-design preset implementation plan

### Shared preset contract

Every preset should receive the same normalized `$scoreData`, `$module`, and `$params` variables and must support these states:

- `ok`: render score, grade, dates, and verification URL.
- `not_found`: render a localized no-score message.
- `error`: render a localized connection or validation error.
- `stale`: render cached last-known-good data with a stale indicator.
- `loading`: only needed if a future async refresh mode is added.

### Presets

1. **modern_circle**
   - Backward-compatible default based on the existing circular meter.
   - Server-render SVG progress values and grade class.
   - Optional CSS animation after initial render.

2. **minimal_card**
   - Compact dashboard card for small admin panels.
   - Prioritize score, grade, and last check date.
   - Minimal CSS, no animation requirement.

3. **glassmorphism**
   - Visual preset using translucent background, blur, soft borders, and high-contrast fallback.
   - Must account for administrator template backgrounds and accessibility contrast.

4. **gauge_meter**
   - Semicircular gauge with score needle or progress arc.
   - Should avoid canvas dependency unless animation becomes necessary.
   - SVG-only first implementation is easier to cache and test.

5. **corporate_dashboard**
   - Enterprise-oriented layout with score, grade, status badges, cache freshness, and action link.
   - Best candidate for future multi-widget dashboard expansion.

### Preset selector parameter

Add a `design_preset` list field to `mod_splaskscore.xml`:

```xml
<field
    name="design_preset"
    type="list"
    label="MOD_SPLASHSCORE_FIELD_DESIGN_PRESET_LABEL"
    description="MOD_SPLASHSCORE_FIELD_DESIGN_PRESET_DESC"
    default="modern_circle">
    <option value="modern_circle">MOD_SPLASHSCORE_PRESET_MODERN_CIRCLE</option>
    <option value="minimal_card">MOD_SPLASHSCORE_PRESET_MINIMAL_CARD</option>
    <option value="glassmorphism">MOD_SPLASHSCORE_PRESET_GLASSMORPHISM</option>
    <option value="gauge_meter">MOD_SPLASHSCORE_PRESET_GAUGE_METER</option>
    <option value="corporate_dashboard">MOD_SPLASHSCORE_PRESET_CORPORATE_DASHBOARD</option>
</field>
```

Whitelist the requested layout before loading it:

```php
$allowedLayouts = [
    'modern_circle',
    'minimal_card',
    'glassmorphism',
    'gauge_meter',
    'corporate_dashboard',
];

$layout = (string) $params->get('design_preset', 'modern_circle');
$layout = in_array($layout, $allowedLayouts, true) ? $layout : 'modern_circle';

require ModuleHelper::getLayoutPath('mod_splaskscore', $layout);
```

## Asset management proposal

- Register module assets through `media/mod_splaskscore/joomla.asset.json` and explicitly add the registry file from PHP because module asset JSON files are not automatically discovered in the same way active component assets are.
- Define:
  - `mod_splaskscore.base` style.
  - One style per preset.
  - Optional `mod_splaskscore` script with `defer` if animation remains client-side.
  - One WebAsset preset per design that depends on base style and the matching preset style.
- Use WebAssetManager asset presets to attach only the selected design assets.
- Avoid inline script except `Text::script` or `Document::addScriptOptions` for localized strings or non-secret display options.

## Accessibility and responsive UI recommendations

- Render score as text in addition to SVG visuals.
- Add `role="status"` or appropriate live-region behavior only for async refresh states; avoid noisy live regions for static server render.
- Ensure color is not the only way to communicate grade; include grade text and status labels.
- Keep responsive card widths fluid with sensible max-widths.
- Respect `prefers-reduced-motion` for meter animations.
- Ensure all links have meaningful text, `rel="noopener noreferrer"` for external targets, and validated HTTPS URLs.
- Provide high-contrast fallback styles for glassmorphism.

## Potential breaking changes

| Area | Risk | Mitigation |
| --- | --- | --- |
| Template overrides | Existing sites cannot currently override layouts, but future overrides may depend on `tmpl/default.php`. | Keep `default.php` and make it load/render `modern_circle` behavior. |
| CSS selectors | Existing custom admin CSS might target current classes/IDs. | Preserve key `.splask-meter` classes in `modern_circle` for one release and document deprecations. |
| Client-side behavior | Integrators may expect browser fetch behavior. | Render server-side by default; optionally add a future `async_refresh` parameter after proxy is stable. |
| Token setting | Token parameter must remain named `splask_token`. | Keep the field name and migrate only internals. |
| Update packaging | Adding folders changes manifest packaging. | Validate install/upgrade zip before release. |
| Error messages | More precise validation may show errors where bad upstream data was previously displayed. | Use localized, administrator-friendly messages and logs. |

## Recommended implementation sequence

### Phase 0: Guardrails before refactor

1. Add this audit/plan to the PR so reviewers align on the target architecture.
2. Create a small manual QA checklist for Joomla administrator install, upgrade, module edit, and dashboard render.
3. Decide whether phase 1 uses legacy `helper.php` or a Joomla 5 namespaced `src/` service structure. For minimal risk, start with `helper.php` and plan `src/` as phase 2.

### Phase 1: Structure without behavior change

1. Add `helper.php` with grade calculation, color class mapping, and layout whitelist helpers.
2. Move existing markup into `tmpl/modern_circle.php`.
3. Add `tmpl/default.php` as a compatibility wrapper for `modern_circle`.
4. Add the `design_preset` parameter defaulting to `modern_circle`.
5. Update manifest file packaging.
6. Keep the existing client-side API temporarily only if needed to reduce change size, but mark it deprecated in code comments.

### Phase 2: Asset extraction

1. Move common CSS to `media/css/base.css`.
2. Move current circular meter CSS to `media/css/modern_circle.css`.
3. Move JavaScript to `media/js/splaskscore.js` only as a temporary compatibility bridge.
4. Add `media/joomla.asset.json` and WebAssetManager registration.
5. Scope DOM selectors by module id and remove global IDs.

### Phase 3: Server-side API and cache

1. Implement server-side API request handling in helper/API client.
2. Remove token output from all HTML and JavaScript.
3. Normalize and validate API responses.
4. Add cache keys based on module id and token hash.
5. Add stale-on-error fallback.
6. Add localized error/status strings.

### Phase 4: Preset expansion

1. Implement `minimal_card` using the normalized data contract.
2. Implement `gauge_meter` using SVG.
3. Implement `corporate_dashboard` with cache/status metadata.
4. Implement `glassmorphism` after contrast review.
5. Add visual QA for all presets in Atum administrator template.

### Phase 5: Joomla 5-native hardening

1. Consider migrating from `helper.php` to namespaced `src/` classes and `services/provider.php` if complexity justifies it.
2. Add automated PHP syntax checks and helper unit tests outside Joomla where possible.
3. Add release notes documenting parameter compatibility and deprecated selectors.
4. Prepare a minor-version release if compatibility is preserved; use a major version only if template or API behavior is intentionally broken.

## Suggested initial acceptance criteria

- Existing module installations retain `splask_token` and render the modern circular UI by default.
- No SPLaSK token appears in page source, JavaScript, script options, or network calls from the browser.
- All layouts render from the same validated server-side data object.
- At least one cache hit path and one stale-on-error path are manually verified.
- Assets are loaded through Joomla WebAssetManager and only the selected preset CSS is attached.
- `modern_circle` remains visually close to the current UI for backward compatibility.

## Reference documentation consulted

- Joomla module examples and manifest/layout guidance: https://manual.joomla.org/docs/5.4/building-extensions/modules/module-examples/basic-module/
- Joomla namespace guidance for modules: https://manual.joomla.org/docs/5.4/general-concepts/namespaces/defining-your-namespace/
- Joomla WebAssetManager documentation: https://manual.joomla.org/docs/5.4/general-concepts/web-asset-manager/
- Joomla module `tmpl` and layout override behavior: https://manual.joomla.org/docs/4.4/building-extensions/modules/module-development-tutorial/step2-tmpl-file/
