# FlutterFlow AI Workspace

FlutterFlow AI is a local workspace for creating and editing FlutterFlow apps with a coding agent.

## Files

- `dsl/create.dart`
- `dsl/edit.dart`
- `test/app_test.dart`
- `references/`
- `patterns/`
- `lib/flutterflow_project.dart` — generated typed project SDK barrel. Import it as `ff` in edit flows. The actual content is split across per-entity files under `lib/flutterflow_project/` (one file per page in `pages/`, one per component in `components/`, plus `schemas.dart`, `app_state.dart`, `apis.dart`, `theme.dart`). The barrel re-exports everything, so user code keeps `import '.../flutterflow_project.dart' as ff;` — to read a specific page's tree, jump directly into its file.
- `PROJECT_CONTEXT.md` — tiny onboarding pointer to the typed SDK for project-bound workspaces.
- `generated_code/` — read-only snapshot of the Flutter code FlutterFlow generates from the project. Manifest at `generated_code/.flutterflow/export_manifest.json` maps each entity (page, component, action block, etc.) to its `primary_files`. Use this when debugging visual or runtime bugs the DSL alone cannot explain (overflow, layout, render errors, build failures).
- `.flutterflow/` (SDK-managed: run history, traces, workspace state, plus router config)

## Keep the SDK up to date

Once per session, before starting on the user's task, run:

```
flutterflow ai upgrade --check
```

This is non-mutating — it reads `latest.json` from the SDK release channel and prints one `key: value` line per fact (no header, no indentation), ending with `newer_available: true|false`.

- `newer_available: false` (or `pinned_local_sdk: true`): proceed with the user's task.
- `newer_available: true`: tell the user there is a newer FlutterFlow AI SDK build (`<current_version>` → `<latest_version>`) and ask whether to upgrade. On consent, run `flutterflow ai upgrade`. On decline, continue with the current build and do not ask again this session.
- If the check fails (network error, etc.): mention it once, then proceed with the user's task. Do not retry in a loop.

Don't run the check on every command — once at the start of the session is enough.

## Workflow

### Selector-first edit workflow

If the user pasted a `FlutterFlow AI Selector v1` block, use it before any broad page/component inspection:

1. Parse the pasted block for `project_id`, `scope_kind`, `scope_name`, `selector_path`, `node_key`, `node_name`, and `node_type`.
2. Run `flutterflow ai inspect <project_id> --page|--component <scope_name> --selector-path <selector_path> --dsl-json` to resolve the target widget.
3. Verify the returned `node_type` and `node_name` match expectations from the pasted block.
4. **If the user is reporting a visual or runtime bug** (overflow, layout, render error, exception, "looks wrong" / "doesn't fit"): before authoring the patch, read the generated Dart for the selector's scope.
   - Look up the entity in `generated_code/.flutterflow/export_manifest.json` by `name == scope_name` (or `key == node_key`).
   - Read its `primary_files` to see the actual widget tree, constraints, and styling Flutter is rendering.
   - The DSL is intent; the generated code is what is actually running. Overflow, an unbounded `Column` inside a `Row`, fixed sizes vs. `Expanded`, etc. are only visible there.
   - If `generated_code/` is missing or stale (`flutterflow ai codegen status` reports `stale`/`missing`), run `flutterflow ai codegen refresh` first.
5. Author the patch in `dsl/edit.dart` through the generated typed widget tree:
   ```dart
   import 'package:<workspace>/flutterflow_project.dart' as ff;

   app.editPage(ff.Pages.homePage, (page) {
     page.find(
       ff.Pages.homePage.widgets.byPath('PageName.body[0].children[1]').single,
     ).update((patch) {
       // ...
     });
   });
   ```
6. Run `flutterflow ai test`, then `flutterflow ai run`. The `flutterflow ai test` wrapper runs `dart test` and additionally records compile/test outcomes for the FlutterFlow AI dashboard. Plain `dart test` still works but won't be tracked. **Do not run `flutterflow ai validate` as a dry-run before `run`** — `run` validates internally and only pushes if validation passes, so a failing `run` is identical to a failing `validate`: same errors, no remote mutation, no half-pushed state. Validate-first is pure overhead; iterate directly on `run`.
7. If `--selector-path` fails, fall back to `--selector-key` with the `node_key` from the block.
8. Only do a broad `flutterflow ai inspect --page/--component` pass when the selector is stale or missing.

### General workflow

