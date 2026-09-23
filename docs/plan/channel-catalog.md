# Channel catalog deepen

Ordered tickets to finish the supply-channel product editor, variants, and productivity toolkit.

Live status is generated in [`../status/02-channel-dashboard.md`](../status/02-channel-dashboard.md). Contracts live in [`../api/catalog/04-channel-catalog.php`](../api/catalog/04-channel-catalog.php).

---

## SC-CAT-1 — Editor completeness ✅ 2026-09-23

**Module:** Catalog (+ Pricing reader contract)  
**Depends on:** EP-SC-014A/015/016 already live  
**Acceptance:**
- `GET /channel/products/{id}` returns `pricing` (or null), `axes`, `variants`, and synced `availability.retailer_group_ids`
- `POST/PUT` syncs `retailer_group_ids` onto `product_retailer_groups`
- Non-empty barcode unique within the channel (product or variant) → 422

---

## SC-CAT-2 — Variant lifecycle + price override ✅ 2026-09-23

**Module:** Catalog, Pricing (`quoteLine` + `CatalogProductLookup::variantPriceOverride`)  
**Tables:** `product_variants.price_override`  
**Permissions:** `sc.catalog.variants`  
**Acceptance:**
- EP-SC-017A `PUT …/variants/{variantId}` edits sku/barcode/image/status/`price_override`
- EP-SC-017B `DELETE …/variants/{variantId}` removes one combination
- `null` override inherits product quote; set override is unit base before lists
- Cart/`quote` passes `variant_id` into `quoteLine`

---

## SC-CAT-3 — Productivity toolkit ✅ 2026-09-23

**Module:** Catalog  
**Permissions:** `sc.catalog.create` / `sc.catalog.update` / `sc.catalog.import` / `sc.catalog.view`  
**Acceptance:**
- EP-SC-016A duplicate product (children + pricing; no variants)
- EP-SC-018 bulk: `set_category` / `set_brand` / `set_zones` / `set_status` (+ existing activate/disable/delete_draft)
- EP-SC-019A import template JSON
- EP-SC-019/020 shared columns: `sku,name_ar,name_en,barcode,brand_id,category_id,status,base_price,currency_id`
