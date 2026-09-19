<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepProfileZone;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Application\Actions\OverrideChannelLimits;
use Modules\Tenancy\Application\Actions\UpdateChannel;
use Modules\Tenancy\Application\Services\ChannelLifecycle;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\ChannelActivityType;
use Modules\Tenancy\Domain\Models\ChannelGovernorate;
use Modules\Tenancy\Domain\Models\ChannelInternalNote;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\ChannelProvisionJob;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seven more channels for the platform back office, so its list, detail, timeline,
 * usage and audit screens have a population and not one row.
 *
 * Every channel state the back office can show is here: active on each plan, one on an
 * open trial, one suspended, one archived, one whose provisioning failed and can be
 * retried, one still queued. The active ones carry a manager, staff, warehouses, reps,
 * retailers, the demo catalog with its price book and ninety days of orders — the same
 * counters `GET /platform/channels/{id}/usage` reads.
 *
 * Status moves only through `ChannelLifecycle` (rule 8) and the limit override and
 * internal notes go through the real actions, so `channel_events` and `audit_logs` are
 * what those writers produce. The clock is moved with `Carbon::setTestNow` around each
 * write so the trail is spread over months, not stamped at seed time.
 *
 * Runs outside any tenant and sets one per channel. Safe to re-run: every row matches on
 * a natural key, and the status trail is written only while the channel is still in
 * `provisioning` — once it has left, its history exists and is not replayed.
 */
final class DemoPlatformSeeder extends DemoSeeder
{
    /**
     * Zone coverage defaults for the channels below: fee and minimum as decimal strings
     * because `ChannelZone` stores them through `MoneyCast`.
     */
    private const COVERAGE_FEE = '6000.00';

    private const COVERAGE_MIN = '120000.00';

    /** @var list<string> */
    private const COVERAGE_DAYS = ['sun', 'mon', 'tue', 'wed', 'thu'];

