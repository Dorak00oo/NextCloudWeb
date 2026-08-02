# Design: Gallery Wave 1 — Desktop organization parity

**Date:** 2026-08-01  
**Status:** Approved for implementation  
**Parent:** `2026-07-31-private-drive-web-design.md`

## Goal

Bring APK organization features to web Gallery with PC-adapted UI. Shared source of truth: `/.gallery/albums-meta.json`.

## Wave 1 scope

- Create album (APK flow: Files tree picker → Create album here → name → pin + return) / create group  
- Hide / show hidden (dimmed when shown)  
- Merge same-name toggle  
- Change cover (pick from album media)  
- Custom sort + drag reorder  
- Add to group (menu; drag onto group when Custom sort)  
- Toolbar for PC actions  
- Grid density: **6, 8, 10, 12** columns (step ±2)

## Out of scope (later waves)

- Media multi-select / move / delete / share  
- Group collage covers, keyboard polish  

## UI (desktop)

- Full-bleed content area (existing)  
- Top toolbar: back, title, sort, density, overflow actions  
- Wider grids (6–12)  

## Backend

Extend `apps/gallery` API as needed for create album/group, hide, cover, reorder; keep metadata shape compatible with APK.
