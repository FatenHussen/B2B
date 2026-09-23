<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Content\Domain\Models\HelpGuide;
use Modules\Content\Domain\Models\LegalDocument;
use Modules\Identity\Domain\Models\PlatformUser;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function contentAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

it('publishes and lists a legal document version', function () {
    contentAdmin();

    $created = $this->postJson('/api/v1/platform/content/legal', [
        'type' => 'privacy',
        'body_ar' => 'نص الخصوصية',
        'effective_from' => '2026-03-15',
        'requires_reconsent' => true,
        'reason' => 'تحديث بنود البيانات',
    ])->assertCreated()->json('data');

    expect($created['version'])->toBe('2026-03');

    $this->getJson('/api/v1/platform/content/legal?type=privacy')
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id'])
        ->assertJsonPath('data.0.requires_reconsent', true);

    expect(LegalDocument::query()->count())->toBe(1);
});

it('creates and lists help guides', function () {
    contentAdmin();

    $created = $this->postJson('/api/v1/platform/content/help', [
        'audience' => 'retailer',
        'title' => 'كيف أرسل طلباً',
        'body' => 'الخطوات…',
        'status' => 'published',
    ])->assertCreated()->json('data');

    $this->getJson('/api/v1/platform/content/help?filter[audience]=retailer')
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id'])
        ->assertJsonPath('data.0.status', 'published');

    expect(HelpGuide::query()->count())->toBe(1);
});