1. Start from the closest working examples in `references/`. Do not read the full API surface first unless the references are insufficient or you are blocked.
2. For edit work, start from `lib/flutterflow_project.dart` (the barrel) — it is the generated typed map of pages, components, state, params, collections, tables, widgets, selectors, and metadata. For surgical reads, jump directly into the per-entity files under `lib/flutterflow_project/` (`pages/<slug>.dart`, `components/<slug>.dart`, `schemas.dart`, etc.) instead of reading the barrel. Use `flutterflow ai inspect <project-id>` for explicit debug/export views.
3. Edit `dsl/create.dart` or `dsl/edit.dart`. The CLI argument-parser boilerplate in these files is stable — only the body of `buildEditFlow` (or `buildCreateFlow`) changes. Prefer the `Edit` tool on that function over a full-file `Write`.
4. Update `test/app_test.dart` to match your changes (page names, component names, expected structure). The starter test references `StarterPage` — change it to match whatever you built.
5. Run `flutterflow ai test` (a `dart test` wrapper that also records compile/test outcomes for the dashboard). Plain `dart test` still works but won't be tracked.
6. **Execute the push** — this is NOT optional, always run this as the final step. `flutterflow ai run` validates internally and only pushes if validation passes, so a failing `run` is identical to a failing `validate`: same errors, no remote mutation, no half-pushed state. **Do not run `flutterflow ai validate` first as a dry-run "for safety" — it adds zero safety over `run` and just doubles iteration time.** Iterate directly on `run` until it passes. Always include `--commit-message` with a short description of what changed:
   - **Create:** `flutterflow ai run dsl/create.dart --project-name "<name>" --commit-message "<what the app does>"`
   - **Edit:** `flutterflow ai run dsl/edit.dart --project-id "<id>" --commit-message "<what changed>"`
   - Use `--find-or-create` only as a retry/recovery option when a previous create run may already have created the remote project but the local workspace is not bound yet.
   - If the workspace is already bound to a project in `.flutterflow/workspace.json`, FlutterFlow AI will refuse plain create mode by default. Use `--allow-new-project` only when you intentionally want a second project from the same workspace.
7. Successful `flutterflow ai run` pushes refresh `lib/flutterflow_project.dart` automatically. Run `flutterflow ai refresh-context <project-id>` after remote changes made outside this workspace.

### When to use `flutterflow ai validate`

`flutterflow ai validate <file>` runs the same pipeline as `flutterflow ai run` but skips the final push. It is **not** part of the normal edit loop — `run` already validates before pushing, so running validate first just doubles the validation work. Reach for `validate` only when you want validation output *without* a network push: CI pre-flight checks, offline previews of a create/edit that you do not yet intend to commit, or sanity-checking a heavily refactored DSL before exposing it to the server.

### Fast-lane patch (`flutterflow_ai__patch` MCP tool) — MANDATORY for property edits

**REQUIRED FIRST ATTEMPT**: If the user's request can be expressed as "set property P on existing widget W to literal value V", you MUST call the `flutterflow_ai__patch` MCP tool **before** considering `flutterflow ai run`. This is not optional — the fast lane lands the edit on the FF backend in ~30s versus 2+ minutes for the slow path. Going to slow-path-first burns ~90 extra seconds of the user's time on every trivial edit.

**Full reference**: `flutterflow ai docs fast-lane` — auto-generated from the live `kFastPatchOps` table. Always current with the SDK. Read this when you're unsure whether an op exists or what its value shape is.

The decision rule:
- "change this text", "make this color X", "set fontSize to N", "hide this widget", "fade this to 50% opacity" on an EXISTING widget → **fast lane (`flutterflow_ai__patch`)**, no exceptions.
- Anything that requires writing or reading Dart, mutating the tree shape, or wiring action chains → slow path (`flutterflow ai run`).

