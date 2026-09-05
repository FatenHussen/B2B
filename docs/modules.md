# The 22 modules

| # | Module | Layer | In L1 | Responsibility | Key tables |
|---|---|---|---|---|---|
| 1 | **Core** | Foundation | Yes | Unified response envelope, error handler, idempotency, audit log, base classes, integer money | `audit_logs, idempotency_keys, approval_requests` |
| 2 | **Identity** | Foundation | Yes | The four guards, separate user tables, OTP, sessions, devices, session revocation | `platform_users, channel_users, warehouse_users, retailers, reps, otp_codes, devices, sessions` |
| 3 | **Access** | Foundation | Yes | The 170-permission catalog, roles, segregation of duties, temporary grants, periodic review, permission simulator | `permissions, roles, role_permission, sod_rules, temp_grants, access_reviews` |
| 4 | **Reference** | Foundation | Yes | Governorates, zones, activity types, root categories, sale units, equipment, currencies, FX rates | `governorates, zones, activity_types, root_categories, sale_units, equipments, currencies, fx_rates` |
| 5 | **Tenancy** | Foundation | Yes | Supply channels, provisioning, coverage, limits, plan binding, strict row-level isolation | `supply_channels, channel_zone, channel_limits, channel_features, provisioning_jobs` |
| 6 | **Integration** | Foundation | Yes | Provider adapters behind contracts: WhatsApp, SMS, maps, storage, notifications, error tracking | `integration_settings, integration_health` |
| 7 | **Catalog** | Domain | No | Brands, five-level category tree, products, variants, media, import | `brands, categories, products, product_variants, product_media, product_zones, import_batches` |
| 8 | **Pricing** | Domain | No | Pricing engine with five-rule precedence, tiers, zone / group / retailer prices, change log, price freeze | `price_lists, price_rules, qty_tiers, zone_prices, group_prices, retailer_prices, price_change_log` |
| 9 | **Promotion** | Domain | No | Six offer types, targeting, time and quantity limits, cart evaluation, indivisibility | `offers, offer_rules, offer_targets, offer_rewards, offer_usages` |
| 10 | **Inventory** | Domain | No | Warehouses, four balance states, movements, reservation, lots and expiry, stocktakes | `warehouses, stock_levels, stock_movements, reservations, lots, stocktakes` |
| 11 | **Loyalty** | Domain | No | Point rules, tiers, rewards, redemption | `loyalty_rules, loyalty_points, loyalty_tiers, rewards, redemptions` |
| 12 | **Content** | Domain | No | Intro, banners, sliders and algorithms, legal pages, app versions | `intros, banners, sliders, slider_items, legal_pages, app_versions` |
| 13 | **Notification** | Domain | No | Templates, targeting, campaigns, scheduling, delivery log, three channels | `notification_templates, notifications, notification_targets, delivery_log` |
| 14 | **Support** | Domain | No | Tickets, unified search, the 360 card, session impersonation | `tickets, ticket_events, impersonation_grants` |
| 15 | **PlatformBilling** | Domain | No | Platform plans, channel subscriptions, platform invoices, dunning | `plans, subscriptions, platform_invoices, dunning_events` |
| 16 | **Ordering** | Coordination | No | Cart, order, multi-channel split, sub-orders, state machine, assignment, scheduling | `carts, cart_lines, orders, sub_orders, order_lines, order_events, assignments, scheduled_orders` |
| 17 | **Fulfillment** | Coordination | No | Picking lists, packing, handover with dual confirmation, rep return, inbound receipt | `picking_lists, picking_lines, packages, handovers, handover_confirmations, inbound_receipts` |
| 18 | **Delivery** | Coordination | No | Rep-to-retailer delivery, line matching, postponement, tracking, location pings | `deliveries, delivery_lines, delivery_events, rep_locations, rep_shifts` |
| 19 | **Returns** | Coordination | No | Return and exchange requests from both apps, channel decision, warehouse sorting | `return_requests, return_lines, return_decisions` |
| 20 | **Finance** | Coordination | No | Invoices, payments with unique receipt, receivables, rep wallets, settlements, statements, ageing | `invoices, invoice_lines, credit_notes, payments, receipts, receivables, rep_wallets, wallet_transactions, settlements` |
| 21 | **Sync** | Coordination | No | Outbox intake, duplicate prevention, differential sync, conflict resolution, sync state | `sync_cursors, sync_operations, sync_conflicts` |
| 22 | **Reporting** | Coordination | No | Daily snapshots, dashboards, reports, background export | `daily_snapshots, report_jobs, report_exports` |
