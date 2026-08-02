# Design: Private Drive Web (Files + Gallery + Settings)

**Date:** 2026-07-31  
**Status:** Draft for user review  
**Product goal:** Personal private-drive web UI, aligned in spirit with the reworked Android app (`Dorak00oo/NextCloudApk`), independent of Nextcloud upstream updates.

---

## 1. Goals

- Web experience focused only on uploading/managing **files** (photos/videos/docs) and a future **gallery**.
- Global navigation limited to three sections: **Files**, **Gallery**, **Settings**.
- Keep **login**, **multi-account**, and **security** (auth, sessions, permissions, admin user management).
- **Hard detach** from Nextcloud upstream: own git remote, no automatic upstream updates; owner maintains the fork.
- Preserve backend contracts used by the Android app (WebDAV / OCS / login) so the APK keeps working.

## 2. Non-goals (Phase 1)

- Full custom Gallery UI matching the APK (Phase 2).
- Full rewrite of the PHP backend / new auth stack.
- Role-based UI differences (everyone sees the same three sections for now).
- Removing AGPL obligations (forking does **not** remove the license; see §8).

## 3. Product structure

### 3.1 Global navbar (top-level)

| Entry | Behavior |
|-------|----------|
| **Files** | Default landing after login. File tree + internal extras. |
| **Gallery** | Samsung-style albums (APK-aligned): folder albums, favorites, groups/metadata via `/.gallery/albums-meta.json`. |
| **Settings** | Existing Settings app (personal + admin). Security, users, theming, etc. |

No Dashboard. No weather/status/comments/app-store chrome in the main nav.

### 3.2 Inside Files (sidebar / extras)

Keep as **internal** Files views (not top-level apps):

- Folder tree (primary)
- Favorites
- Recent
- Trashbin
- Sharing (share with users / public links) as today inside Files
- Other Files-native extras that support a drive (e.g. personal files) if already wired

### 3.3 Removed / disabled from the product surface

Disable and stop shipping in the default enabled set (and remove from navigation registration where they still appear):

- `dashboard`
- `weather_status`
- `user_status`
- `comments` (UI surface; keep only if a hard dependency appears—prefer disable)
- `appstore` (no browsing/installing random apps in this product)
- Other non-drive UI apps not required for Files/Settings/auth/DAV

**Keep enabled** (backend / drive / security):

- `files`, `files_trashbin`, `files_sharing`, `files_versions` (versions stay available as file feature; not a top nav item)
- `dav`, `federatedfilesharing` / federation pieces only if required by sharing stack—trim later if unused
- `settings`, `theming`, `twofactor_backupcodes`, `oauth2`, `provisioning_api`
- Core auth and user management

Exact disable list will be finalized in the implementation plan against `occ app:list` dependency checks.

## 4. Technical approach (core fork)

### 4.1 Strategy

**Soft-fork of this server tree → hard ownership:**

1. Change git `origin` to the owner’s private/public repo (not `nextcloud/server`).
2. Stop merging upstream Nextcloud by default.
3. Encode product defaults in **source + config**, so every deploy of this fork behaves as a private drive.

### 4.2 Concrete change areas (Phase 1)

1. **Default app / landing**  
   - System default: `files` only (remove `dashboard` from `defaultapp` fallback in `NavigationManager` / config sample / install defaults).

2. **Navigation allowlist**  
   - Central filter (e.g. in `NavigationManager::getAll` or equivalent) so only entries for `files`, `gallery` (new stub app or core route), and `settings` appear as top-level links.  
   - Files internal views remain managed by `@nextcloud/files` navigation inside the Files app.

3. **Disable unwanted apps**  
   - Ship defaults so Dashboard and other UI apps are disabled on install / documented `occ app:disable` set for existing instances.  
   - Prefer disable over deleting directories in Phase 1 (easier rollback); deletion of unused app trees can be a later cleanup PR.

4. **Gallery stub**  
   - Minimal app or core page registering one navigation entry `gallery` with empty/placeholder UI.  
   - No media pipeline yet; Phase 2 replaces the stub using APK UX as reference.

5. **Branding (light)**  
   - Optional: product name via theming/config (`productname`) so the UI doesn’t present as a full Nextcloud suite. Deep rebrand can wait.

### 4.3 What must not break

- Login / logout / session / brute-force protections  
- User provisioning and admin settings  
- WebDAV and Files API used by `NextCloudApk`  
- Trashbin and sharing endpoints  

## 5. Git ownership / “leave Nextcloud upstream”

### 5.1 Steps (human-owned)

1. Create a new GitHub repo under the owner’s account (e.g. `Dorak00oo/private-drive-server` or similar).  
2. Point this working tree’s `origin` at that repo.  
3. Push the fork.  
4. Do **not** configure automatic sync from `nextcloud/server`.  
5. Optionally keep `upstream` remote read-only for cherry-picks of security fixes—manual only.

### 5.2 Local server / Docker image

- UI/backend code changes live in this repo; redeploy/rebuild the container when shipping.  
- User data (`data/`), DB, and passwords are **not** replaced by a UI fork—they stay on the local server.  
- The Android app continues to talk to the same server URL/APIs.

## 6. Mobile APK in the workspace

- Repo: https://github.com/Dorak00oo/NextCloudApk  
- Intended path: `/var/www/html/mobile-apk` (or sibling folder).  
- **Blocker:** the repo is private (GitHub returns 404 without credentials). Clone requires one of:
  - `gh auth login` / PAT with `repo` scope, or  
  - SSH key added to GitHub, or  
  - Copy the project from the Windows host into the mounted workspace.

Phase 2 Gallery will use the APK as UX/API reference once the code is present.

## 7. Phased delivery

### Phase 1 (approved — next)

- Fork ownership docs + remote change (owner).  
- Landing = Files.  
- Top nav = Files | Gallery (stub) | Settings.  
- Disable/remove suite apps from product surface.  
- Keep sharing, trash, security, multi-user.

### Phase 2 (later)

- Custom Gallery (APK-aligned).  
- Optional deeper rebrand / further app tree deletion.  
- Optional admin-only future features.

## 8. License and compliance note

This codebase is **AGPL-3.0-or-later** (and related SPDX headers). Moving to a private git remote and stopping upstream updates does **not** waive AGPL duties (source offer to users who interact with the software over a network, etc.). The owner must keep compliance when distributing or hosting the fork. AI-assisted commits must follow the repo’s AI Contribution Policy when contributing back anywhere that requires it; for a private fork, still keep `Assisted-by` trailers if the owner wants that audit trail.

## 9. Success criteria (Phase 1)

- After login, user lands on Files tree.  
- Top-level nav shows only Files, Gallery, Settings.  
- Dashboard and unrelated suite apps are not reachable as first-class apps.  
- Favorites / recent / trash / share still work inside Files.  
- Existing users can still log in; admin can manage users in Settings.  
- Android app against this server still authenticates and syncs files.  
- Git `origin` is the owner’s repo (not `nextcloud/server`).

## 10. Open items for implementation plan

- Final `occ app:disable` list after dependency dry-run.  
- Gallery stub: new minimal app vs core route.  
- Whether to hide federation UI while leaving backend packages installed.  
- Auth path for cloning `NextCloudApk` into the workspace.
