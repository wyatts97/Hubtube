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

## Phase 2 — Pilot: AppLayout.vue dropdowns + Login Modal ✅ CODE DONE, MANUAL TESTING PENDING

Highest-value, highest-traffic file — proves the wrapper API before it's baked into 8 more call sites. **Not committed** (user's call): all six conversions sit together in the working tree, so a regression has to be found by reading the diff rather than by bisecting.

- [x] Login Modal → `BaseDialog`. Kept the old `showLoginModal && !user` guard as a writable `loginModalOpen` computed. The `autofocus` attribute on the email field was replaced by an `@open-auto-focus` handler, because Reka focuses the first tabbable element (the close button) on open — this required adding an `openAutoFocus` emit to `BaseDialog`. Passes `transition-name="login-modal"` to preserve the old behavior, though note **no CSS is defined for `login-modal` anywhere**, so that transition was and still is a no-op; drop the prop to get `BaseDialog`'s default fade. Minor visual change: `BaseDialog`'s panel uses `.card`, which adds a 1px border the old panel didn't have.
- [x] Upload dropdown → `BaseDropdown`.
- [x] Notifications dropdown → `BaseDropdown`, controlled via `:model-value` + `@update:model-value="onNotificationsOpenChange"` (the old `toggleNotifications`, rewritten to take the new open state) so the fetch-and-mark-all-read-on-open behavior is preserved. Each notification is a `DropdownMenuItem as-child` for arrow-key navigation, with `@select.prevent` on the ones that have no URL so a non-link notification doesn't close the menu.
- [x] User account menu → `BaseDropdown`, each row a `DropdownMenuItem as-child`, the username/email block a `DropdownMenuLabel`.
- [x] Mobile Upload popup and mobile "More" popup → `BaseDropdown` with `side="top"`. **The More popup gained outside-click dismissal it never had** — intentional. Both popups' `showMobileUploadMenu`/`showMobileMoreMenu` refs are gone; `handleMobileNavClick` now only handles the `search` action.
- [x] Header "mega menu" → `BaseDropdown` after all. The plan flagged possible hover-open semantics, but the code was click-only (`@click.stop="toggleMegaMenu"`), so `DropdownMenu` fits and `NavigationMenu` was unnecessary. The grid layout is passed through as the default slot with `content-style` for the computed `minWidth`, which required adding a `contentStyle` prop to `BaseDropdown`. Converting it meant `closeDropdowns()` could be deleted outright.
- [x] `closeDropdowns()` and its `addEventListener`/`removeEventListener` pair are deleted, along with `showUserMenu`, `showUploadMenu`, `openMegaMenu`, `toggleMegaMenu`, `closeMegaMenu`, `showMobileUploadMenu` and `showMobileMoreMenu`. `closeSuggestionsOnOutside` is now a single `onClickOutside(suggestionsRef, …, { ignore: [searchInputRef] })` call, as planned, since Combobox work is deferred to Phase 5.
- [x] **`BaseDropdown` defaults to `:modal="false"`** — a decision made during this phase. A modal Reka menu locks page scroll (wrong for a header nav menu) and swallows the click that opens a sibling menu, so switching between two header menus would have taken two clicks instead of one.
- [x] Check `resources/css/app.css` for styling keyed off `.notification-dropdown`/`.user-menu-dropdown`/`.upload-menu-dropdown` class-name selectors used by the old `.closest()` checks — port to `contentClass` on `BaseDropdown` if needed, don't silently drop styling. **Checked during Phase 1:** those three selectors do exist, at `resources/css/app.css:356-373`, inside a `@media (max-width: 480px)` block that forces `position: fixed !important` plus `left`/`right`/`width` overrides to make the panels near-full-width on small phones. Do not just re-apply those class names via `contentClass` — `position: fixed !important` will fight Reka's popper, which positions the content with its own absolute/transform styles. Either rewrite that media query for the Reka panels (e.g. drop the `position` override and constrain width only) or use Reka's collision/`avoidCollisions` handling and delete the block. **Resolved:** the block was deleted (a comment marks the spot) and Reka's built-in collision detection now keeps the panels on-screen; the notification panel additionally caps itself with `w-[min(20rem,calc(100vw-1rem))]`. The `.upload-menu-dropdown` rule was dead anyway — that dropdown is `hidden sm:block`, so a `max-width: 480px` rule could never apply to it.

