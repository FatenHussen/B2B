<?php

declare(strict_types=1);

namespace Modules\Reference\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;

/**
 * The fourteen Syrian governorates and a working set of zones under each.
 *
 * Every client screen before login reads these — the retailer picks a zone to register,
 * the channel picks zones to cover — so an empty table blocks all of them at once. Codes
 * are ISO 3166-2:SY. Safe to run repeatedly: governorates match on `code`, zones on
 * `(governorate_id, name)`, and neither touches `status`, so a zone disabled through
 * EP-AD-034 stays disabled after a reseed.
 */
class ReferenceSeeder extends Seeder
{
    /**
     * @var array<string, array{ar: string, en: string, zones: list<string>}>
     */
    private const GOVERNORATES = [
        'DI' => ['ar' => 'دمشق', 'en' => 'Damascus', 'zones' => [
            'المزة', 'المالكي', 'أبو رمانة', 'كفرسوسة', 'المهاجرين', 'ركن الدين', 'الميدان',
            'باب توما', 'القصاع', 'الشعلان', 'برزة', 'دمر', 'القدم', 'الزاهرة',
        ]],
        'RD' => ['ar' => 'ريف دمشق', 'en' => 'Rif Dimashq', 'zones' => [
            'جرمانا', 'صحنايا', 'قدسيا', 'داريا', 'دوما', 'التل', 'يبرود', 'النبك', 'الزبداني', 'قطنا',
        ]],
        'HL' => ['ar' => 'حلب', 'en' => 'Aleppo', 'zones' => [
            'الحمدانية', 'الفرقان', 'الجميلية', 'حلب الجديدة', 'السريان', 'الشهباء', 'السليمانية', 'العزيزية',
        ]],
        'HI' => ['ar' => 'حمص', 'en' => 'Homs', 'zones' => [
            'الوعر', 'الإنشاءات', 'عكرمة', 'الحمراء', 'الغوطة', 'باب تدمر',
        ]],
        'HM' => ['ar' => 'حماة', 'en' => 'Hama', 'zones' => [
            'الحاضر', 'الصابونية', 'الجراجمة', 'البعث', 'الأربعين',
        ]],
        'LA' => ['ar' => 'اللاذقية', 'en' => 'Latakia', 'zones' => [
            'الزراعة', 'الأزهري', 'الرمل الشمالي', 'مشروع الصليبة', 'الشيخ ضاهر', 'جبلة',
        ]],
        'TA' => ['ar' => 'طرطوس', 'en' => 'Tartus', 'zones' => [
            'الشيخ صالح', 'حي الثورة', 'بانياس', 'صافيتا',
        ]],
        'ID' => ['ar' => 'إدلب', 'en' => 'Idlib', 'zones' => [
            'إدلب المدينة', 'معرة النعمان', 'أريحا', 'جسر الشغور',
        ]],
        'DR' => ['ar' => 'درعا', 'en' => 'Daraa', 'zones' => [
            'درعا المحطة', 'درعا البلد', 'إزرع', 'الصنمين',
        ]],
        'SU' => ['ar' => 'السويداء', 'en' => 'As-Suwayda', 'zones' => [
            'السويداء المدينة', 'شهبا', 'صلخد',
        ]],
        'QU' => ['ar' => 'القنيطرة', 'en' => 'Quneitra', 'zones' => [
            'خان أرنبة', 'فيق',
        ]],
        'DY' => ['ar' => 'دير الزور', 'en' => 'Deir ez-Zor', 'zones' => [
            'الجورة', 'القصور', 'الميادين', 'البوكمال',
        ]],
        'RA' => ['ar' => 'الرقة', 'en' => 'Raqqa', 'zones' => [
            'الرقة المدينة', 'تل أبيض', 'الثورة',
        ]],
        'HA' => ['ar' => 'الحسكة', 'en' => 'Al-Hasakah', 'zones' => [
            'الحسكة المدينة', 'القامشلي', 'رأس العين', 'المالكية',
        ]],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::GOVERNORATES as $code => $row) {
            $governorate = Governorate::query()->updateOrCreate(
                ['code' => $code],
                ['name_ar' => $row['ar'], 'name_en' => $row['en'], 'order' => ++$order],
            );

            foreach ($row['zones'] as $i => $name) {
                Zone::query()->updateOrCreate(
                    ['governorate_id' => $governorate->id, 'name' => $name],
                    ['order' => $i + 1],
                );
            }
        }
    }
}
