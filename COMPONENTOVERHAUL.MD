# HubTube Vue UI Overhaul — Reka UI + VueUse

The public frontend (Laravel + Inertia.js + Vue 3 + Tailwind v4, `resources/js/`) has no component/primitive library. Every dropdown, modal, popover, and search-suggestion panel is hand-built: manual `ref` booleans, manual `document.addEventListener('click', ...)` outside-click detection (often via fragile `e.target.closest('.some-class')` string matching), manual `setTimeout`-based debounce, manual `ResizeObserver`/`IntersectionObserver` wiring, and no consistent focus-trap/Escape/scroll-lock handling across ~10 modals. `@vueuse/core` was already a dependency but was barely used (only `useVirtualList`/`useWindowSize` in two composables) despite being able to replace most of this hand-rolled browser-interaction code with maintained, auto-cleanup composables.

Goal: make the codebase measurably cleaner and more robust by (1) replacing hand-rolled browser-interaction logic with `@vueuse/core` composables (zero new dependencies, low risk), and (2) introducing `reka-ui` (unstyled, accessible Vue 3 primitives — formerly Radix Vue) for interactive UI patterns (dropdowns, dialogs, tabs, combobox, toast), fixing latent accessibility gaps (no focus trap, no Escape handling, no scroll lock on most modals) for free, while keeping the site's existing Tailwind visual design via two new thin wrapper components.

Two incidental bug fixes are accepted as intentional side effects of the Reka UI conversions (per decision made when this plan was approved): the mobile "More" popup will gain outside-click dismissal it never had, and `Playlists.vue`'s create-modal will gain proper `<body>` portalling. Both get called out explicitly in their commit messages when they land, not silently.

---

## Dependencies

- Add **`reka-ui`** (`npm install reka-ui`) when Phase 1 starts — not yet installed. No CSS import needed (fully unstyled). No plugin registration needed in `resources/js/app.js` (imported per-component, same tree-shakeable pattern as `@vueuse/core`).
- `@vueuse/core` is already installed (`^10.7.2`) — sufficient for every composable used in Phase 0.

---

## Phase 0 — VueUse quick wins ✅ DONE

No new dependency. Mechanical replacements of hand-rolled browser-interaction logic, verified with a clean `npx vite build` after each batch.

- [x] **Clipboard copy** — `Components/ShareModal.vue`: `useClipboard()` replaces the manual `execCommand` fallback + manual reset timer.
- [x] **Media queries** — `Components/AdInterstitial.vue` and `Components/GridAdSlot.vue`: `useMediaQuery` replaces manual `matchMedia`/`window.innerWidth` checks with reactive refs.
- [x] **Storage** — `Components/AdInterstitial.vue` (page-view counter) and `Composables/useChunkedUpload.js` (resumable-upload persistence): `useStorage` replaces manual `sessionStorage` JSON get/set + try/catch.
- [x] **Script loading** — `Composables/useImaAd.js`: `useScriptTag` (manual mode) replaces the hand-built `<script>` tag injection for the Google IMA SDK.
- [x] **Debounce** — `Layouts/AppLayout.vue` search suggestions: `useDebounceFn` replaces the manual `suggestTimeout` + `setTimeout`/`clearTimeout` pair.
- [x] **Scroll tracking** — `Layouts/AppLayout.vue` mobile-nav show/hide: `useWindowScroll` + `watch` replaces the manual `window.addEventListener('scroll', ...)` + `lastScrollY` ref + manual cleanup pair.
- [x] **Outside-click** — `Components/LanguageSwitcher.vue`, `Pages/Videos/Show.vue` (Save-to-Playlist menu), `Pages/Images/Index.vue`, `Pages/Videos/Index.vue`: `onClickOutside` replaces each hand-rolled `document.addEventListener('click', ...)` + `.contains()`/`.closest()` check, with auto-cleanup on unmount.
- [x] **Resize/Intersection observers** — `Components/MasonryGrid.vue` (column-count on resize), `Components/SponsoredVideoCard.vue` (impression-on-visible, self-stopping), `Components/ShortsAdSlide.vue` (play/pause on visibility), `Components/OutstreamAd.vue` (play/pause on visibility): `useResizeObserver`/`useIntersectionObserver` replace manual observer creation + `disconnect()` in `onUnmounted`.
- [x] **Event listeners** — `Pages/Shorts/Index.vue`: the `window` keydown listener and the feed-container scroll listener now use `useEventListener`, dropping the manual add/remove pairs in `onMounted`/`onUnmounted`.

