# Custom Design Tool

The custom apparel designer lives in `custom-design.php` (PHP + vanilla JS, no
frameworks). This document covers the **Version B** upgrade: a polished 2D /
faux-3D live preview, data-driven apparel types (incl. Couple & Corporate wear),
animal stamps, and server-side upload validation.

> **Everything here is free and offline.** There are **no external APIs, no API
> keys, and no paid services** in the design tool. It runs entirely in the
> browser (Canvas + CSS) and your own PHP/MySQL backend. (The separate AI
> virtual try-on feature is the only thing that needs an external API — the
> design tool does not.)

---

## Files

| File | Purpose |
|------|---------|
| `custom-design.php` | The whole tool: UI, canvas drawing, preview, stamps, submit. |
| `includes/apparel-config.php` | **Single source of truth** for apparel types (client + server). |
| `includes/custom-design-ajax.php` | Save designs, list/get designs, and validate uploads. |
| `uploads/designs/` | Submitted design composites (auto-created). |
| `uploads/design_assets/` | Sanitized user-uploaded logos/artwork (auto-created). |

---

## Configuring apparel types

All apparel types are defined in **`includes/apparel-config.php`** via
`getApparelConfig()`. The same array is:

1. Injected into the page as JSON (`APPAREL_CONFIG`) to build the tabs, mockups,
   design areas, logo guides, and pricing.
2. Used server-side to validate the submitted `product_type`.

### Add a new type (no JS changes needed)

Add an entry to the array. Example — a "Corporate T-Shirt":

```php
'corp_tshirt' => [
    'label'    => 'Corporate T-Shirt',
    'icon'     => 'fa-user-tie',          // Font Awesome icon
    'group'    => 'corporate',            // basic | couple | corporate (which tab)
    'base'     => 900,                    // base price in ₱ (couple values are per set)
    'shape'    => 'tshirt',               // which procedural SVG mockup to draw
    'area'     => ['x'=>130,'y'=>120,'w'=>140,'h'=>180], // print area (400x500 space)
    'couple'   => false,                  // true => Partner A / Partner B side-by-side
    'logoZone' => ['x'=>150,'y'=>150,'w'=>72,'h'=>72],   // dashed logo guide, or null
],
```

The **only** field tied to code is `shape`. It must be one of the procedural
mockups defined in `custom-design.php` (`mockups` / `mockupsBack` objects):

- `tshirt`, `hoodie`, `polo` — basics
- `corp_polo` — polo with a chest pocket
- `longsleeve` — long-sleeve shirt
- `vest` — sleeveless vest

Everything else (label, group/tab, price, design area, couple mode, logo zone)
is pure data and can be changed freely. To add a genuinely new silhouette, add a
new key to both `mockups` and `mockupsBack` (front/back SVG markup) and point
your config entry's `shape` at it.

> Coordinate system for `area` and `logoZone` is the **400×500** mockup canvas
> space, matching the SVG `viewBox`.

---

## Features

### Live preview (2D / faux-3D)
- The preview composites the apparel color + your front/back drawing onto a flat
  garment mockup in real time.
- **Flip** rotates the card 180° (CSS) to show the back; **Spin** auto-rotates;
  drag to rotate manually. No WebGL, no 3D models.

### Couple Wear
- Couple types (`couple: true`) edit **Partner A** and **Partner B** with a
  toggle in the canvas toolbar. Each partner keeps its own front/back design.
- The preview shows **both garments side by side**, and submitted images are an
  800×500 side-by-side composite of both partners.

### Corporate Wear
- Corporate types show a dashed **logo placement guide** on the front of the
  canvas (configured per type via `logoZone`).

### Animal Stamps
- Ten bundled inline-SVG stamps (Lion, Tiger, Fox, Wolf, Eagle, Bear, Butterfly,
  Dragon, Unicorn, Shark) — no external image service.
- Pick a size with the slider, click a stamp to drop it (in the current brush
  color), drag to reposition, select and press × to delete. Stamps count toward
  the "Elements" total. They're clean stylized silhouettes, not photoreal art.

### Pricing
- Base prices come from the config. Print-size and per-color costs work as
  before. Couple sets ₱1,500; Corporate Polo ₱950; Long Sleeve ₱1,050;
  Vest ₱1,100.

---

## Uploads are validated server-side

User image uploads (logos/artwork) are **never trusted from the browser**. The
client does a quick size/type check for UX, but the real validation happens in
`includes/custom-design-ajax.php` → `handleUploadAsset()`:

- Enforces a **5 MB** size limit and sane pixel dimensions.
- Detects the **real MIME type from the file bytes** (`finfo`), allowing only
  PNG / JPEG / WEBP.
- **Re-encodes the image through GD**, which strips EXIF/metadata and neutralizes
  any embedded payload.
- Saves with a **randomized filename** to `uploads/design_assets/`.
- Returns a sanitized same-origin URL, which the client draws onto the canvas.

> Requires the PHP **GD** extension (bundled with XAMPP). If GD is unavailable the
> endpoint falls back to a validated, randomized raw copy.

---

## Chatbot

The site chatbot is **not** built by this tool. A commented mount point is left
near the bottom of `custom-design.php` (search for `CHATBOT MOUNT POINT`).