**Mandatory first-attempt criteria** (use fast lane if ALL apply):
- The target already exists (you have its handle in `lib/flutterflow_project/`, or you're tweaking a project-level setting like dark mode / fonts)
- The change is one of the ~100 ops in the fast-patch table (see "Op surface" below) — when in doubt, try it; the tool returns a structured `invalid_request` error listing valid ops if you guessed wrong, which is still cheaper than the slow path
- The value is a literal (a string, a number, a bool, a theme-token name, or an ARGB int) — NOT a variable/state/API/conditional binding

**When to fall back to `flutterflow ai run`** (slow path):
- The fast lane returned `error_kind: invalid_request` and the error message says the op isn't supported. Don't retry — switch to `run` immediately.
- The change is structural (insert/remove/move widgets, wrap/unwrap, change widget type)
- Custom code (functions, actions, widgets, classes, enums)
- Action wiring (onTap → Navigate, action chains, triggers)
- Binding a property to a variable / state / API response / conditional
- App-state field declarations, custom constants, API config, pub dependencies (use slow path)

**Disallowed pattern**: editing `dsl/edit.dart` with `page.update(widget, (patch) { patch.color(...); patch.fontSize(...); })` and running `flutterflow ai run` for a request that matches the fast-lane criteria above. Doing this slows the user down by ~90 seconds for no gain. If the fast lane fits, use the fast lane.

**How to invoke (call shape):**
```
flutterflow_ai__patch({
  project_id: <id>,
  commit_message: 'fast-patch: <op summary>',
  node_key: ff.pages.Home.widgets.welcomeTitle.key,
  widget_type: ff.pages.Home.widgets.welcomeTitle.type,
  patches: [
    { op: 'text', value: 'Hello, World' },
    { op: 'color', value: { token: 'primary' } },
  ],
})
```

`node_key` and `widget_type` are both available on every typed SDK widget handle (no discovery query needed). The `ProjectWidgetHandle.fastPatch(...)` helper returns the right `{node_key, widget_type, patches}` shape ready to pass to the tool.

**CAS / `parent_updated_at_ms` is automatic** — the SDK client caches the project's updated_at_ms after every patch and re-fetches transparently on a 409. Agents do NOT pass it. The tool's input schema lists it as optional only for the rare case where you want to force a specific CAS check.

**Context auto-refreshes after every fast-patch** — both `lib/flutterflow_project/` (typed SDK, completes in seconds) AND `generated_code/` (full Flutter snapshot, can take 10–30s) are regenerated in the background. The patch response returns to you in ~30s; by the time you make the next prompt, the typed SDK is fresh and `generated_code/` is either fresh or refreshing. Don't call `flutterflow ai refresh-context` / `flutterflow ai codegen refresh` manually for fast-patch flows — they'd duplicate the background work.

**Color ops — two flavors:**
- `color` (theme tokens): `{ op: 'color', value: { token: 'primary' } }`. Valid slots: `primary`, `secondary`, `tertiary`, `alternate`, `primaryBackground`, `secondaryBackground`, `primaryText`, `secondaryText`, `accent1`–`accent4`, `success`, `warning`, `error`, `info`.
- `colorArgb` (raw ARGB int): `{ op: 'colorArgb', value: 0xFFE91E63 }`. Use this when the user asks for a specific hex/RGB color that doesn't map to a theme slot. Both ops target the same widgets (Text, Button, Icon, IconButton, Container, Card, Divider, TextField); they write to different proto leaves.

**Op surface (current snapshot, ~100 ops):**

The complete list lives in `kFastPatchOps` (in the SDK's generated `fast_patch_ops.g.dart`) and is too long to enumerate inline. Highlights by category:

- **Text/typography**: `text`, `fontFamily`, `fontSize`, `fontWeight` (w100–w900), `fontStyle` (normal/italic), `textAlign` (start/center/end/justify), `textDecoration` (none/underline/strikethrough), `overflow` (clip/ellipsis/fade/visible), `maxLines`, `lineHeight`, `letterSpacing`
- **Color / visibility**: `color` (token), `colorArgb` (raw int), `visible`, `opacity`
- **Sizing / spacing**: `width`, `height`, `borderRadius`, `spacing`. `paddingAll` and `aspectRatio`-equivalents stay slow-path
- **Container styling**: `boxShape`, `clipContent`, `safeArea`, `borderColor`, `borderWidth`
- **AppBar**: `appBarTitle`, `appBarCenterTitle`, `appBarElevation`
- **Button**: `buttonVariant`, `buttonLoading`, `buttonElevation`, `buttonIconPosition` (leading/trailing)
- **TextField (~14)**: `textFieldLabel`, `textFieldHint`, `textFieldFilled`, `textFieldBorder` (outline/underline/none), `textFieldMaxLength`, `textFieldLinesMin/Max`, `keyboardType`, `obscureText`, `readOnly`, `autofocus`, `textFieldFillColor`, `textFieldHintColor`, `textFieldLabelColor`, `textFieldCursorColor`
- **Slider/Switch/Checkbox/Progress**: per-side color ops (`sliderColorActive/Inactive`, `switchColorActive/ActiveTrack/InactiveThumb/InactiveTrack`, `checkboxColorActive/Check/Unchecked`, `progressColorForeground/Background`), `sliderMin/Max/Divisions`, `progressShape`, `progressBarRadius`, `disabled`
- **Dropdown**: `dropdownHint`, `dropdownLabel`, `dropdownElevation`, `dropdownInitialOption`
- **Divider**: `dividerThickness`, `dividerStyle` (solid/dotted/dashed/dashDotted), `dividerIndentStart`, `dividerIndentEnd`
- **Card**: `cardElevation`
- **Charts**: `chartShowGrid`, `chartShowLegend`, `chartShowBorder`, `chartGridColor`, `chartBorderWidth`, plus per-type configs (`barChart*`, `pieChart*`)
- **Map**: `mapZoom`, `mapType` (normal/terrain/hybrid/satellite)
- **Image**: `imageFit` (fill/contain/cover/fitWidth/fitHeight/none/scaleDown)
- **HTML**: `htmlContent`
- **Shader**: `shaderAnimationMode`, `shaderBackgroundColor`, `shaderInteractive`, `shaderCache`, `shaderAnimationDurationMs/DelayMs/Loop/Reverse`
- **App-scoped (no node_key needed)**: `darkMode`, `primaryFont`, `secondaryFont`

**When in doubt, try the fast lane first.** A wrong op name returns `invalid_request` in <500ms with a list of valid ops; the slow path takes 2+ minutes whether the op exists or not. The cost of a wrong fast-lane guess is one extra round-trip; the cost of defaulting to slow path is the full 2+ minutes.

**Failure modes** — the tool returns a structured `error_kind`:
- `invalid_request`: malformed op, unknown widget type, or op not valid for this widget type. Fix the args and retry.
- `cas_conflict`: the project changed underneath you AND the client's transparent retry also lost the race. Rare. Just re-run the tool — the SDK client refreshes its CAS cache on every 409, so a fresh call picks up the new server state.
- `fast_lane_disabled`: server kill switch is on. Use `flutterflow ai run` instead.
- `server_error`: anything else. Fall back to slow path.

After a successful fast-patch, the backend proto is updated and the workspace's `lib/flutterflow_project/` (typed SDK) plus `generated_code/` (full Flutter snapshot) are refreshed in the background. The patch tool returns immediately; by your next prompt the typed SDK is fresh and `generated_code/` is fresh-or-soon. Only run `flutterflow ai refresh-context` manually if you made a structural change via `flutterflow ai run` and need fresh handles right now.

## Design & Quality Rules

These rules are **mandatory for every create and edit script**. Quick summary; read `flutterflow ai docs design-quality` for the full reference.

- **Theme first** — set up `app.themeColor(...)`, `app.typography(...)`, and design tokens before building UI; bind widgets to `Colors.primary` / `Colors.secondaryText` / etc. for cohesion. **Scope colors correctly:** widget-specific color requests → `Colors.hex(...)` on the node; brand/app-wide requests → `app.themeColor(...)`.
- **Components for reuse** — extract any repeated subtree into `app.component()` with typed `params:`.
- **Default values on params** — give every `app.page`/`app.component` param a `.withDefault(...)` unless every call site provably supplies a non-null value. Required page params crash on cold-entry deep links.
- **Descriptions everywhere** — pass `description:` on `app.page/component/actionBlock/collection/table/event/customFunction`. Short, clear text — it shows up in the FF editor.
- **Visual quality** — size buttons with `width`/`padding`/`borderRadius`/`color`; use `Container` for cards; `spacing:` on `Column`/`Row`; `Styles.titleLarge` etc. for text hierarchy; `maxLines:` + `TextOverflow.ellipsis` on overflow-prone text; explicit size on `ProgressBar.circular`; avoid `shrinkWrap: true` on dynamic `ListView`.
- **Action outputs** — when a page/component has >1 backend action with output, set `outputAs:` explicitly on each.
- **DSL ↔ Flutter drift** — a handful of widgets/props differ from Flutter (no `Center`, no `GestureDetector`, `Shadow(dx:, dy:)` not `Offset`, `Param(...)` not `ComponentParam(...)`, etc.). See the docs for the full drift table; check `references/` when a Flutter-shaped symbol fails to compile.

## Create → Edit Transition

**IMPORTANT:** Create scripts (`dsl/create.dart`) are one-shot — they create pages and components from scratch. You **cannot** re-run a create script against the same project; it will fail with duplicate-name errors.

After the first successful create push:
1. The project now exists. Read `projectId` from `.flutterflow/workspace.json`.
2. If `flutterflow` CLI is available, FlutterFlow AI also exports a local Flutter snapshot into `generated_code/`.
3. `flutterflow ai init --project <id>` and successful `flutterflow ai run` pushes keep `lib/flutterflow_project.dart` current for work done in this workspace.
4. For all subsequent edits, use **edit flows** in `dsl/edit.dart` with `--project-id "<id>"`.
5. Use `lib/flutterflow_project.dart` (the barrel) to understand the current page/component structure before editing — or jump straight into `lib/flutterflow_project/pages/<slug>.dart` for a specific page's typed tree. Use `flutterflow ai inspect <project-id> --page <PageName>` when you need an explicit debug/export view.
6. Read `references/taskboard_dsl.dart` or other edit references for patterns.
7. After later pushes, `lib/flutterflow_project.dart` is regenerated and `generated_code/` is re-exported when refresh is enabled. If a push leaves the snapshot stale (codegen skipped or the export failed), run `flutterflow ai codegen refresh`.

Do NOT modify and re-run `dsl/create.dart` to make changes to an existing project.
Do NOT switch back to `--project-name` in a bound workspace unless you intentionally want a separate project and pass `--allow-new-project`.

## Edit Context

- `flutterflow ai init --project <id>` creates a project-bound workspace and writes `lib/flutterflow_project.dart` when credentials are available.
- When available, `flutterflow ai init --project <id>` also exports a local Flutter snapshot into `generated_code/`.
- `lib/flutterflow_project.dart` is the **authoring and inspection map** (a thin barrel; the content lives in per-entity files under `lib/flutterflow_project/`). Import the barrel as `ff` and prefer `ff.Pages.*`, `ff.Components.*`, `ff.Collections.*`, `ff.Tables.*`, `ff.AppState.*`, and widget handles over raw strings. For a single page or component, navigate into its per-entity file (`lib/flutterflow_project/pages/<slug>.dart`, `lib/flutterflow_project/components/<slug>.dart`) rather than scrolling the barrel.
- `generated_code/` is the **runtime truth**. The DSL describes intent; the generated Dart is what Flutter actually builds and renders. Read it whenever you need to reason about layout, sizing, overflow, render exceptions, build errors, or any "why does the rendered app look or behave like this" question — these are not answerable from the DSL alone.
- Use the manifest at `generated_code/.flutterflow/export_manifest.json` to jump directly from an entity (page, component, action block) to its `primary_files`. Look up by `name` (matches the selector's `scope_name`) or `key` (matches `node_key`). Do not grep for files when the manifest exists.
- Treat `generated_code/` as read-only. Do NOT edit files there directly — make changes in `dsl/edit.dart` or other FlutterFlow AI-managed source, then push through `flutterflow ai run`.
- If a task starts from a generated Dart file, identify the corresponding page, component, or resource from that file and apply the change through FlutterFlow AI rather than patching the generated output.
- Successful `flutterflow ai run` pushes refresh `lib/flutterflow_project.dart` automatically and refresh `generated_code/` by default. If codegen is skipped or the export fails, the generated-code snapshot is marked stale and `flutterflow ai codegen status` / `codegen refresh` apply.
- `flutterflow ai refresh-context <project-id>` rewrites `lib/flutterflow_project.dart` **and** re-exports `generated_code/` after meaningful remote changes made outside this workspace.
- Run `flutterflow ai context-check` to verify whether generated typed SDK metadata is still fresh.
- **Do NOT use `flutterflow ai inspect <id> --dsl-json` for general discovery.** Read `lib/flutterflow_project.dart` (or, for surgical reads of a single entity, `lib/flutterflow_project/pages/<slug>.dart` / `components/<slug>.dart`) instead — every page, component, collection, table, app-state field, and widget selector lives there as a typed handle. `inspect --dsl-json` is reserved for two narrow cases: (1) resolving a pasted FlutterFlow AI Selector v1 block via `--selector-path` (see the selector workflow above), and (2) explicit debug/export when the typed SDK genuinely doesn't carry what you need (e.g. raw FFNode shape). For human-readable summaries reach for plain `flutterflow ai inspect <id>` or `flutterflow ai resources <id>`, not the JSON variant.

## Edit APIs for Existing Resources

Quick summary; read `flutterflow ai docs edit-apis` for the full reference with code samples.

- **Typed handles** — use `ff.Collections.*`, `ff.Components.*`, `ff.Pages.*`, `ff.AppState.*` from `lib/flutterflow_project.dart` everywhere. Raw `app.existing*` helpers were removed.
- **Component instances** — `ff.Components.tripCard(title: ...)`. `name:` and `visible:` are reserved on every component call (don't declare params with those names).
- **Component param binding** — `page.setComponentParam(selection, 'paramName', expr)`.
- **Page-load actions** — `app.editPageOnLoad(ff.Pages.myPage, [...])`.
- **Idempotent creation** — `app.ensurePage(...)`, `app.ensureFirebaseAuth(...)` no-op if already present.
- **Page metadata** — use brownfield helpers: `setPageRoute`, `setPageRequiresAuth`, `updatePage`. Do NOT touch `routePath` on `ensurePageRouteSettings()` directly (skips normalization).
- **Removing entities** — `app.removePage/Component/Collection/Table/DataStruct/Enum/ActionBlock/AppEvent/CustomFunction/CustomAction/CustomWidget/SpacingToken/RadiusToken/ShadowToken`. Fails loudly if the name is also declared in the same App. There is no `app.removeProject(...)`.
- **Edit property patches** — `page.update(selection, (patch) { ... })` exposes typed methods on `EditWidgetPatch` (`text`, `color`, `visible`, `spacing`, `padding`, `borderRadius`, `size`, `icon`, `margin`, `alignment`, `border`, `shadow`, `opacity`, etc.). Escape hatch: `page.mutateNode(selection, (node) { ... })`.

## Runtime Artifacts

- `.flutterflow/runs.jsonl`: local run history
- `.flutterflow/history/<run-id>/`: archived source files and plan
- `.flutterflow/traces/<run-id>.json`: canonical run trace
- Use `flutterflow ai history`, `flutterflow ai trace latest`, and `flutterflow ai support inspect <run-id>` to debug what happened.

## FlutterFlow Desktop Live Session

If FlutterFlow Desktop is running on this machine, the workspace's MCP server auto-pairs with it. Read `flutterflow ai docs live-session` for the full reference (tool list, worked examples, push-handling rules).

Minimum you need to know:

- Call `live.status` once per interactive session. If `paired: true`, the Desktop tools (`ide.*`, `workspace.*`, `local_run.*`, `events.*`, `live.*`) are usable; otherwise fall back to the DSL-only workflow.
- **Drain `live.pending_pushes` at the start of every user turn** — IDE "Send to FF AI" actions and runtime errors arrive here. Acknowledge each push with one visible line so the user knows it landed. Pushes override `ide.get_user_selection`.
- Persistent project changes still go through the DSL workflow (`flutterflow ai run`). Live tools are observe + push-receive only; Desktop hot-reloads automatically when the proto changes.
- Control calls (`local_run.start/stop/hot_reload/hot_restart`) require the `local_run:<project_id>` lease. Don't manually hot-reload after a DSL push — the IDE does it.

## Source Tracking

- FlutterFlow AI keeps the source that produced each run for auditability and replay.
- By default, `flutterflow ai run dsl/create.dart` or `flutterflow ai run dsl/edit.dart` tracks the executed DSL script.
- Support tooling can turn a traced run into a bundle or replay workspace with `flutterflow ai support bundle`, `flutterflow ai support replay`, or `flutterflow ai support case`.

## References

- Start from the closest working examples in `references/` before inventing new DSL structure.
- **If a widget or property fails to compile and the symbol isn't in the drift table above, check `references/` for the nearest working example before iterating.** The DSL surface is curated; when it diverges from Flutter, the right form is documented in a reference.
- If a `validate` error survives two plausible fixes (renaming the colliding name, restructuring the chain) and the error tracks whatever you renamed, run `flutterflow ai validate references/<closest-match>.dart` on the closest reference — if that fails too, the bug is in the SDK / codegen, not your script. Stop iterating and report it.
- Only use `flutterflow ai docs api-surface` or `flutterflow ai docs ui` when the references do not cover what you need or you are blocked on a specific API detail.
- `flutterflow ai docs api-surface` covers the lower-level helper contract. `flutterflow ai docs ui` covers the broader widget and action authoring surface.
- CRUD: `references/shopflow_dsl.dart`
- Task board: `references/taskboard_dsl.dart`
- Auth: `references/auth_shell_dsl.dart`
- Supabase: `references/supabase_crud_auth_shell_dsl.dart`
- Firestore: `references/social_feed_data_dsl.dart`
- Forms: `references/workflow_forms_dsl.dart`
- Shell/navigation: `references/commerce_shell_dsl.dart`
- Content generation: `references/content_companion_dsl.dart`
- Resource/library usage: `references/resource_library_dsl.dart`
- Postgres compile-only: `references/postgres_compile_only_dsl.dart`
- Action blocks: `references/action_block_showcase_dsl.dart`
- App events: `references/app_event_showcase_dsl.dart`
- GenUI: `references/genui_catalog_assistant_dsl.dart`
- Action reuse/composability: `references/taskboard_dsl.dart`
- Local state CRUD (lists, forms, per-item actions): `references/local_state_crud_dsl.dart`
- Theming, styling, layout (colors, fonts, sizing, borders, password fields): `references/styled_profile_dsl.dart`
- Media/content (horizontal lists, grids, images, text truncation, scrollable rows): `references/media_browser_dsl.dart`
- Asset/reference types (`imagePath`, `videoPath`, `audioPath`, `docRef(...)`, typed media/reference state): `references/asset_and_reference_surface_dsl.dart`
- Edit: search + filter on existing page: `references/edit_add_search_filter_dsl.dart`
- Edit: add form + detail page + navigation: `references/edit_form_and_detail_dsl.dart`
- Edit: restyle, enhance, empty states, refresh: `references/edit_restyle_and_enhance_dsl.dart`
- Edit: existing collections, components, data binding, idempotent ops: `references/edit_data_binding_dsl.dart`
- Multiple API calls with explicit `outputAs:` naming: `references/multi_api_call_dsl.dart`
- REST + GraphQL APIs (`app.api(...)`, all five HTTP methods, `Endpoint.graphql`, headers, body types, `EndpointSettings` for cache/auth/private/streaming): `references/rest_graphql_api_dsl.dart`
- Theme & design system (color slots, typography scale, spacing/radius/shadow tokens, custom fonts/icons, scrollbar, pull-to-refresh): `references/theme_design_system_dsl.dart`
- Animations + page transitions (`Lottie` / `Rive` widgets; `StartAnimation` / `StopAnimation` / `ResetAnimation` / `ReverseAnimation` / `ToggleLottie` / `ToggleRive` actions; `NavigateTransition` for page-to-page transitions): `references/triggers_and_animations_dsl.dart`
- Custom code + pub.dev packages — greenfield: pair a custom action with `http` and a custom widget with `intl` in a fresh project (`buildPubPackageShowcase`). Brownfield: add the same artifacts to an **existing** project using the `find* → add* → editPage` shape with structural inserts (`buildPubPackageEdit`, run with `--mode brownfield`). Read this when adding any pub-dep-backed feature, especially in edit flows. `references/custom_code_pub_package_dsl.dart`
- Custom Dart classes + enums used as typed args/returns via `classRef` / `customEnumRef`: `references/custom_code_classes_and_functions_dsl.dart`

## Custom code authoring

The SDK is the canonical way to add, update, and remove user-authored Dart inside a FlutterFlow project. Read `flutterflow ai docs custom-code` for the full reference (typing, validation, staging sandbox, non-goals).

Quick map:

| Artifact | DSL (greenfield) | Helper (brownfield) |
| --- | --- | --- |
| Custom function | `app.customFunction` | `addCustomFunction` |
| Custom action | `app.customAction` | `addCustomAction` |
| Custom widget | `app.customWidget` | `addCustomWidget` |
| Custom class | `app.customClass` | `addCustomClass` |
| Custom enum | `app.customEnum` | `addCustomEnum` |
| Pub dep | `app.pubDependency` / `pubDevDependency` / `pubDependencyOverride` | `addPubDependency` / `addDevDependency` / `addDependencyOverride` |

- **Greenfield vs brownfield** — DSL inside `buildApp`, helpers when editing a pulled project. Don't mix in one script.
- **Validation runs automatically** — format + identifier + shape. Catch `CustomCodeDuplicateError` / `CustomCodeValidationError`. **Not** caught: type correctness against the rest of the project — use the staging sandbox (`.ffai_staging/` + `dart analyze`) for non-trivial code that references `FFAppState` / structs / generated types.
- **Pub deps** — pub.dev discovery is your job; the SDK only records the resolution. Declare the dep next to the artifact that imports it.
- **Param typing** — `DslType` covers scalars, `listOf(T)`, `classRef(handle)`, `customEnumRef(handle)`, `app.enum_/struct` handles, Firestore/Postgres handles, `action`. For uncovered types (`Document`, `SQLiteRow`, RevenueCat, etc.) drop into `app.raw(...)` and set `FFParameter.dataType` directly.
- **Folder organization** — when the target project has `useFolderOrganizedCustomCode` on (an IDE-owned opt-in) and the **standard layout** is present (well-known `__ff_custom_code__` root), the SDK auto-files new artifacts into the synthetic `CustomCode/Functions|Widgets|Actions` tree. Pass `folderKey:` on any `add*`/`ensure*`/`update*` helper or DSL declaration to override (use `kCustomCodeFolderKey` for the synthetic root explicitly — `''` falls back to the legacy paths, NOT the root). On **adopted layouts** (rare, brownfield only — migration grafted onto a pre-existing user folder named `custom_code`), the SDK does NOT auto-default; pass `folderKey:` explicitly with the adopted folder's key (durable across IDE renames). Without it, items land unfiled at the merged panel root (codegen still resolves them via the legacy paths). On flag-off projects `folder_key` stays empty and `folderKey:` is silently ignored. The SDK never flips the flag — that's the IDE's job. Full notes: `flutterflow ai docs custom-code` → "Folder organization".

## AI Agents

Project-level AI agents in five modalities — CHAT, TTS (text-to-speech), STT (speech-to-text), IMAGE_GEN, VIDEO_GEN. Each is declared on `app.*` (greenfield) or via the matching helper (brownfield), and invoked from an action chain through the kind-specific action node.

| Kind | DSL (greenfield) | Helper (brownfield) | Action chain entry |
| --- | --- | --- | --- |
| CHAT | `app.chatAgent` | `addChatAgent` | `CallAiAgent`, `ClearAiAgentMessages` |
| TTS | `app.ttsAgent` | `addTtsAgent` | `GenerateSpeech` |
| STT | `app.sttAgent` | `addSttAgent` | `TranscribeAudio` |
| IMAGE_GEN | `app.imageGenAgent` | `addImageGenAgent` | `GenerateImage` |
| VIDEO_GEN | `app.videoGenAgent` | `addVideoGenAgent` | `GenerateVideo` |

Shared CRUD: `removeAiAgent`, `updateAiAgent`, `findAiAgent`, `listAiAgents` (all kinds; `updateAiAgent` is kind-preserving — to change kind, remove and re-add).

Sub-config value objects (in `dsl/ai_agent.dart`):
- `AiModel(provider, model, apiKey?, parameters?, messages?)` — `apiKey` may be null when Firebase AI Logic provides credentials at the workspace level.
- `AiModelParameters(temperature?, maxTokens?, topP?)` — nullable; unset stays unset.
- `AiMessage.system/user/assistant(text)` — chat-history rows for `AiModel.messages`.
- `AiResponse.plaintext/markdown/json/dataType` (CHAT only) — drives the typed output of `CallAiAgent(... outputAs:)`. `AiResponse.json(structs: [...])` constrains JSON to one or more DataStructs; `AiResponse.dataType(struct:)` returns a typed struct.
- `AiRequestInputs(plaintext:, image:, audio:, video:, pdf:, dataStructs:)` (CHAT only) — which input modalities the agent accepts.
- `AiAgentDeployment(timeoutSeconds?, memory?, requireAuthentication?, minInstances?, maxInstances?, outputTtlDays?)` — cloud-function knobs.
- `AudioInput.networkUrl(url) | AudioInput.asset(asset)` (STT only) — sealed source for `TranscribeAudio.audio`.

Provider/kind support matrix (matches the cloud-function templates in `flutterflow_codegen/lib/server/agent_util.dart`). Unsupported pairs are rejected at the SDK boundary with `AiAgentValidationError`:

| Kind | Supported providers |
| --- | --- |
| CHAT | google, openai, anthropic |
| TTS | elevenlabs |
| STT | elevenlabs |
| IMAGE_GEN | openai, google |
| VIDEO_GEN | google |

Required fields per agent (matches codegen's project validator). Every `addX/chatAgent/...` call must satisfy these or `AiAgentValidationError` fires before any proto mutation:

- non-empty `description`
- non-empty `model.model` string
- `apiKey` on `AiModel` — EXCEPT for `google + chat` (runs client-side via `firebase_vertexai`). Google IMAGE_GEN and VIDEO_GEN both need a key.
- For CHAT: at least one `AiMessage.system(...)` in `model.messages`, a non-empty `requestInputs` (at least `AiRequestInputs(plaintext: true)`), and a non-null `response`.

Greenfield CHAT example:

```dart
final intent = app.struct('Intent', {'name': string});
final classifier = app.chatAgent(
  'Classifier',
  description: 'Classifies user intent.',
  model: AiModel(
    provider: AiModelProvider.openai,
    model: 'gpt-4o-mini',
    apiKey: 'sk-...',
    messages: [AiMessage.system('You are a JSON-only classifier.')],
  ),
  requestInputs: AiRequestInputs(),
  response: AiResponse.json(structs: [intent]),
);
app.state('chatId', string);
Button(onTap: [
  CallAiAgent(
    classifier,
    conversationId: AppState('chatId'),
    message: State('userQuery'),
    outputAs: 'reply',
  ),
  SetState('lastReply', value: ActionOutput('reply')),
]);
```

`CallAiAgent` and `ClearAiAgentMessages` both require `conversationId:` — codegen validates send/clear pairing by matching this value. Use a stable per-thread string (`AppState('currentChatId')`).

Multimodal CHAT inputs map to the proto `*_input` oneofs and reuse the same sealed types where possible:

```dart
CallAiAgent(
  assistant,
  conversationId: AppState('chatId'),
  message: State('q'),
  image: ImageInput.networkUrl(AppState('imageUrl')),
  audio: AudioInput.asset(AppState('voiceClip')),
  video: VideoInput.asset(AppState('clip')),
  pdf: PdfInput.upload(AppState('document')),
);
```

Edit-mode (brownfield) example — resolve by name and let the compile pass verify the kind:

```dart
Button(onTap: [
  GenerateSpeech.named('Narrator', text: State('lineToRead'), outputAs: 'audio'),
]);
```

Validation runs before any proto mutation. Failures throw a typed subclass of `AiAgentError` — catch `AiAgentDuplicateError`, `AiAgentNotFoundError` (carries did-you-mean), `AiAgentValidationError` (carries `issues:`), or `AiAgentKindMismatchError`. Action targets are kind-checked at compile time: handle-typed call sites are statically safe; `*Agent.named(...)` defers to the compile pass.

Out of scope:
- Model catalog sync — server-side; the SDK assumes models are valid strings.
- Firebase AI Logic credentials — assumed pre-configured at the workspace level when `apiKey` is null on a `google + chat` agent.
- `endpointUrl`, `editMetadata`, `createdAt`, `updatedAt` — runtime-managed proto fields, not surfaced.
- `AiResponse.dataType` + `outputAs:` — codegen TODO (ENG-5623). The SDK throws at `CallAiAgent` construction time if you try to bind a typed-struct output until codegen catches up.

## Deprecated proto fields are OFF LIMITS

When you drop into `app.raw((project) { ... })` (or any helper that hands you a raw proto message), **never read or write any field annotated `[deprecated = true]` in `flutterflow.proto`** — and never write a field named `legacy_*`. Codegen reads only the modern fields; data written to the deprecated pair is invisible to codegen and can crash it.

The canonical landmine is `FFConditionActions`:

```proto
message FFConditionActions {
  FFActionCondition legacy_condition   = 1 [deprecated = true]; //  do NOT use
  FFActionNode      legacy_true_action = 2 [deprecated = true]; //  do NOT use
  repeated FFTrueConditionAction true_actions = 4;              //   modern shape
  FFActionNode      false_action       = 3;
}
```

Walking the schema and picking the two scalar fields that look like "condition + true action" lands you in the deprecated pair. Codegen (`generateConditionActionsCode`) then reads `trueActions.first` and crashes with `Bad state: No element` — the SDK now rejects this shape at compile time with `MalformedConditionActionsError` so you'll see the failure before the push lands.

**Always build conditional action chains with the typed builders** — they emit the modern `true_actions[0]` shape and the SDK validators pass them by construction. There is no legitimate reason to reach for `app.raw` to construct a conditional:

```dart
// if/else
Actions.conditional(
  condition: someBoolVariable,
  trueActions: Actions.chain([Actions.snackBar('Yes')]),
  falseActions: Actions.chain([Actions.snackBar('No')]),
);

// if/else-if/else
Actions.conditionalMulti(
  branches: [
    (condition: isPremium, actions: premiumChain),
    (condition: isTrial,   actions: trialChain),
  ],
  fallback: Actions.chain([Actions.snackBar('Free tier')]),
);
```

The general rule: any field with `[deprecated = true]` or a name starting with `legacy_` is for backwards-compatible reads by other consumers — never write to them. If you're not sure, use the typed DSL/helper surface. If the typed surface really doesn't cover what you need, ask first; don't poke deprecated proto fields.


## Project

- `projectId`: `voetbalplanner-76ly9n`
- `baseUrl`: `https://api.flutterflow.io/v2`
