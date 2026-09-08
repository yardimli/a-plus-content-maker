# Template image filters

Templates and projects store an independent ordered `image_filters` JSON array.
Creating a project copies the template stack. Editing either stack afterward does
not modify the other. Existing templates/projects start with an empty stack.

The shared catalog is `resources/data/image-filters.json`. It defines preset IDs,
names, slider labels, units, bounds, steps and default values. The 79 presets are
authored Instagram-inspired **color approximations**, including classic, story,
and newer preset names. They are not official Instagram processing recipes.
Names associated with grain, blur, distortion, or lens effects currently provide
color looks only; those spatial effects are not implemented.

Each stack entry is `{ "id": "aden", "values": { "contrast": 1.2 } }`.
Omitted values inherit that preset's defaults. At most three entries are accepted,
and each entry processes the previous entry's result. An empty array removes all
filters. Open Image filters beneath Assets in the editor sidebar to access the
dedicated setup page. Filters and sliders appear on the left, with live image
previews on the right (stacked on narrow screens). The simplified asset chooser
supports thumbnail search and up to three selections, applied together; Cancel
keeps the existing preview selection. The page exposes save, reset, removal and
reordering controls.

An image field named `image` has an adjacent `image_apply_filters` boolean in its
content object. Missing means true for compatibility. Repeater rows carry their
own flags, so reordering and template cloning preserve opt-outs independently of
asset identity. New/replacement images use their slot's existing setting.

Editor and gallery previews use CSS filter functions. The transfer guide loads
actual rendered images from its authorized download endpoint with `preview=1`.
Downloads use the same generated PNG bytes, preserve dimensions and alpha, and
never overwrite source assets. The endpoint validates the project, module and
image slot before consulting that slot's opt-out. Original downloads retain their
existing format handling when no filters apply. The JSON export includes the
stack and per-slot flags for consumers of the manifest.

The PHP renderer follows the sRGB matrices and operation order in the
[CSS Filter Effects specification](https://www.w3.org/TR/filter-effects-1/).
Browser color management and intermediate rounding can produce small differences
between CSS editor previews and the PNG; the transfer preview and download match
exactly. Generated PNGs are cached privately under `storage/app/filtered-images`
by source bytes, resolved operations and renderer version. Bump the renderer cache version when
changing its output. Preset values are included in the resolved operations. This cache can be cleared and regenerated.

Deployment: run `php artisan migrate` and `npm run build`. PHP GD is required
(already used by the existing crop/download pipeline).

Verification: `php artisan test --filter=ImageFiltersTest` covers ordered pixel
processing, transparency, validation, access control, cloning, opt-outs, and
preview/download parity.
