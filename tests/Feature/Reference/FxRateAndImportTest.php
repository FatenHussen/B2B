<?php

declare(strict_types=1);

/**
 * BE-R09 — EP-AD-040 / EP-AD-043G, and BE-R11 — EP-AD-041.
 */

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\FxRateResolver;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\FxRate;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\ImportBatch;
use Modules\Reference\Domain\Models\Zone;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function fxAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

function csvUpload(string $name, string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

// ── BE-R09 ──────────────────────────────────────────────────────────────────────────

it('posts an integer rate at the fixed FX scale and closes the previous one', function () {
    fxAdmin();
    $base = Currency::query()->where('iso', 'SYP')->firstOrFail();
    $usd = Currency::query()->create(['iso' => 'USD', 'name' => 'دولار', 'decimals' => 2]);

    $first = $this->postJson('/api/v1/platform/refs/fx-rates', [
        'currency_id' => $usd->id,
        'rate' => 1_500_000, // 1.5 at Money::FX_SCALE (10^6)
        'effective_from' => '2026-03-01T00:00:00+03:00',
        'source' => 'manual',
    ])->assertCreated()
        ->assertJsonPath('data.currency_id', $usd->id)
        ->assertJsonPath('data.base_currency_id', $base->id)
        ->assertJsonPath('data.rate', 1_500_000)
        ->json('data.id');

    $this->postJson('/api/v1/platform/refs/fx-rates', [
        'currency_id' => $usd->id,
        'rate' => 1_550_000,
        'effective_from' => '2026-04-01T00:00:00+03:00',
    ])->assertCreated();

    expect(FxRate::query()->find($first)?->effective_to?->toIso8601String())->toBe('2026-03-31T21:00:00+00:00')
        ->and(app(FxRateResolver::class)->prevailing($usd->id, $base->id, new DateTimeImmutable('2026-04-15'))->rate)->toBe(1_550_000)
        ->and(app(FxRateResolver::class)->prevailing($usd->id, $base->id, new DateTimeImmutable('2026-03-15'))->rate)->toBe(1_500_000);

    $this->getJson("/api/v1/platform/refs/fx-rates?filter[currency_id]={$usd->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.rate', 1_550_000);
});

it('refuses a decimal rate so no float reaches the FX path', function () {
    fxAdmin();
    $usd = Currency::query()->create(['iso' => 'USD', 'name' => 'دولار', 'decimals' => 2]);

    $this->postJson('/api/v1/platform/refs/fx-rates', [
        'currency_id' => $usd->id,
        'rate' => 1.5,
        'effective_from' => '2026-03-01T00:00:00+03:00',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect(FxRate::query()->count())->toBe(0);
});

it('refuses a rate for the base currency against itself', function () {
    fxAdmin();
    $base = Currency::query()->where('iso', 'SYP')->firstOrFail();

    $this->postJson('/api/v1/platform/refs/fx-rates', [
        'currency_id' => $base->id,
        'rate' => Money::FX_UNIT,
        'effective_from' => '2026-03-01T00:00:00+03:00',
    ])->assertStatus(422)
        ->assertJsonPath('error.details.currency_id', fn ($v) => is_array($v) && $v !== []);
});

// ── BE-R11 ──────────────────────────────────────────────────────────────────────────

it('a dry run writes nothing, provably', function () {
    fxAdmin();
    $gov = Governorate::factory()->create(['code' => 'DI']);
    $before = Zone::query()->count();

    $csv = "governorate_code,name,district,order\nDI,المزة,المزة,1\nDI,كفرسوسة,,2\n";

    $this->post('/api/v1/platform/refs/import', [
        'type' => 'zones',
        'file' => csvUpload('zones.csv', $csv),
        'dry_run' => '1',
    ], ['Accept' => 'application/json', 'X-Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.dry_run', true)
        ->assertJsonPath('data.rows_total', 2)
        ->assertJsonPath('data.errors', [])
        ->assertJsonPath('data.preview.0.name', 'المزة')
        ->assertJsonPath('data.preview.1.row', 3);

    expect(Zone::query()->count())->toBe($before)
        ->and(ImportBatch::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'like', 'ref.import%')->count())->toBe(0)
        ->and($gov->zones()->count())->toBe(0);
});

it('row errors name the exact row and column', function () {
    fxAdmin();
    Governorate::factory()->create(['code' => 'DI']);

    $csv = "governorate_code,name,order\nDI,المزة,1\nXX,برزة,2\nDI,,abc\nDI,المزة,4\n";

    $errors = $this->post('/api/v1/platform/refs/import', [
        'type' => 'zones',
        'file' => csvUpload('zones.csv', $csv),
        'dry_run' => '1',
    ], ['Accept' => 'application/json', 'X-Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->json('data.errors');

    $pairs = collect($errors)->map(fn (array $e) => $e['row'].':'.$e['column'])->all();

    expect($pairs)->toContain('3:governorate_code')
        ->and($pairs)->toContain('4:name')
        ->and($pairs)->toContain('4:order')
        ->and($pairs)->toContain('5:name')
        ->and($pairs)->not->toContain('2:name');
});

it('executes with dry_run=false and a fresh key, and the same key does not double-import', function () {
    fxAdmin();
    $gov = Governorate::factory()->create(['code' => 'DI']);
    $csv = "governorate_code,name,order\nDI,المزة,1\nDI,كفرسوسة,2\n";
    $key = (string) Str::uuid();
    $headers = ['Accept' => 'application/json', 'X-Idempotency-Key' => $key];

    $first = $this->post('/api/v1/platform/refs/import', [
        'type' => 'zones',
        'file' => csvUpload('zones.csv', $csv),
        'dry_run' => '0',
    ], $headers)->assertOk()
        ->assertJsonPath('data.dry_run', false)
        ->assertJsonPath('data.rows_created', 2)
        ->assertJsonPath('data.rows_updated', 0);

    expect($gov->zones()->count())->toBe(2)
        ->and(ImportBatch::query()->count())->toBe(1);

    // Same key, same body: the stored response replays and nothing is written again.
    $replay = $this->post('/api/v1/platform/refs/import', [
        'type' => 'zones',
        'file' => csvUpload('zones.csv', $csv),
        'dry_run' => '0',
    ], $headers)->assertOk();

    expect($replay->json('data.batch_id'))->toBe($first->json('data.batch_id'))
        ->and($gov->zones()->count())->toBe(2)
        ->and(ImportBatch::query()->count())->toBe(1);

    // A fresh key re-imports the same file as updates, never as duplicates.
    $this->post('/api/v1/platform/refs/import', [
        'type' => 'zones',
        'file' => csvUpload('zones.csv', $csv),
        'dry_run' => '0',
    ], ['Accept' => 'application/json', 'X-Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.rows_created', 0)
        ->assertJsonPath('data.rows_updated', 2);

    expect($gov->zones()->count())->toBe(2);
});

it('refuses an import with any invalid row rather than importing half a file', function () {
    fxAdmin();
    $gov = Governorate::factory()->create(['code' => 'DI']);
    $csv = "governorate_code,name\nDI,المزة\nXX,برزة\n";

    $this->post('/api/v1/platform/refs/import', [
        'type' => 'zones',
        'file' => csvUpload('zones.csv', $csv),
        'dry_run' => '0',
    ], ['Accept' => 'application/json', 'X-Idempotency-Key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.dry_run', false)
        ->assertJsonCount(1, 'data.errors')
        ->assertJsonMissingPath('data.batch_id');

    expect($gov->zones()->count())->toBe(0);
});

it('names ad.refs.import on a 403 for a platform user without it', function () {
    $user = PlatformUser::factory()->create();
    $user->givePermissionTo('ad.refs.create');
    Sanctum::actingAs($user, ['*'], 'platform');

    $this->post('/api/v1/platform/refs/import', [
        'type' => 'governorates',
        'file' => csvUpload('g.csv', "code,name_ar,name_en\nDI,دمشق,Damascus\n"),
        'dry_run' => '1',
    ], ['Accept' => 'application/json', 'X-Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden()
        ->assertJsonPath('error.permission', 'ad.refs.import');
});