    /**
     * @var array<string, array{
     *   name: string, legal_name: string, legal_form: string, cr_number: string, tax_number: string,
     *   phone: string, email: string, plan: string, billing_cycle: string, trial_days: int,
     *   created_days_ago: int, created_hours_ago?: int,
     *   governorates: list<string>, zones: list<array{0: string, 1: string}>, activities: list<string>,
     *   manager: array{0: string, 1: string, 2: string},
     *   staff: list<array{0: string, 1: string, 2: string, 3: string}>,
     *   warehouses: list<array{0: string, 1: float, 2: float}>,
     *   reps: list<array{0: string, 1: string, 2: string}>,
     *   retailers: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: float, 7: float, 8: string}>,
     *   catalog: bool, orders_per_day: int,
     *   events: list<array{0: string, 1: int, 2: string}>,
     *   job: array{status: string, steps: list<string>, attempts: int, error: string|null},
     *   notes: list<array{0: int, 1: string}>,
     *   override: array{limits: array<string, int>, days: int, reason: string, days_ago: int}|null
     * }>
     */
    private const CHANNELS = [
        'al-furat' => [
            'name' => 'الفرات للتوزيع',
            'legal_name' => 'شركة الفرات للتوزيع والتجارة م.م',
            'legal_form' => 'llc',
            'cr_number' => 'CR-DI-104512',
            'tax_number' => '011-4512890',
            'phone' => '+963911000201',
            'email' => 'ops@furat-dist.sy',
            'plan' => 'growth',
            'billing_cycle' => 'yearly',
            'trial_days' => 0,
            'created_days_ago' => 150,
            'governorates' => ['DI', 'RD'],
            'zones' => [['DI', 'أبو رمانة'], ['DI', 'المهاجرين'], ['DI', 'ركن الدين'], ['DI', 'الشعلان'], ['DI', 'دمر'], ['DI', 'القدم'], ['RD', 'داريا'], ['RD', 'دوما'], ['RD', 'التل'], ['RD', 'قطنا']],
            'activities' => ['سوبر ماركت', 'بقالة', 'مطعم', 'كافيه'],
            'manager' => ['+963900001001', 'محمد الفرات', 'manager@furat-dist.sy'],
            'staff' => [
                ['+963900001002', 'هدى مديرة المبيعات', 'sales@furat-dist.sy', 'sales_manager'],
                ['+963900001003', 'باسل مدير الكتالوج', 'catalog@furat-dist.sy', 'catalog_manager'],
                ['+963900001004', 'ميساء المحاسبة', 'finance@furat-dist.sy', 'accountant'],
            ],
            'warehouses' => [['مستودع الفرات - المهاجرين', 33.5250, 36.2810], ['مستودع الفرات - دوما', 33.5710, 36.4020]],
            'reps' => [
                ['+963935000001', 'أحمد الفراتي', 'سوبر ماركت'],
                ['+963935000002', 'رامي دياب', 'بقالة'],
                ['+963935000003', 'نبيل عيد', 'مطعم'],
                ['+963935000004', 'سالم قدور', 'بقالة'],
            ],
            'retailers' => [
                ['+963936000001', 'أبو أيمن', 'سوبر ماركت أبو رمانة', 'سوبر ماركت', 'DI', 'أبو رمانة', 33.5180, 36.2860, 'أبو رمانة - شارع الجلاء'],
                ['+963936000002', 'ماهر شيخ الأرض', 'بقالة المهاجرين', 'بقالة', 'DI', 'المهاجرين', 33.5270, 36.2790, 'المهاجرين - شارع 30'],
                ['+963936000003', 'سهى مراد', 'كافيه الشعلان', 'كافيه', 'DI', 'الشعلان', 33.5150, 36.2930, 'الشعلان - شارع الحمراء'],
                ['+963936000004', 'أبو فادي', 'مطعم ركن الدين', 'مطعم', 'DI', 'ركن الدين', 33.5350, 36.2950, 'ركن الدين - الشيخ خالد'],
                ['+963936000005', 'رغد يوسف', 'ماركت دمر', 'سوبر ماركت', 'DI', 'دمر', 33.5330, 36.2260, 'دمر - المشروع - جزيرة 12'],
                ['+963936000006', 'عدنان الحاج', 'بقالة القدم', 'بقالة', 'DI', 'القدم', 33.4790, 36.2960, 'القدم - الشارع العام'],
                ['+963936000007', 'أبو عمر', 'سوبر ماركت داريا', 'سوبر ماركت', 'RD', 'داريا', 33.4580, 36.2380, 'داريا - شارع الثورة'],
                ['+963936000008', 'خلود السيد', 'ماركت دوما', 'سوبر ماركت', 'RD', 'دوما', 33.5720, 36.4030, 'دوما - الساحة الكبرى'],
                ['+963936000009', 'أبو حسام', 'بقالة التل', 'بقالة', 'RD', 'التل', 33.6100, 36.3100, 'التل - الشارع العام'],
                ['+963936000010', 'ليندا عبود', 'مطعم قطنا', 'مطعم', 'RD', 'قطنا', 33.4370, 36.0800, 'قطنا - ساحة البلدية'],
            ],
            'catalog' => true,
            'orders_per_day' => 8,
            'events' => [['active', 149, 'provisioning completed']],
            'job' => ['status' => 'complete', 'steps' => ['limits', 'warehouse', 'invite', 'activate'], 'attempts' => 1, 'error' => null],
            'notes' => [
                [150, 'عميل محوّل من النظام القديم — أكبر موزع في ريف دمشق'],
                [40, 'طلبوا رفع سقف المندوبين مؤقتاً لموسم رمضان'],
            ],
            'override' => ['limits' => ['reps' => 30], 'days' => 45, 'reason' => 'توسّع موسمي: 10 مندوبين إضافيين لموسم رمضان', 'days_ago' => 5],
        ],
        'sham-trading' => [
            'name' => 'شام للتجارة العامة',
            'legal_name' => 'مؤسسة شام للتجارة العامة',
            'legal_form' => 'sole',
            'cr_number' => 'CR-HL-220871',
            'tax_number' => '021-2208710',
            'phone' => '+963911000301',
            'email' => 'info@sham-trading.sy',
            'plan' => 'starter',
            'billing_cycle' => 'monthly',
            'trial_days' => 30,
            'created_days_ago' => 18,
            'governorates' => ['HL'],
            'zones' => [['HL', 'الفرقان'], ['HL', 'الجميلية'], ['HL', 'السريان'], ['HL', 'الشهباء'], ['HL', 'السليمانية'], ['HL', 'العزيزية']],
            'activities' => ['سوبر ماركت', 'بقالة'],
            'manager' => ['+963900002001', 'عبد الرحمن شامي', 'manager@sham-trading.sy'],
            'staff' => [
                ['+963900002002', 'لينا مديرة المبيعات', 'sales@sham-trading.sy', 'sales_manager'],
            ],
            'warehouses' => [['مستودع شام - الشهباء', 36.2150, 37.1450]],
            'reps' => [
                ['+963935000011', 'مصطفى حلاق', 'سوبر ماركت'],
                ['+963935000012', 'جمال قباني', 'بقالة'],
            ],
            'retailers' => [
                ['+963936000011', 'أبو خالد', 'سوبر ماركت الفرقان', 'سوبر ماركت', 'HL', 'الفرقان', 36.2100, 37.1300, 'الفرقان - شارع النيل'],
                ['+963936000012', 'سامي عطار', 'بقالة الجميلية', 'بقالة', 'HL', 'الجميلية', 36.2060, 37.1520, 'الجميلية - شارع الملك فيصل'],
                ['+963936000013', 'ريما نجار', 'ماركت السريان', 'سوبر ماركت', 'HL', 'السريان', 36.2110, 37.1400, 'السريان الجديدة'],
                ['+963936000014', 'أبو جورج', 'بقالة العزيزية', 'بقالة', 'HL', 'العزيزية', 36.2120, 37.1600, 'العزيزية - شارع الكنيسة'],
                ['+963936000015', 'محمد حاج علي', 'سوبر ماركت السليمانية', 'سوبر ماركت', 'HL', 'السليمانية', 36.2140, 37.1650, 'السليمانية - شارع الرئيسي'],
            ],
            'catalog' => true,
            'orders_per_day' => 3,
            'events' => [['active', 18, 'provisioning completed']],
            'job' => ['status' => 'complete', 'steps' => ['limits', 'warehouse', 'invite', 'activate'], 'attempts' => 1, 'error' => null],
            'notes' => [[18, 'تجربة 30 يوماً — متابعة التحويل قبل انتهاء الفترة']],
            'override' => null,
        ],
        'barada-foods' => [
            'name' => 'بردى للمواد الغذائية',
            'legal_name' => 'شركة بردى للمواد الغذائية م.م',
            'legal_form' => 'llc',
            'cr_number' => 'CR-HI-330419',
            'tax_number' => '031-3304190',
            'phone' => '+963911000401',
            'email' => 'ops@barada-foods.sy',
            'plan' => 'growth',
            'billing_cycle' => 'monthly',
            'trial_days' => 0,
            'created_days_ago' => 95,
            'governorates' => ['HI', 'HM'],
            'zones' => [['HI', 'الوعر'], ['HI', 'الإنشاءات'], ['HI', 'عكرمة'], ['HI', 'الحمراء'], ['HI', 'الغوطة'], ['HM', 'الحاضر'], ['HM', 'الصابونية'], ['HM', 'البعث']],
            'activities' => ['سوبر ماركت', 'بقالة', 'مطعم', 'محل حلويات'],
            'manager' => ['+963900003001', 'فراس بردى', 'manager@barada-foods.sy'],
            'staff' => [
                ['+963900003002', 'نور مديرة الكتالوج', 'catalog@barada-foods.sy', 'catalog_manager'],
                ['+963900003003', 'طارق المحاسب', 'finance@barada-foods.sy', 'accountant'],
            ],
            'warehouses' => [['مستودع بردى - حمص', 34.7300, 36.7100], ['مستودع بردى - حماة', 35.1320, 36.7500]],
            'reps' => [
                ['+963935000021', 'وسيم الحمصي', 'سوبر ماركت'],
                ['+963935000022', 'زياد الحموي', 'بقالة'],
                ['+963935000023', 'هيثم عبود', 'مطعم'],
            ],
            'retailers' => [
                ['+963936000021', 'أبو محمود', 'سوبر ماركت الوعر', 'سوبر ماركت', 'HI', 'الوعر', 34.7380, 36.6800, 'الوعر - الشارع العام'],
                ['+963936000022', 'رنا الأحمد', 'بقالة الإنشاءات', 'بقالة', 'HI', 'الإنشاءات', 34.7200, 36.7200, 'الإنشاءات - شارع الجامعة'],
                ['+963936000023', 'أبو علي', 'مطعم عكرمة', 'مطعم', 'HI', 'عكرمة', 34.7100, 36.7300, 'عكرمة - الدوار'],
                ['+963936000024', 'ميرنا خوري', 'حلويات الحمراء', 'محل حلويات', 'HI', 'الحمراء', 34.7350, 36.7150, 'الحمراء - شارع الحضارة'],
                ['+963936000025', 'أبو ياسر', 'ماركت الحاضر', 'سوبر ماركت', 'HM', 'الحاضر', 35.1330, 36.7550, 'الحاضر - الشارع العام'],
                ['+963936000026', 'سمر العلي', 'بقالة الصابونية', 'بقالة', 'HM', 'الصابونية', 35.1400, 36.7600, 'الصابونية - دوار الصابونية'],
            ],
            'catalog' => true,
            'orders_per_day' => 5,
            'events' => [
                ['active', 94, 'provisioning completed'],
                ['suspended', 40, 'فاتورة المنصة متأخرة 15 يوماً'],
                ['active', 33, 'تمت تسوية الفاتورة المتأخرة'],
            ],
            'job' => ['status' => 'complete', 'steps' => ['limits', 'warehouse', 'invite', 'activate'], 'attempts' => 1, 'error' => null],
            'notes' => [
                [95, 'موزع حمص وحماة — يطلب فواتير ورقية'],
                [40, 'أُوقفت لتأخر السداد؛ التواصل مع المدير المالي'],
                [33, 'سُدّدت الفاتورة ورُفع الإيقاف'],
            ],
            'override' => null,
        ],
        'qasioun-supply' => [
            'name' => 'قاسيون للتوريدات',
            'legal_name' => 'شركة قاسيون للتوريدات الغذائية',
            'legal_form' => 'partnership',
            'cr_number' => 'CR-LA-410233',
            'tax_number' => '041-1023300',
            'phone' => '+963911000501',
            'email' => 'info@qasioun-supply.sy',
            'plan' => 'starter',
            'billing_cycle' => 'monthly',
            'trial_days' => 0,
            'created_days_ago' => 70,
            'governorates' => ['LA', 'TA'],
            'zones' => [['LA', 'الزراعة'], ['LA', 'الأزهري'], ['LA', 'الرمل الشمالي'], ['LA', 'مشروع الصليبة'], ['TA', 'الشيخ صالح'], ['TA', 'حي الثورة']],
            'activities' => ['سوبر ماركت', 'بقالة', 'كافيه'],
            'manager' => ['+963900004001', 'غسان اللاذقاني', 'manager@qasioun-supply.sy'],
            'staff' => [],
            'warehouses' => [['مستودع قاسيون - اللاذقية', 35.5230, 35.7920]],
            'reps' => [
                ['+963935000031', 'كنان صالح', 'سوبر ماركت'],
                ['+963935000032', 'علاء بركات', 'بقالة'],
            ],
            'retailers' => [
                ['+963936000031', 'أبو رامي', 'سوبر ماركت الزراعة', 'سوبر ماركت', 'LA', 'الزراعة', 35.5300, 35.7850, 'الزراعة - شارع بغداد'],
                ['+963936000032', 'هالة سليمان', 'بقالة الأزهري', 'بقالة', 'LA', 'الأزهري', 35.5350, 35.7900, 'الأزهري - الشارع الرئيسي'],
                ['+963936000033', 'أبو علاء', 'كافيه الرمل', 'كافيه', 'LA', 'الرمل الشمالي', 35.5150, 35.7800, 'الرمل الشمالي - الكورنيش'],
                ['+963936000034', 'جمانة ديب', 'ماركت الثورة', 'سوبر ماركت', 'TA', 'حي الثورة', 34.8900, 35.8850, 'طرطوس - حي الثورة'],
            ],
            'catalog' => true,
            'orders_per_day' => 3,
            'events' => [
                ['active', 69, 'provisioning completed'],
                ['suspended', 9, 'شكاوى متكررة من التجار — قيد المراجعة'],
            ],
            'job' => ['status' => 'complete', 'steps' => ['limits', 'warehouse', 'invite', 'activate'], 'attempts' => 1, 'error' => null],
            'notes' => [
                [70, 'موزع الساحل — بدأ بمستودع واحد'],
                [9, 'ثلاث شكاوى تسليم في أسبوع؛ أُوقفت القناة حتى الرد'],
            ],
            'override' => null,
        ],
        'orontes-dist' => [
            'name' => 'العاصي للتوزيع',
            'legal_name' => 'شركة العاصي للتوزيع',
            'legal_form' => 'llc',
            'cr_number' => 'CR-HM-150077',
            'tax_number' => '033-1500770',
            'phone' => '+963911000601',
            'email' => 'info@orontes-dist.sy',
            'plan' => 'starter',
            'billing_cycle' => 'monthly',
            'trial_days' => 0,
            'created_days_ago' => 200,
            'governorates' => ['HM', 'ID'],
            'zones' => [['HM', 'الجراجمة'], ['HM', 'الأربعين'], ['ID', 'إدلب المدينة']],
            'activities' => ['بقالة'],
            'manager' => ['+963900005001', 'أيمن العاصي', 'manager@orontes-dist.sy'],
            'staff' => [],
            'warehouses' => [['مستودع العاصي', 35.1400, 36.7400]],
            'reps' => [],
            'retailers' => [],
            'catalog' => false,
            'orders_per_day' => 0,
            'events' => [
                ['active', 199, 'provisioning completed'],
                ['suspended', 100, 'توقف النشاط منذ شهرين'],
                ['archived', 80, 'أُغلقت القناة بطلب المالك'],
            ],
            'job' => ['status' => 'complete', 'steps' => ['limits', 'warehouse', 'invite', 'activate'], 'attempts' => 1, 'error' => null],
            'notes' => [[80, 'أُرشفت — البيانات محفوظة للمراجعة الضريبية']],
            'override' => null,
        ],
        'coast-supply' => [
            'name' => 'الساحل للتوريدات',
            'legal_name' => 'شركة الساحل للتوريدات م.م',
            'legal_form' => 'llc',
            'cr_number' => 'CR-LA-520190',
            'tax_number' => '041-5201900',
            'phone' => '+963911000701',
            'email' => 'ops@coast-supply.sy',
            'plan' => 'growth',
            'billing_cycle' => 'yearly',
            'trial_days' => 14,
            'created_days_ago' => 2,
            'governorates' => ['LA', 'TA'],
            'zones' => [['LA', 'الشيخ ضاهر'], ['LA', 'جبلة'], ['TA', 'بانياس'], ['TA', 'صافيتا']],
            'activities' => ['سوبر ماركت', 'بقالة', 'مطعم'],
            'manager' => ['+963900006001', 'سامر الساحلي', 'manager@coast-supply.sy'],
            'staff' => [],
            'warehouses' => [['الساحل للتوريدات', 35.5200, 35.7900]],
            'reps' => [],
            'retailers' => [],
            'catalog' => false,
            'orders_per_day' => 0,
            'events' => [],
            'job' => ['status' => 'failed', 'steps' => ['limits', 'warehouse'], 'attempts' => 2, 'error' => 'invite: WhatsApp gateway timed out after 30s'],
            'notes' => [[2, 'فشلت دعوة المدير مرتين — تحقق من رقم واتساب قبل إعادة المحاولة']],
            'override' => null,
        ],
        'new-horizon' => [
            'name' => 'الأفق الجديد للتوزيع',
            'legal_name' => 'مؤسسة الأفق الجديد',
            'legal_form' => 'sole',
            'cr_number' => 'CR-DR-610042',
            'tax_number' => '015-6100420',
            'phone' => '+963911000801',
            'email' => 'info@newhorizon.sy',
            'plan' => 'starter',
            'billing_cycle' => 'monthly',
            'trial_days' => 30,
            'created_days_ago' => 0,
            'created_hours_ago' => 3,
            'governorates' => ['DR', 'SU'],
            'zones' => [['DR', 'درعا المحطة'], ['DR', 'درعا البلد'], ['SU', 'السويداء المدينة']],
            'activities' => ['بقالة', 'سوبر ماركت'],
            'manager' => ['+963900007001', 'خالد الحوراني', 'manager@newhorizon.sy'],
            'staff' => [],
            'warehouses' => [],
            'reps' => [],
            'retailers' => [],
            'catalog' => false,
            'orders_per_day' => 0,
            'events' => [],
            'job' => ['status' => 'queued', 'steps' => [], 'attempts' => 0, 'error' => null],
            'notes' => [],
            'override' => null,
        ],
    ];

