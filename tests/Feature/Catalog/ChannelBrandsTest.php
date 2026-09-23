<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function brandsManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function brandsActivity(): ActivityType
{
    return ActivityType::query()->create([
        'name' => 'بقالة',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);
}

/**
 * @return array{logo: string, banner: string}
 */
function brandsUploadPair(int $edge = 512): array
{
    $logo = test()->post('/api/v1/channel/media/upload', [
        'file' => UploadedFile::fake()->image('logo.jpg', $edge, $edge),
        'type' => 'image',
    ], ['X-Idempotency-Key' => 'media-logo-'.uniqid()]);
    CatalogAssert::ok($logo, ['media_id', 'url'], 201);

    $banner = test()->post('/api/v1/channel/media/upload', [
        'file' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        'type' => 'image',
    ], ['X-Idempotency-Key' => 'media-banner-'.uniqid()]);
    CatalogAssert::ok($banner, ['media_id'], 201);

    return [
        'logo' => (string) $logo->json('data.media_id'),
        'banner' => (string) $banner->json('data.media_id'),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function brandsPayload(ActivityType $activity, array $overrides = []): array
{
    $media = brandsUploadPair();

    return array_merge([
        'name_ar' => 'نور',
        'name_en' => 'Nour',
        'logo' => $media['logo'],
        'banner' => $media['banner'],
        'description' => 'منتجات غذائية',
        'activity_type_ids' => [$activity->id],
        'order' => 5,
        'status' => 'active',
    ], $overrides);
}

it('uploads channel media and returns media_id plus url', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');

    $res = $this->post('/api/v1/channel/media/upload', [
        'file' => UploadedFile::fake()->image('logo.jpg', 640, 640),
        'type' => 'image',
    ], ['X-Idempotency-Key' => 'upload-1']);

    CatalogAssert::ok($res, ['media_id', 'url', 'type', 'mime', 'size'], 201);
    expect($res->json('data.type'))->toBe('image')
        ->and($res->json('data.url'))->not->toBeEmpty();
});

it('creates a brand with sliders and returns the editor body on show', function () {
    $channel = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');

    $payload = brandsPayload($activity, [
        'sliders' => [
            ['name' => 'الأكثر مبيعاً', 'source' => 'algorithm', 'source_id' => null, 'count' => 12, 'order' => 1],
        ],
    ]);

    $create = $this->postJson('/api/v1/channel/brands', $payload, ['X-Idempotency-Key' => 'brand-create-1']);
    CatalogAssert::ok($create, ['id'], 201);
    $id = (int) $create->json('data.id');

    $show = $this->getJson("/api/v1/channel/brands/{$id}");
    CatalogAssert::ok($show);
    expect($show->json('data'))->toMatchArray([
        'id' => $id,
        'name_ar' => 'نور',
        'name_en' => 'Nour',
        'logo' => $payload['logo'],
        'banner' => $payload['banner'],
        'description' => 'منتجات غذائية',
        'activity_type_ids' => [$activity->id],
        'order' => 5,
        'status' => 'active',
    ])->and($show->json('data.logo_url'))->not->toBeEmpty()
        ->and($show->json('data.sliders.0'))->toMatchArray([
            'name' => 'الأكثر مبيعاً',
            'source' => 'algorithm',
            'count' => 12,
            'order' => 1,
        ]);
});

it('lists brands with search status activity filter and order sort', function () {
    $channel = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    $other = ActivityType::query()->create([
        'name' => 'مطعم',
        'order' => 2,
        'status' => RefStatus::Active,
    ]);
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');

    $this->postJson('/api/v1/channel/brands', brandsPayload($activity, [
        'name_ar' => 'نور',
        'order' => 20,
        'status' => 'active',
    ]));
    $this->postJson('/api/v1/channel/brands', brandsPayload($other, [
        'name_ar' => 'الياسمين',
        'name_en' => 'Yasmin',
        'order' => 10,
        'status' => 'disabled',
    ]));

    $active = $this->getJson('/api/v1/channel/brands?filter[status]=active&sort=order');
    CatalogAssert::ok($active);
    expect(collect($active->json('data'))->pluck('name_ar')->all())->toBe(['نور']);

    $byActivity = $this->getJson('/api/v1/channel/brands?filter[activity_type_id]='.$activity->id);
    expect(collect($byActivity->json('data'))->pluck('name_ar')->all())->toBe(['نور']);

    $search = $this->getJson('/api/v1/channel/brands?filter[search]=yas');
    expect(collect($search->json('data'))->pluck('name_ar')->all())->toBe(['الياسمين']);

    $sorted = $this->getJson('/api/v1/channel/brands?sort=order');
    expect(collect($sorted->json('data'))->pluck('order')->all())->toBe([10, 20]);
});

it('updates a brand and replaces sliders', function () {
    $channel = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');

    $created = brandsPayload($activity, [
        'sliders' => [
            ['name' => 'قديم', 'source' => 'manual', 'count' => 3, 'order' => 1],
        ],
    ]);
    $id = $this->postJson('/api/v1/channel/brands', $created)->json('data.id');

    $media = brandsUploadPair();
    $update = $this->putJson("/api/v1/channel/brands/{$id}", [
        'name_ar' => 'نور المحدّثة',
        'name_en' => 'Nour Updated',
        'logo' => $media['logo'],
        'banner' => $media['banner'],
        'description' => 'وصف جديد',
        'activity_type_ids' => [$activity->id],
        'order' => 2,
        'status' => 'disabled',
        'sliders' => [
            ['name' => 'جديد', 'source' => 'algorithm', 'count' => 8, 'order' => 1],
        ],
    ], ['X-Idempotency-Key' => 'brand-update-1']);
    CatalogAssert::ok($update, ['id']);

    $show = $this->getJson("/api/v1/channel/brands/{$id}");
    expect($show->json('data.name_ar'))->toBe('نور المحدّثة')
        ->and($show->json('data.status'))->toBe('disabled')
        ->and($show->json('data.sliders'))->toHaveCount(1)
        ->and($show->json('data.sliders.0.name'))->toBe('جديد');
});

it('rejects a logo smaller than 512 square', function () {
    $channel = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');

    $small = $this->post('/api/v1/channel/media/upload', [
        'file' => UploadedFile::fake()->image('small.jpg', 128, 128),
        'type' => 'image',
    ], ['X-Idempotency-Key' => 'small-logo']);
    CatalogAssert::ok($small, ['media_id'], 201);

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/brands', [
            'name_ar' => 'نور',
            'logo' => (string) $small->json('data.media_id'),
            'description' => 'وصف',
            'activity_type_ids' => [$activity->id],
        ]),
        422,
        'validation_failed',
    );
});