**Still to verify by hand** (`npm run dev`) — the code builds clean but nothing in this phase has been clicked through yet. Beyond the generic dropdown checklist below, the specific risks introduced here: notification links navigating correctly through `DropdownMenuItem as-child` + Inertia `Link`; the mega menu's grid layout surviving the move into a portalled panel; the two mobile dock popups anchoring correctly above the floating dock (`side="top"`, `:side-offset="12"`) and behaving when the dock hides on scroll while a popup is open; and login-modal focus landing on the email field rather than the close button.

---

## Phase 3 — Safety-critical AlertDialogs ✅ CODE DONE, MANUAL TESTING PENDING

Done right after the pilot, while the `BaseDialog` API was fresh. **Not committed** — sits in the working tree with Phases 1-2.

### Age gate: behavior recorded before conversion

Read off `AgeVerificationModal.vue` as it stood before any edit:

1. **No close button** — none existed in the markup.
2. **Backdrop click does nothing** — the overlay `div` carried no `@click` handler at all. (The inner `@click.stop` was dead defensive code, since there was no parent handler to stop.)
3. **No Escape handling** — no `keydown` listener was registered anywhere in the component.
4. **No focus trap** — Tab could walk into the page behind the gate and even follow its links.
5. **No body scroll lock** — the page behind the fixed overlay scrolled freely.

### Age gate: how each was verified after conversion

The plan said not to assume Reka's `AlertDialogContent` defaults match, so this was checked against Reka's actual source rather than by inspection of the docs:

- `DismissableLayer` has exactly three dismiss paths (`node_modules/reka-ui/dist/DismissableLayer/DismissableLayer.js`): `pointerDownOutside`, `focusOutside` and the Escape `onKeyStroke`. Each emits its event and then emits `dismiss` **only** `if (!event.defaultPrevented)`. `focusOutside` and `pointerDownOutside` both also emit `interactOutside`.
- `BaseDialog` calls `event.preventDefault()` on `escapeKeyDown`, `pointerDownOutside` **and** `interactOutside` whenever it is non-dismissable, which covers all three paths — `focusOutside` included, via `interactOutside`.
- Independently, `AlertDialogContent` itself hard-prevents `pointerDownOutside` and `interactOutside` (`withModifiers(() => {}, ["prevent"])`), and `mergeProps` array-merges those with ours, so outside-click is blocked twice over. **It does not prevent Escape** — that one is blocked only by `BaseDialog`, which is exactly why the plan's "don't assume the defaults match" warning was right.
- Items 4 and 5 are now **fixed rather than preserved**, and both tighten the gate: Reka traps focus inside the dialog and locks body scroll. Worth knowing, since it means the page behind the gate is no longer reachable by keyboard.
- Focus on open lands on the "I am 18 or older" button (Reka's first-tabbable default; `AlertDialogContent` also tries to focus an `AlertDialogCancel`, which this dialog does not use).

**Still needs a hands-on pass**: reload with the `age_verified` cookie cleared and confirm by hand that Escape, backdrop click, and Tab all fail to dismiss or escape it, per the plan's verification section.

- [x] `Components/AgeVerificationModal.vue` → `BaseDialog variant="alert" :dismissable="false"`. Needed three new `BaseDialog` props: `unstyled` (the gate paints its own shell — heading block outside a card, buttons inside one — so the default card panel and body padding had to go), `overlayStyle` (the gate's backdrop color and blur are admin-configurable) and `contentStyle` (its `fontFamily` is too, and the panel is no longer a child of the overlay so it can't inherit).
- [x] `Pages/Settings.vue` Disable 2FA modal → `BaseDialog variant="alert"`.
- [x] `Pages/Settings.vue` Delete Account modal → `BaseDialog variant="alert"`. Cancel is now a named `cancelDeleteAccount` / `cancelDisable2fa` handler rather than an inline expression; both close the dialog and clear the typed password, and neither sends a request. Confirm paths (`confirmDeleteAccount`, `disableTwoFactor`) were not touched. **Still to check by hand:** that Confirm fires the correct request in the network tab with the right success/error toast.

**Behavior change on the two Settings modals — worth a decision.** Both were previously dismissable by backdrop click (`@click.self` plus an overlay `@click`); as `variant="alert"` they no longer are, so the user must click Cancel. That follows the plan, and it incidentally fixes a real inconsistency — backdrop-dismissing the 2FA modal used to leave the typed password sitting in `disable2faPassword`, while the Cancel button cleared it. But it does remove a familiar escape hatch from two dialogs where dismissing was the *safe* outcome. If that reads as too strict, `:dismissable="true"` on either one restores backdrop and Escape dismissal without giving up the AlertDialog role.

---

## Phase 4 — Remaining Dialogs ✅ CODE DONE, MANUAL TESTING PENDING

Converted in the planned order. **Not committed** — sits in the working tree with Phases 1-3.

- [x] `Components/ShareModal.vue` — near drop-in as expected. Its local `close()` became unused (BaseDialog's header close button replaced the hand-rolled one) and was deleted.
- [x] `Components/ReportModal.vue` — same pattern. **Gains a close button** it didn't have: it used the `title` prop, which renders BaseDialog's standard header. Cancel and backdrop-dismiss both still work.
- [x] `Pages/Playlists.vue` Create-Playlist modal — **now portalled to `<body>`, fixing the clipping bug**, as intended. Its `v-motion` enter/leave directive was dropped in favour of BaseDialog's own transition, since the panel is BaseDialog's element now and can't carry the directive; the visual difference is a 0.2s slide-up becoming a 0.15s fade/scale. The `X` icon import went unused and was removed.
- [x] `Components/AdInterstitial.vue` — the countdown gate (`@click.self="countdown <= 0 ? close() : null"`) is now `:dismissable="countdown <= 0"`, which **also gates Escape** — previously Escape did nothing at all, so post-countdown Escape-to-close is new behavior. Its `<style scoped>` block had to become unscoped: the `interstitial-*` transition classes are applied by BaseDialog to elements it renders, so a scoped rule keyed to this component's `data-v` attribute would never have matched them.
- [x] `Components/KeyboardShortcuts.vue` — hand-rolled Escape branch deleted in favour of Dialog's built-in; the remaining `?`-toggle listener moved to `useEventListener`, dropping its `onMounted`/`onUnmounted` pair. Uses the `#header` slot (its heading carries an icon), which is why `BaseDialog`'s `header` slot now also exposes `close`.
- [x] `Components/Lightbox.vue` — shell only. All pan/zoom, arrow-key and thumbnail logic stayed put; only Escape moved out of `onKeydown` into Dialog's handler, and the remaining listener moved to `useEventListener`. **The manual body-scroll-lock is deleted** (both the `watch` that set `document.body.style.overflow` and the `onUnmounted` reset) — Reka's is now the only one, per the plan's "pick one". A grep confirms no `document.body.style` writes remain anywhere in `resources/js/`. Because the lightbox fills the viewport, `contentClass="fixed inset-0"` lets the panel escape BaseDialog's padded centring wrapper; its own inner `absolute inset-0` div keeps the click-background-to-close behavior, which Reka's outside-click could not provide here (nothing is ever "outside" a full-viewport panel).

**Two `BaseDialog` changes this phase**: the `relative` class moved into the styled (non-`unstyled`) branch, so an `unstyled` caller can position its panel itself; and the `header` slot now receives `close`.

**Still to verify by hand** (`npm run dev`), on top of the generic dialog checklist: the lightbox opened and closed repeatedly, confirming `<body>` is left with no leftover `overflow: hidden` or scrollbar padding; the interstitial ad's third-party `AdSlot` markup behaving inside a focus-trapped, `aria-hidden`-siblings modal (this is the one conversion where a third party renders inside the dialog); and the Playlists modal no longer clipping.

---

## Phase 5 — Remaining DropdownMenus, Combobox, Tabs, Toast ✅ CODE DONE, MANUAL TESTING PENDING

- [x] `Components/LanguageSwitcher.vue` → `BaseDropdown`. All three trigger variants live in one `#trigger` slot behind `v-if`/`v-else-if`/`v-else` and share the panel, driven by the slot's `open` prop instead of the old `showDropdown` ref. Its `direction`/`align`/`compact` props now map onto Reka's `side`/`align` via two computeds. **A wrapper `<div>` was required around `BaseDropdown`**: both call sites pass layout classes (`md:hidden`, `shrink-0 ms-4`) that fall through to the component root, and `DropdownMenuRoot` is a renderless fragment that cannot receive them — without the wrapper the mobile switcher would have shown on desktop. The per-item `@mouseenter`/`@mouseleave` inline-style hover hack is replaced by `data-[highlighted]:bg-bg-secondary`.
- [x] `Pages/Videos/Show.vue` Save-to-Playlist dropdown → `BaseDropdown`. **`@select.prevent` is on every row, not just the create-input** — the plan only flagged the input, but the playlist rows are a multi-toggle checklist that must also survive a click without the menu closing. Reka's menu typeahead was the other worry; it turns out `MenuContentImpl` explicitly skips typeahead when the keydown target is an `input`/`textarea`, so the create field is safe. The guest branch (previously `user ? toggle : router.visit('/login')`) is now a separate plain button, since a Reka trigger should not conditionally not-be-a-trigger.
- [x] `Pages/Shorts/Index.vue` ⋮ popover → `BaseDropdown` (`side="top"`). **Gains outside-click dismissal it never had** — intentional. The `showMoreMenu` ref and its three manual resets are gone, as is the `currentIndex === index` render guard (the menu now belongs to its trigger).
- [x] **Deferred Phase 0 item resolved while the file was open**: `setupIntersectionTracking()` is now `useIntersectionObserver(slides, …)`. VueUse re-creates the observer whenever the target array changes, so **slides appended by infinite scroll are now tracked** — the pre-existing limitation is fixed rather than preserved, which is the behavior change Phase 0 declined to make unilaterally. `observerInstance` and the `onUnmounted` disconnect are gone (VueUse disposes on scope teardown).
- [x] **Combobox search-autocomplete** — **the plan's premise for this item was wrong.** It assumed `Pages/Videos/Show.vue` had a page search duplicating AppLayout's fetch/debounce/keyboard-nav. It does not: `grep -rl "search-suggest" resources/js/` matches `AppLayout.vue` only, so step 2 had no second call site and was dropped. The duplication that *does* exist is inside AppLayout — the desktop header search and the mobile search overlay each carried their own copy of the suggestion-panel markup.
  1. [x] `Composables/useSearchSuggestions.js` written, returning `{ suggestions, flat, hasResults, isLoading, isOpen, search, clear, urlFor }`.
  2. [x] ~~Wire `Videos/Show.vue`~~ — dropped, no such call site exists.
  3. [x] Both AppLayout UIs converted to Reka `Combobox`, and the duplicated panel markup extracted to `Components/SearchSuggestionList.vue`. The hand-rolled `activeSuggestionIndex` modular arithmetic, `onSearchKeydown`, `isActiveSuggestion` and the `onClickOutside` call are all deleted.
  - Two behaviors were verified against Reka's source before relying on them: `ListboxRoot.onKeydownEnter` only calls `preventDefault()` when something is highlighted, so **Enter with nothing highlighted still falls through to the form submit** and the full search page; and opening the list calls `highlightSelected()`, not "highlight first", so nothing is auto-highlighted (no model value is ever set here).
  - `:reset-search-term-on-blur="false"` is deliberate — Reka's default is `true`, which would have wiped the header search box every time it lost focus.
  - **Pre-existing bug fixed in passing**: the mobile overlay called `debouncedFetchSuggestions(mobileSearchQuery.value)` inside a template, where the ref is already unwrapped, so it always passed `undefined` and mobile suggestions never worked at all.
- [x] **Tabs**: `Pages/Settings.vue` → `TabsRoot`/`TabsList`/`TabsTrigger`/`TabsContent`. Its `password` tab had *two* sibling `v-if` panels, which are now wrapped in a single `TabsContent` (two contents with the same value would both render). `Pages/Channel/Playlists.vue` keeps `switchTab()` as the `@update:model-value` handler against a one-way `:model-value="currentTab"`, exactly as the plan required — no second source of truth, so the URL query param cannot drift from the tab state.
- [x] **Toast**: `Components/ToastContainer.vue` swapped to `ToastProvider`/`ToastRoot`/`ToastViewport`. **`Composables/useToast.js` is byte-for-byte unchanged** — its module-level `toasts` array is still the single source of truth and its `setTimeout` is still the only auto-dismiss clock. Every toast passes `:duration="0"` because Reka's `startTimer` returns early on `duration <= 0`, so it never runs a competing timer. Gains an aria-live region, the F8 hotkey, swipe-to-dismiss and pause-on-hover.
  - **Regression to be aware of: the toast leave animation is gone.** Removal happens by the composable splicing its array, so the element unmounts outright instead of passing through `data-state="closed"`. Restoring it means moving the auto-dismiss clock into Reka — i.e. editing `useToast.js`, which the plan explicitly forbade. Say the word and it can be done as a deliberate follow-up.

---

## Phase 6 — Optional / low priority ✅ DECIDED

- [x] **Native `<select>` → Reka `Select`: skipped, per the item's own condition.** The plan gated this on "only if custom option rendering is actually wanted". Checked all three sites — `ReportModal.vue`, `Shorts/Index.vue` filters, `Videos/Create.vue` — and every option is plain text. A native `<select>` beats a custom listbox on mobile (it gets the OS picker) and needs no JS, so converting would be a net loss here. Revisit only if someone actually wants icons/thumbnails/multi-line options.
- [x] **`Switch` for Settings toggles** — done via a new `Components/UI/BaseSwitch.vue`. **Scope widened slightly, deliberately**: the plan named only the notification checkboxes, but the Privacy tab on the same page has three more identical ones, and converting one tab would have left two visibly different toggle styles in the same settings page. All six are switches now. Cosmetic only, as the plan said — the checkboxes were already accessible; the switch just reads as on/off rather than checked/unchecked.
- [x] **Net-new `Tooltip`: skipped, per the plan.** It called this out as "not a migration — separate feature request if wanted". No hand-rolled tooltip exists to replace; every `title="..."` relies on the native browser tooltip. Adding tooltips is a design decision, not a refactor.

---

## Phase 6 — Optional / low priority (do opportunistically, or skip)

- [ ] Native `<select>` → Reka `Select` in `ReportModal.vue`, `Shorts/Index.vue` filters, `Videos/Create.vue` (only if custom option rendering is actually wanted).
- [ ] `Switch` for `Settings.vue` notification checkboxes (cosmetic only, not an accessibility fix).
- [ ] Net-new `Tooltip` additions (not a migration — separate feature request if wanted; no hand-rolled tooltip exists today, all `title="..."` attributes rely on native browser tooltips).

---

## Risk Callouts (apply across all remaining phases)

- ~~**`AgeVerificationModal.vue` non-dismissability**~~ — verified against Reka's `DismissableLayer` source in Phase 3; see that section. The warning was well-placed: `AlertDialogContent` blocks outside-click on its own but **not** Escape, so `BaseDialog`'s explicit `escapeKeyDown` guard is load-bearing. Do not remove it. A hands-on confirmation is still outstanding.
- ~~**Singleton composable state must survive untouched**~~ — held. `useToast.js` was not edited at all; `ToastContainer.vue` renders `ToastRoot`s straight off the shared `toasts` array. `useTheme.js` and `useGlobalAutoTranslate.js` were never touched by any phase.
- ~~**`Videos/Show.vue` Save-to-Playlist dropdown**~~ — addressed in Phase 5, and the risk was slightly wider than written: `@select.prevent` is needed on the *playlist rows* too, not just the create input, because the panel is a multi-toggle checklist. Reka's typeahead turned out to be a non-issue (it skips `input`/`textarea` targets). Still wants the dedicated manual test the callout asks for.
- ~~**`Lightbox.vue`'s dual scroll-lock risk**~~ — resolved in Phase 4: the manual implementation is deleted and Reka's is the only scroll lock. Confirmed by grep that no `document.body.style` write remains in `resources/js/`. The repeated open/close check is still outstanding.
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