    private PlatformUser $admin;

    private ChannelLifecycle $lifecycle;

    public function run(): void
    {
        $this->admin = PlatformUser::query()->where('email', 'admin@platform.sy')->firstOrFail();
        $this->lifecycle = app(ChannelLifecycle::class);

        foreach (self::CHANNELS as $slug => $spec) {
            $this->seedChannel($slug, $spec);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function seedChannel(string $slug, array $spec): void
    {
        // A channel created N days ago was created mid-morning; one created N hours ago
        // keeps the wall clock, so it never lands in the future.
        $createdAt = isset($spec['created_hours_ago'])
            ? Carbon::now('Asia/Damascus')->subHours($spec['created_hours_ago'])
            : Carbon::now('Asia/Damascus')->subDays($spec['created_days_ago'])->setTime(10, 15);

        $plan = ChannelPlan::query()->where('key', $spec['plan'])->firstOrFail();

        $channel = SupplyChannel::query()->where('slug', $slug)->first()
            ?? $this->at($createdAt, fn (): SupplyChannel => SupplyChannel::query()->create([
                'name' => $spec['name'],
                'slug' => $slug,
                'legal_name' => $spec['legal_name'],
                'tax_number' => $spec['tax_number'],
                'phone' => $spec['phone'],
                'email' => $spec['email'],
                'legal_form' => $spec['legal_form'],
                'cr_number' => $spec['cr_number'],
                'plan_id' => $plan->id,
                'billing_cycle' => $spec['billing_cycle'],
                'trial_ends_at' => $spec['trial_days'] > 0 ? $createdAt->copy()->addDays($spec['trial_days']) : null,
                'custom_discount' => 0,
                'settings' => [
                    'locale' => 'ar',
                    'timezone' => 'Asia/Damascus',
                    'currency' => 'SYP',
                    'order_cutoff_time' => '14:00',
                    'delivery_window_days' => 2,
                    'support_phone' => $spec['phone'],
                ],
            ]));

        $channelId = (int) $channel->id;

        Tenant::as($channelId, function () use ($channel, $channelId, $slug, $spec, $plan, $createdAt): void {
            $this->seedShape($channelId, $spec, $plan);
            $this->seedProvisionJob($channelId, $slug, $spec, $plan, $createdAt);
            $this->seedTransitions($channel, $spec);

            // Only a channel that finished provisioning has a manager: the invite is the
            // provisioning step that creates one, and these two never got there.
            if ($channel->fresh()->status !== ChannelStatus::Provisioning) {
                $this->seedPeople($channelId, $spec);
            }

            if ($spec['catalog']) {
                $this->call(DemoCatalogSeeder::class);
                app(DemoPricingSeeder::class)->seedBasePrices();
            }

            $this->seedNotes($channel, $spec);
            $this->seedOverride($channel, $spec);

            if ($spec['orders_per_day'] > 0) {
                $this->call(DemoOrderHistorySeeder::class, false, ['perDay' => $spec['orders_per_day']]);
            }
        });
    }

    /**
     * Limits, coverage, activity types and warehouses — the rows `CreateChannel` and
     * `RunProvisioning` write. Idempotent on their natural keys.
     *
     * @param  array<string, mixed>  $spec
     */
    private function seedShape(int $channelId, array $spec, ChannelPlan $plan): void
    {
        ChannelLimit::query()->firstOrCreate(
            ['channel_id' => $channelId],
            ['channel_id' => $channelId] + $plan->limits,
        );

        foreach ($spec['governorates'] as $code) {
            ChannelGovernorate::query()->firstOrCreate([
                'channel_id' => $channelId,
                'governorate_id' => $this->governorateId($code),
            ]);
        }

        foreach ($spec['activities'] as $name) {
            ChannelActivityType::query()->firstOrCreate([
                'channel_id' => $channelId,
                'activity_type_id' => $this->activityTypeId($name),
            ]);
        }

        foreach ($spec['zones'] as [$governorate, $zone]) {
            ChannelZone::query()->firstOrCreate(
                ['zone_id' => $this->zoneId($governorate, $zone)],
                [
                    'delivery_days' => self::COVERAGE_DAYS,
                    'delivery_fee' => self::COVERAGE_FEE,
                    'min_order_value' => self::COVERAGE_MIN,
                ],
            );
        }

        foreach ($spec['warehouses'] as [$name, $lat, $lng]) {
            Warehouse::query()->firstOrCreate(
                ['name' => $name],
                ['status' => WarehouseStatus::Active, 'lat' => $lat, 'lng' => $lng],
            );
        }
    }

    /**
     * The job EP-AD-051 would have queued, in the state the spec says it reached. The
     * payload is the create body, so EP-AD-053 can retry the failed one for real.
     *
     * @param  array<string, mixed>  $spec
     */
    private function seedProvisionJob(int $channelId, string $slug, array $spec, ChannelPlan $plan, Carbon $createdAt): void
    {
        $payload = [
            'name' => $spec['name'],
            'slug' => $slug,
            'legal_form' => $spec['legal_form'],
            'cr_number' => $spec['cr_number'],
            'governorate_ids' => array_map(fn (string $code): int => $this->governorateId($code), $spec['governorates']),
            'zone_ids' => array_map(fn (array $pair): int => $this->zoneId($pair[0], $pair[1]), $spec['zones']),
            'activity_type_ids' => array_map(fn (string $name): int => $this->activityTypeId($name), $spec['activities']),
            'plan_id' => (int) $plan->id,
            'billing_cycle' => $spec['billing_cycle'],
            'trial_days' => $spec['trial_days'],
            'limits' => $plan->limits,
            'manager' => [
                'name' => $spec['manager'][1],
                'phone' => $spec['manager'][0],
                'email' => $spec['manager'][2],
                'invite_via' => 'whatsapp',
            ],
        ];

        $job = $spec['job'];
        $finished = $job['status'] === 'queued' ? null : $createdAt->copy()->addMinutes(2 * max(1, $job['attempts']));

        $this->at($createdAt, fn () => ChannelProvisionJob::query()->firstOrCreate(
            ['public_id' => 'job_prov_demo_'.str_replace('-', '', $slug)],
            [
                'channel_id' => $channelId,
                'status' => $job['status'],
                'payload' => $payload,
                'completed_steps' => $job['steps'],
                'error' => $job['error'],
                'attempts' => $job['attempts'],
                'started_at' => $job['status'] === 'queued' ? null : $createdAt->copy()->addSeconds(30),
                'finished_at' => $finished,
            ],
        ));
    }

    /**
     * Every move through `ChannelLifecycle`, with the platform admin as the actor and the
     * clock set to the day it happened, so the timeline and `channel_events` read as a
     * real history. `provisioned_at` is stamped on the first move to active, as
     * `RunProvisioning` does. Written once: a channel that has left `provisioning`
     * already has its trail, and replaying it would be a second, illegal history.
     *
     * @param  array<string, mixed>  $spec
     */
    private function seedTransitions(SupplyChannel $channel, array $spec): void
    {
        if ($channel->fresh()->status !== ChannelStatus::Provisioning) {
            return;
        }

        foreach ($spec['events'] as [$to, $daysAgo, $reason]) {
            $at = Carbon::now('Asia/Damascus')->subDays($daysAgo)->setTime(11, 0);
            $status = ChannelStatus::from($to);

            $this->at($at, function () use ($channel, $status, $reason): void {
                $fresh = $channel->fresh();
                $this->lifecycle->transition($fresh, $status, $this->admin, $reason);

                if ($status === ChannelStatus::Active && $fresh->provisioned_at === null) {
                    $fresh->provisioned_at = now();
                    $fresh->save();
                }
            });
        }
    }

    /**
     * Manager and staff on the channel guard, reps and retailers on the app guard. Reps
     * split the coverage between them so assignment has somewhere to route.
     *
     * @param  array<string, mixed>  $spec
     */
    private function seedPeople(int $channelId, array $spec): void
    {
        // Roles are seeded on team 0 by RolesPermissionsSeeder; syncRoles must look there.
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $users = [[...$spec['manager'], 'channel_manager'], ...$spec['staff']];
        foreach ($users as [$phone, $name, $email, $role]) {
            $user = ChannelUser::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $name, 'email' => $email, 'status' => UserStatus::Active],
            );
            ChannelUserChannel::query()->firstOrCreate(
                ['channel_user_id' => $user->id, 'channel_id' => $channelId],
                ['is_default' => true],
            );
            $user->syncRoles([$role]);
        }

        $zoneIds = $this->coveredZoneIds();
        foreach ($spec['reps'] as $i => [$phone, $name, $activity]) {
            $user = AppUser::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $name, 'kind' => AppUserKind::Rep, 'status' => UserStatus::Active],
            );
            $profile = RepProfile::query()->firstOrCreate(
                ['app_user_id' => $user->id],
                [
                    'channel_id' => $channelId,
                    'activity_type_id' => $this->activityTypeId($activity),
                    'status' => ProfileStatus::Active,
                ],
            );
            foreach ($zoneIds as $j => $zoneId) {
                if ($j % count($spec['reps']) === $i) {
                    RepProfileZone::query()->firstOrCreate(['rep_profile_id' => $profile->id, 'zone_id' => $zoneId]);
                }
            }
        }

        foreach ($spec['retailers'] as [$phone, $owner, $shop, $activity, $governorate, $zone, $lat, $lng, $address]) {
            $user = AppUser::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $owner, 'kind' => AppUserKind::Retailer, 'status' => UserStatus::Active],
            );
            $zoneId = $this->zoneId($governorate, $zone);
            RetailerProfile::query()->firstOrCreate(
                ['app_user_id' => $user->id],
                [
                    'shop_name' => $shop,
                    'activity_type_id' => $this->activityTypeId($activity),
                    'governorate_id' => (int) Zone::query()->findOrFail($zoneId)->governorate_id,
                    'zone_id' => $zoneId,
                    'lat' => $lat,
                    'lng' => $lng,
                    'address' => $address,
                    'status' => ProfileStatus::Active,
                ],
            );
        }
    }

    /**
     * The first note is the one the create form carries; every later one goes through
     * `UpdateChannel` so it lands with its `channel.update` audit row, as it would from
     * the back office. Notes are append-only, so a body already on the channel is the
     * mark that it was written.
     *
     * @param  array<string, mixed>  $spec
     */
    private function seedNotes(SupplyChannel $channel, array $spec): void
    {
        foreach ($spec['notes'] as $i => [$daysAgo, $body]) {
            if (ChannelInternalNote::query()->where('body', $body)->exists()) {
                continue;
            }

            $at = Carbon::now('Asia/Damascus')->subDays($daysAgo)->setTime(12, 30);

            $this->at($at, function () use ($channel, $body, $i): void {
                if ($i === 0) {
                    ChannelInternalNote::query()->create([
                        'channel_id' => $channel->id,
                        'body' => $body,
                        'actor_type' => PlatformUser::class,
                        'actor_id' => $this->admin->id,
                        'created_at' => now(),
                    ]);

                    return;
                }

                app(UpdateChannel::class)($channel->fresh(), ['internal_note' => $body, 'reason' => 'ملاحظة داخلية'], $this->admin);
            });
        }
    }

    /**
     * A temporary limit override through the real action (BE-T12), so the detail shows
     * an override beside the base and the audit log shows who raised it and why.
     *
     * @param  array<string, mixed>  $spec
     */
    private function seedOverride(SupplyChannel $channel, array $spec): void
    {
        $override = $spec['override'];
        if ($override === null || ChannelLimit::query()->whereNotNull('overridden_at')->exists()) {
            return;
        }

        $at = Carbon::now('Asia/Damascus')->subDays($override['days_ago'])->setTime(9, 45);

        $this->at($at, fn () => app(OverrideChannelLimits::class)($channel->fresh(), [
            'limits' => $override['limits'],
            'temporary_until' => now()->addDays($override['days'])->toIso8601String(),
            'reason' => $override['reason'],
        ], $this->admin));
    }

    private function governorateId(string $code): int
    {
        return (int) Governorate::query()->where('code', $code)->firstOrFail()->id;
    }

    /**
     * Runs `$callback` with the clock set to `$when`, so `now()` inside the lifecycle,
     * the actions and the timestamps reads as that moment. Always restored.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(Carbon $when, callable $callback): mixed
    {
        Carbon::setTestNow($when);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
