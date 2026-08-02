# Design: Gallery Wave 2–3 — Media actions + polish

**Date:** 2026-08-01  
**Status:** Approved for implementation  
**Parent:** `2026-08-01-gallery-wave1-desktop-design.md`

## Goal

APK-aligned media operations inside album detail, plus group collage covers and desktop keyboard polish. No Share on web (mobile-only).

## Viewer (photos / videos)

**Wave 2.** Upstream `viewer` is listed in `shipped.json` but not present in this fork checkout. Gallery ships a lightweight in-app lightbox:

- Photos: full-size / preview image  
- Videos: HTML5 `<video controls>` via WebDAV/download URL  
- Prev/next within current album; Esc closes  
- If `OCA.Viewer` becomes available later, prefer it; lightbox remains fallback

## Wave 2 — Media multi-select & actions

- Multi-select: Ctrl/Cmd+click toggle; right-click toggle; toolbar “Select” optional  
- Normal click opens Viewer (unless selecting / pick-cover)  
- Selection bar: **Move** · **Favorite / Unfavorite** · **Delete** · Clear  
- **Share:** out of scope (cell exclusive)  
- **Move:** picker of other folder-albums only (exclude Favorites, groups, current, merged) → server-side move into destination folder  
- **Delete:** confirm dialog on PC, then unlink files  
- **Favorite:** NC file favorite tag; ★ badge on tiles; Unfavorite from Favorites album removes from that album view  
- API under `apps/gallery` (`moveMedia`, `deleteMedia`, `setFavorite`)

## Wave 3 — Polish

- Group collage covers: up to 4 member `coverUrl`s (2 side-by-side / 2×2), APK-like  
- Keyboard: `Esc` clears selection / exits cover pick / goes back; `Ctrl+A` select all media in album; `Delete` deletes selection (with confirm)

## Out of scope

- Share / public links from Gallery  
- Copy (APK album detail is move-only)  
- Offline download  
- Custom viewer rewrite (use stock Viewer)