**Note — deliberately NOT converted:** `Pages/Shorts/Index.vue`'s `setupIntersectionTracking()` (~lines 240-273), the multi-slide autoplay-tracking `IntersectionObserver`. It observes a *dynamic array* of slide elements that grows as more shorts load via infinite scroll; today's code only ever observes whatever slides exist at the moment it's called (a pre-existing limitation, not introduced by this overhaul). Converting it cleanly means deciding whether to preserve that limitation or fix it by reactively re-observing newly-added slides — the latter is a behavior change beyond Phase 0's "mechanical replacement, no behavior change" scope. Left as manual `IntersectionObserver` code for now; revisit as a deliberate, called-out change if wanted (either in a follow-up to Phase 0, or bundled into whichever later phase touches Shorts/Index.vue's dropdown, per Phase 5 below).

**Committed** — this note previously said the Phase 0 edits were uncommitted, but they landed in `56ef7fc` ("Refactor SEO and translation fallback system") along with unrelated work, and the working tree is clean of them.

---

## Phase 1 — Shared wrapper components ✅ DONE

Added to the existing `resources/js/Components/UI/` folder, alongside `BannerAd.vue`, `Breadcrumbs.vue`, `EmptyState.vue`, `PageHeader.vue`.

- [x] `npm install reka-ui` — installed `reka-ui@2.10.3`. No CSS import, no plugin registration; imported per-component.
- [x] `Components/UI/BaseDialog.vue` — wraps `DialogRoot`/`DialogPortal`/`DialogOverlay`/`DialogContent` (or the `AlertDialog*` set when `variant="alert"`), selected via a `parts` computed map so both variants share one template.
  - Props: `modelValue: Boolean` (matches `ShareModal.vue`/`ReportModal.vue`'s existing `v-model:modelValue` pattern), `variant: 'dialog' | 'alert'`, `dismissable: Boolean` (default `null` = follow the variant; explicit override for the age-gate belt-and-suspenders case), `maxWidth` (Tailwind class passthrough, default `max-w-md`), `title` (optional standard header + close button; omit for a custom `header` slot), `transitionName` (default `base-dialog`), `contentClass`.
  - Two props added beyond the plan, both for accessibility: `ariaLabel` and `description`. Reka needs a `DialogTitle` for the accessible name and warns without one, so when `title` is omitted the component always renders a `VisuallyHidden` title from `ariaLabel`; `description` renders a `VisuallyHidden` `DialogDescription`.
  - Slots: default (body), `header` (override), `footer` (action-button row). Default and footer slots expose a `close` scope prop.
  - Always portals to `<body>` internally (`DialogPortal`).
  - Non-dismissability is enforced by preventing Reka's `escape-key-down`, `pointer-down-outside` and `interact-outside` events, and by hiding the close button — the plan's Phase 3 requirement to not trust Reka's `AlertDialog` defaults is handled here rather than per-call-site, but still needs the hands-on verification Phase 3 calls for.
  - Panel styling: `card rounded-xl shadow-2xl bg-bg-card`, appended-to by `contentClass`. The backdrop always plain-fades (its own `base-dialog-overlay` transition) so a scaling `transitionName` can't reveal the page edges.
- [x] `Components/UI/BaseDropdown.vue` — wraps `DropdownMenuRoot`/`DropdownMenuTrigger`/`DropdownMenuPortal`/`DropdownMenuContent`.
  - Props: `align` (default `'end'`), `side` (default `'bottom'`), `sideOffset` (default `8`), `contentClass` (width/max-height/padding overrides), plus an optional `modelValue` for the call sites that need to drive open state themselves (default `null` = uncontrolled).
  - Slots: `trigger` (rendered with `as-child`, so the caller's own button element is the trigger; receives an `open` scope prop for chevron/active state), default (raw `DropdownMenuContent` — children are NOT forced into `DropdownMenuItem`, so the Save-to-Playlist dropdown can mix real items with a plain create-input using `@select.prevent`).
  - Default panel styling deliberately carries no padding (`card shadow-xl bg-bg-card border border-border z-[9999]`) — menus that want `p-1` pass it via `contentClass`, since Tailwind can't reliably override a padding utility by appending another one.
- [x] Not building `BaseCombobox.vue`/`BaseTabs.vue` yet — each pattern has only 2-3 call sites with meaningfully different data shapes; revisit after Phase 5 if duplication actually appears.

---

## Phase 2 — Pilot: AppLayout.vue dropdowns + Login Modal (not started)

Highest-value, highest-traffic file — proves the wrapper API before it's baked into 8 more call sites. Commit each sub-step separately so a regression can be bisected/reverted independently.

- [ ] Login Modal (`AppLayout.vue`, currently ~lines 1046-1132) → `BaseDialog` first — self-contained, one form.
- [ ] Upload dropdown (~lines 517-533) → `BaseDropdown` — simplest, short static list.
- [ ] Notifications dropdown (~lines 543+) → `BaseDropdown` — verify `unreadCount` badge logic still fires on open.
- [ ] User account menu (~lines 583+) → `BaseDropdown`.
- [ ] Mobile Upload popup (~lines 966+) and mobile "More" popup (~lines 994+) → `BaseDropdown`. **Note:** the More popup gains outside-click dismissal it never had — intentional, call out in the commit message.
- [ ] Header "mega menu" (~lines 654+, admin-configurable, grid layout + hover-open semantics) — leave for last; decide concretely whether `DropdownMenu` or `NavigationMenu` fits once the other 5 are done. Don't let it block shipping the rest.
- [ ] Once all 5 are converted: delete `closeDropdowns()` and its `addEventListener`/`removeEventListener` pair, **except** `closeSuggestionsOnOutside` — extract that into its own `onClickOutside` call now (small bonus cleanup) since Combobox work is deferred to Phase 5.
- [ ] Check `resources/css/app.css` for styling keyed off `.notification-dropdown`/`.user-menu-dropdown`/`.upload-menu-dropdown` class-name selectors used by the old `.closest()` checks — port to `contentClass` on `BaseDropdown` if needed, don't silently drop styling. **Checked during Phase 1:** those three selectors do exist, at `resources/css/app.css:356-373`, inside a `@media (max-width: 480px)` block that forces `position: fixed !important` plus `left`/`right`/`width` overrides to make the panels near-full-width on small phones. Do not just re-apply those class names via `contentClass` — `position: fixed !important` will fight Reka's popper, which positions the content with its own absolute/transform styles. Either rewrite that media query for the Reka panels (e.g. drop the `position` override and constrain width only) or use Reka's collision/`avoidCollisions` handling and delete the block.

**Note:** line numbers above reflect `AppLayout.vue` as of this session (after the Phase 0 edits and the earlier menu-bar/language-switcher relocation work) — re-verify exact ranges at implementation time since further edits shift them.

---

## Phase 3 — Safety-critical AlertDialogs (not started)

Do these right after the pilot, while the `BaseDialog` API is fresh. Treat as one reviewed, isolated unit.

- [ ] `Components/AgeVerificationModal.vue` (~lines 90-169) → `BaseDialog variant="alert"`. **Before converting, write down today's exact behavior** (no close button, backdrop click does nothing, no Escape handler) and explicitly verify all three are preserved after conversion — do not assume Reka's `AlertDialogContent` defaults match; test Escape and backdrop-click by hand.
- [ ] `Pages/Settings.vue` Disable 2FA modal (~lines 678-708) → `BaseDialog variant="alert"`.
- [ ] `Pages/Settings.vue` Delete Account modal (~lines 711-765) → `BaseDialog variant="alert"`. After conversion, re-verify Cancel sends no request and closes cleanly, and Confirm still fires the correct Inertia request (check network tab) with the right success/error toast.

---

## Phase 4 — Remaining Dialogs (not started)

Order: `ShareModal.vue` (already has `v-model:modelValue`, near drop-in) → `ReportModal.vue` (same pattern) → `Pages/Playlists.vue` Create-Playlist modal (**note:** also fixes its non-portalled clipping bug — intentional, call out in commit) → `AdInterstitial.vue` → `KeyboardShortcuts.vue` (delete its hand-rolled Escape handler in favor of Dialog's built-in) → `Lightbox.vue` (shell only; keep custom pan/zoom/arrow-key logic inside `Dialog.Content`; **reconcile its manual body-scroll-lock with Reka's own** — pick one (Reka's), delete the manual one, verify no leftover `overflow:hidden`/scrollbar-padding on `<body>` after repeated open/close).

- [ ] `Components/ShareModal.vue`
- [ ] `Components/ReportModal.vue`
- [ ] `Pages/Playlists.vue` Create-Playlist modal
- [ ] `Components/AdInterstitial.vue`
- [ ] `Components/KeyboardShortcuts.vue`
- [ ] `Components/Lightbox.vue`

---

## Phase 5 — Remaining DropdownMenus, Combobox, Tabs, Toast (not started)

- [ ] `Components/LanguageSwitcher.vue` → `BaseDropdown` (3 trigger variants sharing one panel — tests slot flexibility).
- [ ] `Pages/Videos/Show.vue` Save-to-Playlist dropdown → `BaseDropdown`, with `@select.prevent` on the create-playlist input row (Reka closes menu on item-select by default — must not close before the create request completes).
- [ ] `Pages/Shorts/Index.vue` ⋮ popover → `BaseDropdown` (**note:** also gains outside-click dismissal it never had — intentional, call out in commit). Good opportunity to also revisit the deferred `setupIntersectionTracking()` item from Phase 0 while this file is open.
- [ ] **Combobox search-autocomplete** — extract shared logic *before* converting UI, since `AppLayout.vue`'s header search and `Pages/Videos/Show.vue`'s page search duplicate the same fetch/debounce/keyboard-nav shape with hand-rolled `activeSuggestionIndex` arithmetic:
  1. [ ] Write `Composables/useSearchSuggestions.js` extracting `AppLayout.vue`'s fetch logic (now using `useDebounceFn`, already done in Phase 0) into `{ suggestions, isLoading, search }`. Deploy as pure refactor first (AppLayout only), verify search behavior is unchanged.
  2. [ ] Wire `Pages/Videos/Show.vue`'s search to the same composable, deleting its duplicate fetch logic.
  3. [ ] Only then replace both UIs' hand-rolled arrow/Enter/Escape logic with Reka's `Combobox`, feeding it the shared composable's `suggestions`.
- [ ] **Tabs**: `Pages/Settings.vue` first (self-contained `activeTab` ref) → `Pages/Channel/Playlists.vue` (`currentTab` synced to a URL query param via `switchTab()`/`router.get`; keep `switchTab()` as the `@update:modelValue` handler on `Tabs.Root` rather than introducing a second source of truth).
- [ ] **Toast**: `Components/ToastContainer.vue` last — swap only internal rendering (`ToastProvider`/`ToastRoot`/`ToastViewport`) while keeping `Composables/useToast.js`'s public API (`success/error/warning/info`) and its module-level singleton `toasts` array completely unchanged (40+ call sites depend on that API).

---

## Phase 6 — Optional / low priority (do opportunistically, or skip)

- [ ] Native `<select>` → Reka `Select` in `ReportModal.vue`, `Shorts/Index.vue` filters, `Videos/Create.vue` (only if custom option rendering is actually wanted).
- [ ] `Switch` for `Settings.vue` notification checkboxes (cosmetic only, not an accessibility fix).
- [ ] Net-new `Tooltip` additions (not a migration — separate feature request if wanted; no hand-rolled tooltip exists today, all `title="..."` attributes rely on native browser tooltips).

---

## Risk Callouts (apply across all remaining phases)

- **`AgeVerificationModal.vue` non-dismissability** is the single highest-stakes behavior to preserve — verify explicitly in Phase 3, don't assume Reka defaults match.
- **Singleton composable state must survive untouched**: `Composables/useToast.js`'s module-level `toasts` (`reactive([])` at module scope), `Composables/useTheme.js`'s `currentTheme`, `Composables/useGlobalAutoTranslate.js`'s `_translationCache`. The Phase 5 Toast conversion must render Reka's `ToastRoot`s directly off the existing shared array, not a local copy.
- **`Videos/Show.vue` Save-to-Playlist dropdown**: Reka's default "close on item select" could break the inline create-new-playlist input — needs explicit `@select.prevent` and dedicated manual testing, not just a generic dropdown smoke test.
- **`Lightbox.vue`'s dual scroll-lock risk** — must delete the manual implementation once Reka's Dialog scroll-lock is in place; running both could double-toggle and fail to unlock cleanly.
- **`AppLayout.vue` is the most-trafficked file in the app** — each Phase 2 sub-conversion should be its own commit so a regression is bisectable.
- **CSS selector dependency check**: before deleting `closeDropdowns()`'s class-name references, confirm `resources/css/app.css` doesn't separately style-key off those same class names.

---

## Verification Approach (no test suite exists — manual, via `npm run dev`)

**Every DropdownMenu conversion**: trigger opens it; click elsewhere closes it; opening a second menu closes the first; `Escape` closes and returns focus to trigger; arrow keys navigate items; scrolling the page keeps the menu anchored or closes gracefully; test on a mobile viewport for touch event ordering. Save-to-Playlist specifically: type + Enter in the create input must not close the menu before the request completes.

**Every Dialog/AlertDialog conversion**: backdrop appears and focus moves into the dialog; `Tab`/`Shift+Tab` stays trapped inside; `Escape` closes (except AlertDialog variants); closing returns focus to the trigger; body scroll is locked while open and unlocked immediately on close (special attention to Lightbox). Login Modal: submit with a wrong password — dialog must stay open showing the error. Settings destructive confirms: Cancel sends no request; Confirm fires the correct request and shows the right toast. AgeVerificationModal: reload with the gating cookie/localStorage cleared and confirm there is truly no way to dismiss it except the intended choice.

**Combobox**: typing debounces requests; arrow keys move through the combined video+channel list in the existing order; Enter on a highlighted item navigates correctly; Enter with nothing highlighted falls back to full search-page navigation; Escape closes without navigating; desktop and mobile variants return identical results after the shared-composable extraction.

**Tabs**: click and arrow-key navigation both switch panels correctly; `Channel/Playlists.vue` specifically — switching tabs still updates the URL query param, and reloading with that param pre-selects the right tab.

**Phase 0 VueUse items** (for regression-checking the work already done): `onClickOutside` sites — click-outside-closes check on each; `useClipboard` — Copy button fires toast, clipboard actually contains the URL, "Copied!" label reverts after ~2s; `useMediaQuery` sites — resize across the 640px/768px breakpoints live, no reload needed; `useStorage` — start a chunked upload, refresh mid-upload, confirm resume-from-persisted-state still works; scroll-based mobile nav — hide/show timing near the footer matches prior behavior.

---

## Critical Files

- `resources/js/Layouts/AppLayout.vue` — pilot phase, most-trafficked file
- `resources/js/Components/UI/BaseDialog.vue` (new, Phase 1)
- `resources/js/Components/UI/BaseDropdown.vue` (new, Phase 1)
- `resources/js/Components/AgeVerificationModal.vue` — safety-critical
- `resources/js/Pages/Settings.vue` — safety-critical (2FA/delete-account) + Tabs
- `resources/js/Composables/useToast.js` — singleton state, preserve API
- `resources/js/Pages/Videos/Show.vue` — Combobox + dropdown-with-input edge case
- `resources/js/Composables/useSearchSuggestions.js` (new, Phase 5)
- `resources/css/app.css` — verify no orphaned class-selector styling after cleanup
