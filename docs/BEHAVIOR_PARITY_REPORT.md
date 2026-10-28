# Dynamic behavior parity — report

**PR branch:** `cursor/dynamic-behavior-parity-0b67` (separate from Phase 3 content PR).

## Inventory

See **[DYNAMIC_BEHAVIOR_INVENTORY.md](./DYNAMIC_BEHAVIOR_INVENTORY.md)** — hero video, sticky header, hover/focus, drawer, plugins, embeds.

## Already matched (before this PR)

- Sticky header + scroll threshold (50px) and `.header--scrolled`
- Nav/donate/search hover colors and `transition: color/background` (Drupal CSS aligned to WXR tokens)
- Mobile drawer toggle + Escape (`main.js`)
- Lazy-load cleanup in migrated HTML

## Implemented in this PR

| Item | Change |
| --- | --- |
| **Hero video** | Issue fields `field_hero_video`, `field_hero_poster`; front page renders `.hero__cover-video` |
| **Behavior harness** | `parity/tests/behavior-parity.spec.ts` + `npm run parity:behavior` |
| **Acceptance** | [ACCEPTANCE_CRITERIA.md](./ACCEPTANCE_CRITERIA.md) — Dynamic behavior section |
| **Inventory tooling** | `migration-data/mine_wxr_behavior.py` |

## Still needs work

- Editors must **upload** `Impact_Ambient.mp4` (+ poster) on the current issue (or run media migrate for `/wp-content/uploads/2026/09/Impact_Ambient.mp4`).
- Mega Menu flyout animation vs simple CSS dropdown.
- PostX scroll animations (not ported).
- SearchStax interaction parity.
- Behavior baseline JSON in frozen baseline zip (future).

## Owner Mac — behavior capture

```bash
cd parity
npm ci && npx playwright install chromium
export PARITY_BEHAVIOR_HIT_WP=1
npm run parity:behavior:smoke
```

Optional strict video assertion: `PARITY_BEHAVIOR_STRICT=1`. Optional Playwright video: `PARITY_BEHAVIOR_VIDEO=1`.

After merge, on DDEV: `drush cim -y && drush cr`, then attach hero video on latest **Issue** node.
