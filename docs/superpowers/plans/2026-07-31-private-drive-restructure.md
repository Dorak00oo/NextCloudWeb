# Private Drive Web Restructure Implementation Plan

> **For agentic workers:** Execute inline in this session. Steps use checkbox syntax for tracking.

**Goal:** Turn this Nextcloud fork into a private-drive web UI with Files + Samsung-style Gallery (APK-aligned) + Settings, keeping auth/security/APIs.

**Architecture:** Core nav allowlist + shipped defaults; new `gallery` app that derives albums from folders with media and syncs organization via `/.gallery/albums-meta.json` (same contract as the Android APK).

**Tech Stack:** PHP AppFramework, Vue 3, WebDAV/Files search, existing Preview API.

## Global Constraints

- Top nav only: `files`, `gallery`, `settings` (link type).
- Landing: `files`.
- Keep trashbin, sharing, versions inside Files.
- Preserve WebDAV/OCS for mobile APK.
- AGPL SPDX headers on new files.

---

### Task 1: Trim suite surface

**Files:**
- Modify: `lib/private/NavigationManager.php`
- Modify: `core/src/components/AppMenu.vue`
- Modify: `core/shipped.json`
- Modify: `apps/appstore/appinfo/info.xml`
- Modify: `apps/settings/lib/Listener/LoadAdditionalEntriesListener.php`
- Modify: `config/config.php` (defaultapp)

- [x] Allowlist top-level link nav to files/gallery/settings
- [x] Default entry fallbacks → `files`
- [x] Remove AppMenu app-store/more-apps tiles (source + patched `dist/core-main.js`)
- [x] Remove appstore navigations; drop appstore from alwaysEnabled
- [x] Add Settings as type=link entry
- [x] Disable unwanted apps via occ; set defaultapp

### Task 2: Gallery app (APK albums)

**Files:**
- Create: `apps/gallery/**`
- Modify: `build/frontend/vite.config.ts` (gallery entry)
- Build: `dist/gallery-main.mjs` (+ css)

- [x] Scaffold app (info.xml, Application, PageController, routes)
- [x] AlbumService: derive albums, favorites, metadata JSON
- [x] ApiController: list albums, album media, get/put metadata
- [x] Vue UI: albums grid → album detail (Samsung-style)
- [x] Enable app + verify albums (Photos/, Camera/)
- [x] Wave 2: media multi-select, move, favorite, delete, in-app photo/video lightbox (no Share)
- [x] Wave 3: group collage covers, keyboard polish (Esc / Ctrl+A / Del / arrows)

### Task 3: Verify

- [x] `occ app:list` shows gallery enabled; dashboard etc disabled
- [x] defaultapp = files
- [x] Gallery lists albums; metadata path `/.gallery/albums-meta.json`
