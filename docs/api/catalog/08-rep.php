<?php

declare(strict_types=1);

return [
    ep('EP-RP-001', 'SP-01', 'POST', '/app/rep/register', 'rep', null, [
        'name' => 'Complete rep registration',
        'name_ar' => 'إكمال تسجيل المندوب',
        'b' => [
            'name' => 'أحمد العلي',
            'supply_channel_id' => 1,
            'activity_type_id' => 3,
            'zone_ids' => [12, 13],
            'note' => 'خبرة سنتين في المزة',
        ],
        'r' => [
            'rep' => [
                'id' => 70,
                'name' => 'أحمد العلي',
                'channel' => ['id' => 1, 'name' => 'شركة النور'],
                'zones' => [['id' => 12, 'name' => 'المزة']],
                'status' => 'pending_review',
            ],
            'token' => '50|rep_xxxxx',
        ],
        'd' => 'Channel must be active; zones must be inside its coverage (TB-RP-010).',
    ]),

    ep('EP-RP-010', 'SP-06', 'GET', '/app/rep/products', 'rep', null, [
        'name' => 'Rep catalog',
        'name_ar' => 'منتجات المندوب',
        'q' => listQuery([
            'search' => 'زيت',
            'filter[category_id]' => '340',
            'filter[brand_id]' => '12',
            'filter[offer_only]' => '0',
            'filter[available_only]' => '1',
            'filter[channel_id]' => '1',
            'barcode' => '',
        ]),
        'r' => [[
            'id' => 880,
            'name' => 'زيت دوار الشمس 1 لتر',
            'channel' => ['id' => 1, 'name' => 'شركة النور'],
            'price' => ['type' => 'tiered', 'value' => 12000, 'label' => 'السعر حسب الكمية'],
            'availability' => 'in_stock',
        ]],
        'd' => 'Union of all channels the rep belongs to, each SKU tagged with its channel (TB-RP-050).',
    ]),

    ep('EP-RP-020', 'SP-09', 'GET', '/app/rep/zones/{id}/shops', 'rep', null, [
        'name' => 'Shops in zone',
        'name_ar' => 'محلات المنطقة',
        'q' => ['search' => 'النور'],
        'r' => [[
            'id' => 481,
            'shop_name' => 'بقالية النور',
            'address' => 'المزة فيلات شرقية',
            'is_open' => true,
            'is_active' => true,
            'last_order_at' => '2026-02-28T16:00:00+03:00',
        ]],
        'd' => 'Available offline from the cached pull (TB-RP-020).',
    ]),
    ep('EP-RP-021', 'SP-09', 'POST', '/app/rep/cart/lines', 'rep', null, [
        'name' => 'Add line to shop cart',
        'name_ar' => 'إضافة لسلة محل',
        'b' => [
            'retailer_id' => 481,
            'product_id' => 880,
            'variant_id' => 1,
            'qty' => 4,
        ],
        'r' => [
            'sections' => [[
                'retailer' => ['id' => 481, 'shop_name' => 'بقالية النور'],
                'lines' => [['product_id' => 880, 'qty' => 4, 'unit_price' => 12000]],
                'total' => 48000,
                'discount' => 0,
            ]],
        ],
    ]),
    ep('EP-RP-022', 'SP-09', 'GET', '/app/rep/cart', 'rep', null, [
        'name' => 'Rep cart',
        'name_ar' => 'سلة المندوب',
        'r' => [
            'sections' => [[
                'retailer' => ['id' => 481, 'shop_name' => 'بقالية النور'],
                'lines' => [['product_id' => 880, 'qty' => 4]],
                'total' => 48000,
                'discount' => 0,
            ]],
        ],
        'd' => 'Grouped by shop.',
    ]),
    ep('EP-RP-023', 'SP-09', 'POST', '/app/rep/cart/sections/{retailer_id}/submit', 'rep', null, [
        'name' => 'Submit shop order',
        'name_ar' => 'إرسال طلب محل',
        'b' => [
            'note' => 'توصيل صباحي',
            'discount_percent' => 2,
        ],
        'r' => ['sub_order' => ['id' => 9001, 'sub_order_no' => 'SO-9001', 'status' => 'pending', 'total' => 47040]],
        'e' => [403 => 'discount_cap_exceeded'],
        'd' => 'discount_percent must be ≤ the rep cap (BR-12).',
    ]),

    ep('EP-RP-030', 'SP-10', 'GET', '/app/rep/assignments', 'rep', 'rp.delivery.accept', [
        'name' => 'Pending assignments',
        'name_ar' => 'إسنادات بانتظار القبول',
        'r' => [[
            'id' => 9001,
            'sub_order_no' => 'SO-9001',
            'shop' => 'بقالية النور',
            'zone' => 'المزة',
            'channel' => 'شركة النور',
            'invoice_no' => null,
            'created_at' => '2026-03-01T10:00:00+03:00',
        ]],
        'd' => 'Grouped by zone. Accept moves the card to deliveries (REQ-IN-03).',
        'in' => 'REQ-IN-03',
    ]),
    ep('EP-RP-031', 'SP-10', 'POST', '/app/rep/assignments/{id}/accept', 'rep', 'rp.delivery.accept', [
        'name' => 'Accept assignment',
        'name_ar' => 'قبول الإسناد',
        'b' => new stdClass(),
        'r' => ['status' => 'accepted'],
        'in' => 'REQ-IN-03',
    ]),
    ep('EP-RP-032', 'SP-10', 'POST', '/app/rep/assignments/{id}/reject', 'rep', 'rp.delivery.accept', [
        'name' => 'Reject assignment',
        'name_ar' => 'رفض الإسناد',
        'b' => ['reason' => 'خارج مساري اليوم'],
        'r' => ['status' => 'unassigned'],
        'd' => 'Returns to unassigned + immediate channel alert (REQ-IN-03).',
        'in' => 'REQ-IN-03',
    ]),
    ep('EP-RP-033', 'SP-10', 'GET', '/app/rep/scheduled-orders', 'rep', 'rp.delivery.accept', [
        'name' => 'Scheduled orders',
        'name_ar' => 'الطلبات المجدولة',
        'q' => ['date' => '2026-03-02'],
        'r' => [[
            'id' => 9001,
            'shop_logo' => null,
            'shop' => 'بقالية النور',
            'address' => 'المزة فيلات شرقية',
            'phone' => '+963933000000',
            'scheduled_at' => '2026-03-02T10:00:00+03:00',
            'status' => 'postponed',
            'color' => 'amber',
        ]],
    ]),
    ep('EP-RP-034', 'SP-10', 'PATCH', '/app/rep/status', 'rep', null, [
        'name' => 'On-duty toggle',
        'name_ar' => 'داخل/خارج الخدمة',
        'b' => ['on_duty' => true],
        'r' => ['on_duty' => true, 'tracking_enabled' => true],
        'd' => 'Tracking is enabled only while on duty (REQ-CM-054).',
    ]),

    ep('EP-RP-040', 'SP-11', 'GET', '/app/rep/warehouse-receipts', 'rep', 'rp.warehouse.receive', [
        'name' => 'Warehouse receipts',
        'name_ar' => 'استلام العهدة',
        'q' => ['date' => '2026-03-01'],
        'r' => [
            'date' => '2026-03-01',
            'rep_name' => 'أحمد العلي',
            'count' => 2,
            'orders' => [
                ['sub_order_id' => 9001, 'order_no' => 'SO-9001', 'shop' => 'بقالية النور', 'zone' => 'المزة'],
            ],
        ],
        'in' => 'REQ-IN-02',
    ]),
    ep('EP-RP-041', 'SP-11', 'POST', '/app/rep/warehouse-receipts/{handoverId}/confirm', 'rep', 'rp.warehouse.receive', [
        'name' => 'Confirm handover',
        'name_ar' => 'تأكيد استلام العهدة',
        'b' => ['temp_code' => '7391'],
        'r' => ['status' => 'on_the_way', 'tracking_enabled' => true],
        'd' => 'Stock is deducted and status becomes on_the_way only when BOTH EP-WH-019 and this confirm exist (REQ-IN-02). temp_code used if previously offline.',
        'in' => 'REQ-IN-02',
    ]),

    ep('EP-RP-050', 'SP-12', 'GET', '/app/rep/deliveries', 'rep', 'rp.delivery.deliver', [
        'name' => 'Delivery list',
        'name_ar' => 'قائمة التسليم',
        'q' => ['filter[zone_id]' => '12'],
        'r' => [
            'zones' => [[
                'name' => 'المزة',
                'total' => 4,
                'delivered' => 1,
                'cards' => [[
                    'id' => 9001,
                    'shop' => 'بقالية النور',
                    'zone' => 'المزة',
                    'channel' => 'شركة النور',
                    'invoice_no' => 'INV-501',
                    'ordered_at' => '2026-03-01T09:10:00+03:00',
                    'status' => 'accepted',
                    'border_color' => 'blue',
                ]],
            ]],
        ],
    ]),
    ep('EP-RP-051', 'SP-12', 'GET', '/app/rep/deliveries/{id}', 'rep', 'rp.delivery.deliver', [
        'name' => 'Delivery detail',
        'name_ar' => 'تفاصيل التسليم',
        'r' => [
            'lines' => [[
                'id' => 1,
                'image' => null,
                'name' => 'زيت دوار الشمس 1 لتر',
                'brand' => 'نور',
                'variant' => null,
                'qty' => 4,
                'price' => 12000,
                'status' => 'pending',
            ]],
            'invoice_total' => 48000,
        ],
        'in' => 'REQ-IN-05',
    ]),
    ep('EP-RP-052', 'SP-12', 'PATCH', '/app/rep/deliveries/{id}/lines/{lineId}', 'rep', 'rp.delivery.deliver', [
        'name' => 'Adjust delivery line',
        'name_ar' => 'تعديل بند التسليم',
        'b' => [
            'qty_delivered' => 3,
            'action' => 'return',
            'reason' => 'رفض التاجر عبوة',
        ],
        'r' => ['new_invoice_total' => 36000],
        'd' => 'action: adjust|return|exchange. Updates the invoice on both sides in the same transaction (REQ-IN-05).',
        'in' => 'REQ-IN-05',
    ]),
    ep('EP-RP-053', 'SP-12', 'POST', '/app/rep/deliveries/{id}/complete', 'rep', 'rp.delivery.deliver', [
        'name' => 'Complete delivery',
        'name_ar' => 'إنهاء التسليم',
        'b' => [
            'lines' => [['line_id' => 1, 'qty_delivered' => 4, 'action' => 'accept']],
            'delivered_at' => '2026-03-01T12:05:00+03:00',
            'signature' => 'data:image/png;base64,...',
        ],
        'r' => [
            'invoice' => ['no' => 'INV-501', 'total' => 48000],
            'receipt_no' => 'RCPT-10041',
        ],
    ]),
    ep('EP-RP-054', 'SP-12', 'POST', '/app/rep/deliveries/{id}/postpone', 'rep', 'rp.delivery.postpone', [
        'name' => 'Postpone delivery',
        'name_ar' => 'تأجيل التسليم',
        'b' => [
            'scheduled_at' => '2026-03-02T10:00:00+03:00',
            'reason' => 'المحل مغلق',
        ],
        'r' => ['status' => 'postponed'],
        'd' => 'Automatically added to scheduled orders.',
    ]),
    ep('EP-RP-055', 'SP-12', 'POST', '/app/rep/deliveries/{id}/fail', 'rep', 'rp.delivery.deliver', [
        'name' => 'Mark undelivered',
        'name_ar' => 'تعذر التسليم',
        'b' => ['reason' => 'رفض الاستلام'],
        'r' => ['status' => 'undelivered', 'border_color' => 'red'],
    ]),
    ep('EP-RP-056', 'SP-12', 'POST', '/app/rep/locations/ping', 'rep', null, [
        'name' => 'Location pings',
        'name_ar' => 'نبضات الموقع',
        'b' => [
            'pings' => [[
                'lat' => 33.5112,
                'lng' => 36.2781,
                'at' => '2026-03-01T11:41:00+03:00',
                'accuracy' => 12,
            ]],
        ],
        'r' => ['accepted' => 1],
        'd' => 'Batched pings, on-duty only. Rate: 1/30s per rep (REQ-CM-044).',
        'e' => [429 => 'rate_limited'],
    ]),
    ep('EP-RP-057', 'SP-12', 'POST', '/app/rep/return-requests', 'rep', 'rp.delivery.return_request', [
        'name' => 'Create return from field',
        'name_ar' => 'طلب إرجاع ميداني',
        'b' => [
            'sub_order_id' => 9001,
            'type' => 'exchange',
            'lines' => [['line_id' => 1, 'qty' => 1, 'reason' => 'خطأ صنف', 'photos' => []]],
        ],
        'r' => ['request_no' => 'RR-201', 'status' => 'pending'],
        'in' => 'REQ-IN-04',
    ]),

    ep('EP-RP-060', 'SP-13', 'POST', '/app/rep/payments', 'rep', 'rp.payment.collect', [
        'name' => 'Collect payment',
        'name_ar' => 'تحصيل دفعة',
        'b' => [
            'receipt_no' => 'RCPT-10041',
            'retailer_id' => 481,
            'invoice_no' => 'INV-501',
            'amount' => 48000,
            'paid_at' => '2026-03-01T12:08:00+03:00',
            'client_op_id' => 'op_rep_pay_10041',
        ],
        'r' => [
            'payment' => ['id' => 301],
            'wallet_balance' => 210000,
            'retailer_receivable' => 0,
        ],
        'd' => 'receipt_no must be reserved first via EP-CM-050. client_op_id makes retries duplicate-safe (REQ-IN-01).',
        'in' => 'REQ-IN-01',
        'e' => [409 => 'duplicate_receipt_no'],
    ]),
    ep('EP-RP-061', 'SP-13', 'GET', '/app/rep/wallet', 'rep', 'rp.wallet.view', [
        'name' => 'Wallet',
        'name_ar' => 'المحفظة',
        'r' => [
            'net_balance' => 210000,
            'stats' => [
                'invoices_delivered' => 18,
                'collected_total' => 860000,
                'receivables' => 120000,
            ],
            'today' => [
                'invoices' => 4,
                'collected' => 180000,
                'receivables' => 30000,
            ],
        ],
        'd' => 'net_balance == SUM(collected) − SUM(settled) at all times (AC-06).',
    ]),
    ep('EP-RP-062', 'SP-13', 'POST', '/app/rep/wallet/withdrawals', 'rep', 'rp.payment.withdraw', [
        'name' => 'Record cash handover',
        'name_ar' => 'تسليم نقدية',
        'b' => [
            'amount' => 1500000,
            'operation_no' => 'OP-7781',
            'operated_at' => '2026-03-01T16:00:00+03:00',
        ],
        'r' => ['remaining_balance' => 250000],
        'd' => 'operation_no comes from the accountant and must stay unique across sync retries (TB-RP-063).',
    ]),
    ep('EP-RP-063', 'SP-13', 'GET', '/app/rep/wallet/withdrawals', 'rep', 'rp.wallet.view', [
        'name' => 'Withdrawal history',
        'name_ar' => 'سجل التسليمات',
        'q' => ['date_from' => '2026-02-01', 'date_to' => '2026-02-28'],
        'r' => [
            'rows' => [['operation_no' => 'OP-7700', 'amount' => 900000, 'operated_at' => '2026-02-20']],
            'total' => 900000,
        ],
    ]),
    ep('EP-RP-064', 'SP-13', 'GET', '/app/rep/receivables', 'rep', 'rp.wallet.view', [
        'name' => 'Shop receivables',
        'name_ar' => 'ذمم المحلات',
        'r' => [
            'by_shop' => [[
                'retailer_id' => 481,
                'shop' => 'بقالية النور',
                'total' => 48000,
                'invoices' => [['no' => 'INV-501', 'total' => 48000, 'paid' => 0, 'remaining' => 48000]],
            ]],
        ],
        'd' => 'Each by_shop row includes retailer_id (RetailerProfile id).',
    ]),

    ep('EP-RP-070A', 'SP-06', 'GET', '/app/rep/customers', 'rep', null, [
        'name' => 'List customers',
        'name_ar' => 'زبائن المندوب',
        'q' => listQuery(['search' => 'النور']),
        'r' => [['id' => 481, 'shop_name' => 'بقالية النور', 'zone_id' => 12, 'is_active' => true]],
        'd' => 'DOC-12B TB-RP-053.',
    ]),
    ep('EP-RP-070B', 'SP-06', 'POST', '/app/rep/customers', 'rep', null, [
        'name' => 'Register shop offline',
        'name_ar' => 'إضافة محل',
        'b' => [
            'shop_name' => 'ميني ماركت الشام',
            'owner_name' => 'أبو سامر',
            'phone' => '+963988000000',
            'zone_id' => 12,
            'activity_type_id' => 3,
            'lat' => 33.51,
            'lng' => 36.27,
            'client_op_id' => 'op_shop_local_1',
        ],
        'r' => ['id' => 490, 'status' => 'pending_sync'],
        'd' => 'Stored locally and synced with a unique client_op_id (TB-RP-053).',
    ]),
    ep('EP-RP-071', 'SP-06', 'POST', '/app/rep/zones', 'rep', null, [
        'name' => 'Request extra zone',
        'name_ar' => 'إضافة منطقة تغطية',
        'b' => ['zone_id' => 14, 'note' => 'طلب تغطية كفرسوسة'],
        'r' => ['status' => 'pending_approval'],
        'd' => 'DOC-12B TB-RP-055.',
    ]),
];