it('rejects duplicate name_ar inside the same channel', function () {
    $channel = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');

    $this->postJson('/api/v1/channel/brands', brandsPayload($activity))->assertCreated();
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/brands', brandsPayload($activity, ['name_ar' => 'نور'])),
        422,
        'validation_failed',
    );
});

it('404s show and update for another channel brand', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    Sanctum::actingAs(brandsManager($foreign), ['*'], 'channel');
    $id = $this->postJson('/api/v1/channel/brands', brandsPayload($activity, ['name_ar' => 'أجنبي']))->json('data.id');

    Sanctum::actingAs(brandsManager($own), ['*'], 'channel');
    CatalogAssert::error($this->getJson("/api/v1/channel/brands/{$id}"), 404, 'not_found');
    CatalogAssert::error(
        $this->putJson("/api/v1/channel/brands/{$id}", brandsPayload($activity, ['name_ar' => 'مسروق'])),
        404,
        'not_found',
    );
})->group('tenancy');

it('keeps a disabled brand row for the channel console', function () {
    $channel = SupplyChannel::factory()->create();
    $activity = brandsActivity();
    Sanctum::actingAs(brandsManager($channel), ['*'], 'channel');
    $id = $this->postJson('/api/v1/channel/brands', brandsPayload($activity, [
        'status' => 'disabled',
    ]))->json('data.id');

    expect(Tenant::as($channel->id, fn () => Brand::query()->whereKey($id)->value('status')))
        ->toBe(BrandStatus::Disabled);

    $list = $this->getJson('/api/v1/channel/brands');
    expect(collect($list->json('data'))->pluck('id')->all())->toContain($id);
});
