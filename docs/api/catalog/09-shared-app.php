<?php

declare(strict_types=1);

return [
    ep('EP-CM-004', 'SP-01', 'GET', '/app/session', 'app', null, [
        'name' => 'App session bootstrap',
        'name_ar' => 'جلسة الإقلاع',
        'r' => [
            'user' => [
                'id' => 481,
                'name' => 'أبو خالد',
                'user_type' => 'retailer',
                'profile_completed' => true,
                'avatar' => null,
            ],
            'permissions' => ['rt.receive.confirm', 'rt.payment.record'],
            'feature_flags' => ['offline_orders' => true, 'loyalty' => true],
            'sync_cursor' => 'c_9f2a71',
            'server_time' => '2026-03-01T09:12:44+03:00',
            'requires_legal_accept' => false,
            'legal' => ['privacy_version' => '2026-03', 'terms_version' => '2026-01'],
        ],
        'd' => 'Called on every launch (REQ-CM-004). Distinguishes new vs returning users (TB-RT-011). requires_legal_accept blocks usage until consent (DOC-12E TB-AD-081). user.avatar is always null until media upload. Reps also receive commercial_limits and duty {on_duty, tracking_enabled}.',
    ]),
    ep('EP-CM-005', 'SP-01', 'POST', '/app/auth/logout', 'app', null, [
        'name' => 'App logout',
        'name_ar' => 'خروج التطبيق',
        'b' => new stdClass(),
        'r' => ['success' => true],
        'd' => 'Client must wipe local data (REQ-CM-056).',
    ]),

    ep('EP-APP-030', 'SP-07', 'POST', '/app/pricing/quote', 'app', null, [
        'name' => 'Server price quote',
        'name_ar' => 'تسعير الخادم',
        'b' => [
            'lines' => [['product_id' => 880, 'variant_id' => 1, 'qty' => 6]],
            'zone_id' => 12,
        ],
        'r' => [
            'lines' => [[
                'product_id' => 880,
                'unit_price' => 11500,
                'applied_rule' => ['type' => 'qty_tier', 'id' => 3, 'label' => 'شريحة 5–9'],
                'tier' => ['from' => 5, 'to' => 9],
                'discount' => 0,
                'line_total' => 69000,
            ]],
            'subtotal' => 69000,
            'currency' => 'SYP',
        ],
        'd' => 'Single source of truth (REQ-IN-07). Precedence: retailer_price ← group_list ← zone_list ← qty_tier ← base_price. Clients must not send prices.',
        'in' => 'REQ-IN-07',
    ]),

    ep('EP-APP-040', 'SP-08', 'GET', '/app/offers', 'app', null, [
        'name' => 'Browse offers',
        'name_ar' => 'العروض',
        'q' => listQuery(['filter[activity_type_id]' => '3', 'filter[zone_id]' => '12']),
        'r' => [[
            'id' => 44,
            'image' => 'https://cdn.example/o/44.jpg',
            'name' => 'اشترِ 10 واحصل على 1',
            'company' => null,
            'rating' => 4.7,
            'components' => [['product_id' => 880, 'qty' => 10]],
            'price_before' => 120000,
            'discount' => 12000,
            'price_after' => 108000,
            'ends_at' => '2026-03-31T23:59:59+03:00',
            'days_left' => 30,
            'remaining_qty' => 320,
            'sold_count' => 180,
        ]],
        'd' => 'company (channel identity) stays hidden until an order is confirmed (REQ-IN-06).',
    ]),
    ep('EP-APP-041', 'SP-08', 'GET', '/app/offers/{id}', 'app', null, [
        'name' => 'Offer detail',
        'name_ar' => 'تفاصيل عرض',
        'r' => [
            'id' => 44,
            'images' => [],
            'long_description' => 'على زيت دوار الشمس',
            'icons' => ['gift'],
            'same_company_offers' => [],
            'same_company_products' => [],
        ],
        'd' => 'Re-validate via quote before submit (TB-RT-043).',
    ]),

    ep('EP-CM-050', 'SP-13', 'POST', '/app/receipts/reserve', 'app', 'rp.payment.collect', [
        'name' => 'Reserve receipt number',
        'name_ar' => 'حجز رقم وصل',
        'b' => new stdClass(),
        'r' => ['receipt_no' => 'RCPT-10041'],
        'd' => 'Server-generated unique number BEFORE payment starts (REQ-IN-01). Used by EP-RP-060 then EP-RT-050.',
        'in' => 'REQ-IN-01',
        'e' => [409 => 'duplicate_receipt_no'],
    ]),

    ep('EP-SY-001', 'SP-14', 'GET', '/app/sync/pull', 'app', null, [
        'name' => 'Sync pull',
        'name_ar' => 'سحب المزامنة',
        'q' => [
            'cursor' => 'c_9f2a71',
            'scopes[]' => 'catalog',
            'limit' => '200',
        ],
        'r' => [
            'changes' => [
                'catalog' => ['upserts' => [], 'deletes' => []],
            ],
            'next_cursor' => 'c_9f3b80',
            'has_more' => false,
            'full_resync_required' => false,
        ],
        'd' => 'Retailer scopes: catalog,pricing,offers,zones,orders,refs. Rep extra: rep_catalog,customers,zones,assignments,deliveries,wallet. If cursor is too old, full_resync_required=true.',
    ]),
    ep('EP-SY-002', 'SP-14', 'POST', '/app/sync/push', 'app', null, [
        'name' => 'Sync push',
        'name_ar' => 'دفع المزامنة',
        'b' => [
            'operations' => [[
                'client_op_id' => 'op_order_local_88',
                'type' => 'cart.submit',
                'payload' => ['sections' => [['ref' => 'ch_a1']], 'offline_created' => true],
                'created_at' => '2026-03-01T09:05:00+03:00',
            ]],
        ],
        'r' => [
            'results' => [[
                'client_op_id' => 'op_order_local_88',
                'status' => 'applied',
                'server_id' => 700,
                'error' => null,
            ]],
        ],
        'd' => 'Max 500 ops per batch, 20/min per device. status: applied|duplicate|conflict|failed. Device never pushes catalog/prices/offers/stock (REQ-CM-017). Allowed: order, payment, delivery, receipt, shortage, favorite, rating, add-shop.',
        'e' => [429 => 'rate_limited'],
    ]),
    ep('EP-SY-003', 'SP-14', 'GET', '/app/sync/status', 'app', null, [
        'name' => 'Sync status',
        'name_ar' => 'حالة المزامنة',
        'r' => [
            'pending_server_side' => 0,
            'last_pull_at' => '2026-03-01T09:12:00+03:00',
            'last_push_at' => '2026-03-01T09:11:40+03:00',
            'conflicts' => [],
        ],
    ]),
    ep('EP-SY-004', 'SP-14', 'POST', '/app/sync/resolve-conflict', 'app', null, [
        'name' => 'Resolve sync conflict',
        'name_ar' => 'حل تعارض',
        'b' => ['conflict_id' => 'cf_12', 'resolution' => 'server_wins'],
        'r' => ['success' => true],
        'd' => 'Client must not drop an op from the outbox until applied or duplicate (REQ-CM-019).',
    ]),

    ep('EP-CM-060', 'SP-14', 'GET', '/app/notifications', 'app', null, [
        'name' => 'Notifications inbox',
        'name_ar' => 'الإشعارات',
        'q' => listQuery(['filter[read]' => '0']),
        'r' => [[
            'id' => 91,
            'icon' => 'order',
            'title' => 'تم تأكيد طلبك',
            'body' => 'الطلب SO-9001 قيد التجهيز',
            'at' => '2026-03-01T09:20:00+03:00',
            'read_at' => null,
            'action' => ['type' => 'order', 'target' => 9001],
        ]],
        'd' => 'meta.unread_count is included in the envelope.',
    ]),
    ep('EP-CM-061', 'SP-14', 'POST', '/app/notifications/read-all', 'app', null, [
        'name' => 'Mark all read',
        'name_ar' => 'تعليم الكل كمقروء',
        'b' => new stdClass(),
        'r' => ['success' => true],
    ]),
    ep('EP-CM-062', 'SP-14', 'DELETE', '/app/notifications', 'app', null, [
        'name' => 'Clear inbox view',
        'name_ar' => 'مسح عرض الإشعارات',
        'r' => ['success' => true],
        'd' => 'Clears the inbox view, not the server log.',
    ]),
    ep('EP-CM-063', 'SP-14', 'POST', '/app/devices/push-token', 'app', null, [
        'name' => 'Register push token',
        'name_ar' => 'رمز الإشعارات',
        'b' => ['token' => 'fcm:xxxxx', 'platform' => 'android'],
        'r' => ['success' => true],
    ]),

    ep('EP-APP-100', 'SP-15', 'GET', '/app/content/home-blocks', 'app', null, [
        'name' => 'Home content blocks',
        'name_ar' => 'بلوكات الرئيسية',
        'r' => [
            'banners' => [['id' => 9, 'image' => 'https://cdn.example/b/9.jpg', 'link' => ['type' => 'offer', 'target' => 44]]],
            'sliders' => [[
                'key' => 'best_selling',
                'title' => 'الأكثر مبيعاً',
                'items' => [],
                'show_all' => true,
            ]],
        ],
        'd' => 'Filtered by the user activity type and zone, including intro targeting (TB-RT-010).',
    ]),
    ep('EP-APP-110', 'SP-15', 'GET', '/app/loyalty', 'app', null, [
        'name' => 'Loyalty wallet',
        'name_ar' => 'النقاط',
        'r' => [
            'points' => 1240,
            'tier' => 'silver',
            'next_tier' => ['name' => 'gold', 'remaining' => 3760],
            'history' => [['at' => '2026-02-20', 'delta' => 40, 'reason' => 'invoice_paid']],
            'rewards' => [['id' => 3, 'name' => 'كرتون زيت', 'points_cost' => 2000]],
        ],
    ]),
    ep('EP-APP-111', 'SP-15', 'POST', '/app/loyalty/redeem', 'app', null, [
        'name' => 'Redeem reward',
        'name_ar' => 'استبدال نقاط',
        'b' => ['reward_id' => 3],
        'r' => ['redemption_no' => 'LY-88'],
        'e' => [422 => 'validation_failed', 423 => 'plan_limit_exceeded'],
    ]),
];
