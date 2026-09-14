<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\BrandActivityType;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\CategoryActivityType;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductActivityType;
use Modules\Catalog\Domain\Models\ProductSliderTag;
use Modules\Catalog\Domain\Models\ProductSpec;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Catalog\Domain\Models\ProductVariantAxis;
use Modules\Catalog\Domain\Models\ProductVariantAxisValue;
use Modules\Catalog\Domain\Models\ProductZone;

/**
 * Brands, the category tree and a working FMCG catalog for the demo channel.
 *
 * Every product is attached to every covered zone and every activity type, because
 * `VisibleCatalogQuery` hides a product from a retailer whose zone or activity type it
 * is not attached to — a catalog with no pivot rows is a catalog no retailer can see.
 */
final class DemoCatalogSeeder extends DemoSeeder
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private const BRANDS = [
        ['الدرة', 'Al Durra', 'active'],
        ['قاسيون', 'Qasioun', 'active'],
        ['كاتاكيت', 'Katakit', 'active'],
        ['نستله', 'Nestlé', 'active'],
        ['بيبسي', 'Pepsi', 'active'],
        ['كوكاكولا', 'Coca-Cola', 'active'],
        ['أريال', 'Ariel', 'active'],
        ['سيغنال', 'Signal', 'active'],
        ['بانتين', 'Pantene', 'active'],
        ['علامة موقوفة', 'Retired Brand', 'disabled'],
    ];

    /**
     * Root category → channel categories, each optionally with its own children.
     * Channel categories directly under a root are level 2 (`CreateCategory` gives the
     * root itself level 1), and their children level 3.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const CATEGORIES = [
        'مواد غذائية' => [
            'أرز وسكر' => [],
            'زيوت' => [],
            'معلبات' => ['معجون طماطم', 'تونة وسردين'],
            'مكرونة ومعجنات' => [],
            'شاي وقهوة' => [],
        ],
        'مشروبات' => [
            'مشروبات غازية' => [],
            'عصائر' => [],
            'مياه' => [],
        ],
        'ألبان وأجبان' => [
            'أجبان' => [],
            'ألبان' => [],
            'حليب' => [],
        ],
        'منظفات' => [
            'مساحيق غسيل' => [],
            'منظفات أرضيات' => [],
        ],
        'عناية شخصية' => [
            'عناية بالفم' => [],
            'عناية بالشعر' => [],
        ],
        'حلويات ومقرمشات' => [
            'شوكولاتة' => [],
            'بسكويت' => [],
            'شيبس' => [],
        ],
    ];

    /**
     * @var list<array{sku: string, ar: string, en: string, brand: string, category: string, barcode: string, unit: string, min: int, multiple: int, weight: int|null, status: string, tags: list<string>, specs?: array<string, string>, sliders?: list<string>}>
     */
    private const PRODUCTS = [
        ['sku' => 'SUG-1KG', 'ar' => 'سكر أبيض 1 كغ', 'en' => 'White Sugar 1kg', 'brand' => 'الدرة', 'category' => 'أرز وسكر', 'barcode' => '6210000000011', 'unit' => 'قطعة', 'min' => 10, 'multiple' => 10, 'weight' => 1000, 'status' => 'active', 'tags' => ['أساسي', 'الأكثر مبيعاً'], 'specs' => ['المنشأ' => 'سوريا', 'الصلاحية' => '24 شهر'], 'sliders' => ['best_sellers']],
        ['sku' => 'RICE-5KG', 'ar' => 'أرز مصري 5 كغ', 'en' => 'Egyptian Rice 5kg', 'brand' => 'قاسيون', 'category' => 'أرز وسكر', 'barcode' => '6210000000028', 'unit' => 'شوال', 'min' => 2, 'multiple' => 1, 'weight' => 5000, 'status' => 'active', 'tags' => ['أساسي'], 'specs' => ['المنشأ' => 'مصر', 'الصلاحية' => '18 شهر']],
        ['sku' => 'OIL-1L', 'ar' => 'زيت دوار الشمس 1 ل', 'en' => 'Sunflower Oil 1L', 'brand' => 'الدرة', 'category' => 'زيوت', 'barcode' => '6210000000035', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 950, 'status' => 'active', 'tags' => ['أساسي', 'الأكثر مبيعاً'], 'sliders' => ['best_sellers']],
        ['sku' => 'OIL-4L', 'ar' => 'زيت نباتي 4 ل', 'en' => 'Vegetable Oil 4L', 'brand' => 'الدرة', 'category' => 'زيوت', 'barcode' => '6210000000042', 'unit' => 'قطعة', 'min' => 2, 'multiple' => 1, 'weight' => 3700, 'status' => 'active', 'tags' => ['أساسي']],
        ['sku' => 'TOM-400', 'ar' => 'معجون طماطم 400 غ', 'en' => 'Tomato Paste 400g', 'brand' => 'الدرة', 'category' => 'معجون طماطم', 'barcode' => '6210000000059', 'unit' => 'عبوة', 'min' => 12, 'multiple' => 12, 'weight' => 420, 'status' => 'active', 'tags' => []],
        ['sku' => 'TUNA-160', 'ar' => 'تونة قطع 160 غ', 'en' => 'Tuna Chunks 160g', 'brand' => 'قاسيون', 'category' => 'تونة وسردين', 'barcode' => '6210000000066', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 170, 'status' => 'active', 'tags' => [], 'specs' => ['المنشأ' => 'تايلاند', 'الصلاحية' => '36 شهر']],
        ['sku' => 'PASTA-500', 'ar' => 'معكرونة سباغيتي 500 غ', 'en' => 'Spaghetti 500g', 'brand' => 'قاسيون', 'category' => 'مكرونة ومعجنات', 'barcode' => '6210000000073', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 500, 'status' => 'active', 'tags' => ['أساسي']],
        ['sku' => 'TEA-500', 'ar' => 'شاي أسود 500 غ', 'en' => 'Black Tea 500g', 'brand' => 'قاسيون', 'category' => 'شاي وقهوة', 'barcode' => '6210000000080', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 500, 'status' => 'active', 'tags' => ['الأكثر مبيعاً'], 'specs' => ['المنشأ' => 'سريلانكا']],
        ['sku' => 'NESC-200', 'ar' => 'نسكافيه كلاسيك 200 غ', 'en' => 'Nescafé Classic 200g', 'brand' => 'نستله', 'category' => 'شاي وقهوة', 'barcode' => '6210000000097', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 220, 'status' => 'active', 'tags' => [], 'specs' => ['المنشأ' => 'تركيا', 'الصلاحية' => '18 شهر'], 'sliders' => ['featured']],
        ['sku' => 'COLA-330', 'ar' => 'كوكاكولا 330 مل', 'en' => 'Coca-Cola 330ml', 'brand' => 'كوكاكولا', 'category' => 'مشروبات غازية', 'barcode' => '6210000000103', 'unit' => 'قطعة', 'min' => 24, 'multiple' => 24, 'weight' => 350, 'status' => 'active', 'tags' => ['الأكثر مبيعاً'], 'sliders' => ['best_sellers']],
        ['sku' => 'PEPSI-1500', 'ar' => 'بيبسي 1.5 ل', 'en' => 'Pepsi 1.5L', 'brand' => 'بيبسي', 'category' => 'مشروبات غازية', 'barcode' => '6210000000110', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 1550, 'status' => 'active', 'tags' => []],
        ['sku' => 'JUICE-ORG-1L', 'ar' => 'عصير برتقال 1 ل', 'en' => 'Orange Juice 1L', 'brand' => 'الدرة', 'category' => 'عصائر', 'barcode' => '6210000000127', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 1050, 'status' => 'active', 'tags' => []],
        ['sku' => 'WATER-500', 'ar' => 'مياه معدنية 500 مل (12 عبوة)', 'en' => 'Mineral Water 500ml x12', 'brand' => 'قاسيون', 'category' => 'مياه', 'barcode' => '6210000000134', 'unit' => 'كرتونة', 'min' => 5, 'multiple' => 5, 'weight' => 6200, 'status' => 'active', 'tags' => ['أساسي']],
        ['sku' => 'CHZ-500', 'ar' => 'جبنة بيضاء 500 غ', 'en' => 'White Cheese 500g', 'brand' => 'الدرة', 'category' => 'أجبان', 'barcode' => '6210000000141', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 520, 'status' => 'active', 'tags' => ['مبرد'], 'specs' => ['التخزين' => 'مبرد 2-6 درجات', 'الصلاحية' => '45 يوم']],
        ['sku' => 'YOG-1KG', 'ar' => 'لبن رائب 1 كغ', 'en' => 'Yogurt 1kg', 'brand' => 'الدرة', 'category' => 'ألبان', 'barcode' => '6210000000158', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 1020, 'status' => 'active', 'tags' => ['مبرد']],
        ['sku' => 'LAB-500', 'ar' => 'لبنة 500 غ', 'en' => 'Labneh 500g', 'brand' => 'الدرة', 'category' => 'ألبان', 'barcode' => '6210000000165', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => 520, 'status' => 'active', 'tags' => ['مبرد']],
        ['sku' => 'MILK-1L', 'ar' => 'حليب طويل الأمد 1 ل', 'en' => 'UHT Milk 1L', 'brand' => 'نستله', 'category' => 'حليب', 'barcode' => '6210000000172', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 1030, 'status' => 'active', 'tags' => ['أساسي']],
        ['sku' => 'DET-3KG', 'ar' => 'مسحوق غسيل 3 كغ', 'en' => 'Laundry Powder 3kg', 'brand' => 'أريال', 'category' => 'مساحيق غسيل', 'barcode' => '6210000000189', 'unit' => 'قطعة', 'min' => 4, 'multiple' => 4, 'weight' => 3050, 'status' => 'active', 'tags' => []],
        ['sku' => 'FLC-1L', 'ar' => 'منظف أرضيات 1 ل', 'en' => 'Floor Cleaner 1L', 'brand' => 'قاسيون', 'category' => 'منظفات أرضيات', 'barcode' => '6210000000196', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 1080, 'status' => 'active', 'tags' => []],
        ['sku' => 'TP-100', 'ar' => 'معجون أسنان 100 مل', 'en' => 'Toothpaste 100ml', 'brand' => 'سيغنال', 'category' => 'عناية بالفم', 'barcode' => '6210000000202', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 130, 'status' => 'active', 'tags' => []],
        ['sku' => 'SHP', 'ar' => 'شامبو بانتين', 'en' => 'Pantene Shampoo', 'brand' => 'بانتين', 'category' => 'عناية بالشعر', 'barcode' => '6210000000219', 'unit' => 'قطعة', 'min' => 6, 'multiple' => 6, 'weight' => null, 'status' => 'active', 'tags' => []],
        ['sku' => 'CHOC-40', 'ar' => 'كيت كات 40 غ', 'en' => 'KitKat 40g', 'brand' => 'نستله', 'category' => 'شوكولاتة', 'barcode' => '6210000000226', 'unit' => 'قطعة', 'min' => 24, 'multiple' => 24, 'weight' => 42, 'status' => 'active', 'tags' => ['الأكثر مبيعاً'], 'sliders' => ['best_sellers']],
        ['sku' => 'BIS-100', 'ar' => 'بسكويت شاي 100 غ', 'en' => 'Tea Biscuits 100g', 'brand' => 'كاتاكيت', 'category' => 'بسكويت', 'barcode' => '6210000000233', 'unit' => 'قطعة', 'min' => 24, 'multiple' => 24, 'weight' => 105, 'status' => 'active', 'tags' => []],
        ['sku' => 'CHIPS-50', 'ar' => 'شيبس ذرة 50 غ', 'en' => 'Corn Chips 50g', 'brand' => 'كاتاكيت', 'category' => 'شيبس', 'barcode' => '6210000000240', 'unit' => 'قطعة', 'min' => 24, 'multiple' => 24, 'weight' => 55, 'status' => 'active', 'tags' => []],
        ['sku' => 'JUICE-MNG-1L', 'ar' => 'عصير مانجو 1 ل', 'en' => 'Mango Juice 1L', 'brand' => 'الدرة', 'category' => 'عصائر', 'barcode' => '6210000000257', 'unit' => 'قطعة', 'min' => 12, 'multiple' => 12, 'weight' => 1050, 'status' => 'draft', 'tags' => ['جديد']],
        ['sku' => 'SNK-NEW', 'ar' => 'مقرمشات بالجبنة 80 غ', 'en' => 'Cheese Crackers 80g', 'brand' => 'كاتاكيت', 'category' => 'شيبس', 'barcode' => '6210000000264', 'unit' => 'قطعة', 'min' => 24, 'multiple' => 24, 'weight' => 85, 'status' => 'draft', 'tags' => ['جديد']],
        ['sku' => 'OLD-COLA-250', 'ar' => 'كوكاكولا 250 مل (زجاج)', 'en' => 'Coca-Cola 250ml Glass', 'brand' => 'كوكاكولا', 'category' => 'مشروبات غازية', 'barcode' => '6210000000271', 'unit' => 'قطعة', 'min' => 24, 'multiple' => 24, 'weight' => 400, 'status' => 'disabled', 'tags' => []],
    ];

    /**
     * One product with a size axis, so the variant path has data. SKUs follow
     * `GenerateVariants`: base SKU, a dash, the value with whitespace removed.
     *
     * @var array{sku: string, axis: string, values: list<string>}
     */
    private const VARIANTS = ['sku' => 'SHP', 'axis' => 'الحجم', 'values' => ['200 مل', '400 مل']];

    public function run(): void
    {
        $this->seedBrands();
        $this->seedCategories();
        $this->seedProducts();
        $this->seedVariants();
    }

    private function seedBrands(): void
    {
        $activityTypeIds = $this->activityTypeIds();

        foreach (self::BRANDS as $i => [$ar, $en, $status]) {
            $brand = Brand::query()->firstOrCreate(
                ['name_ar' => $ar],
                ['name_en' => $en, 'order' => $i + 1, 'status' => BrandStatus::from($status)],
            );

            foreach ($activityTypeIds as $activityTypeId) {
                BrandActivityType::query()->firstOrCreate([
                    'brand_id' => $brand->id,
                    'activity_type_id' => $activityTypeId,
                ]);
            }
        }
    }

    private function seedCategories(): void
    {
        $activityTypeIds = $this->activityTypeIds();

        foreach (self::CATEGORIES as $rootName => $children) {
            $rootId = $this->rootCategoryId($rootName);
            $order = 0;

            foreach ($children as $name => $grandchildren) {
                $parent = Category::query()->firstOrCreate(
                    ['name' => $name, 'root_category_id' => $rootId, 'parent_id' => null],
                    ['level' => 2, 'order' => ++$order, 'status' => CategoryStatus::Active],
                );
                $this->attachCategoryActivityTypes($parent, $activityTypeIds);

                foreach ($grandchildren as $j => $childName) {
                    $child = Category::query()->firstOrCreate(
                        ['name' => $childName, 'root_category_id' => $rootId, 'parent_id' => $parent->id],
                        ['level' => 3, 'order' => $j + 1, 'status' => CategoryStatus::Active],
                    );
                    $this->attachCategoryActivityTypes($child, $activityTypeIds);
                }
            }
        }
    }

    /**
     * @param  list<int>  $activityTypeIds
     */
    private function attachCategoryActivityTypes(Category $category, array $activityTypeIds): void
    {
        foreach ($activityTypeIds as $activityTypeId) {
            CategoryActivityType::query()->firstOrCreate([
                'category_id' => $category->id,
                'activity_type_id' => $activityTypeId,
            ]);
        }
    }

    private function seedProducts(): void
    {
        $zoneIds = $this->coveredZoneIds();
        $activityTypeIds = $this->activityTypeIds();

        foreach (self::PRODUCTS as $i => $row) {
            $product = Product::query()->firstOrCreate(
                ['sku' => $row['sku']],
                [
                    'name_ar' => $row['ar'],
                    'name_en' => $row['en'],
                    'brand_id' => Brand::query()->where('name_ar', $row['brand'])->firstOrFail()->id,
                    'category_id' => Category::query()->where('name', $row['category'])->firstOrFail()->id,
                    'barcode' => $row['barcode'],
                    'status' => ProductStatus::from($row['status']),
                    'short_description' => $row['ar'].' - '.$row['en'],
                    'sale_unit_id' => $this->saleUnitId($row['unit']),
                    'min_order_qty' => $row['min'],
                    'order_multiple' => $row['multiple'],
                    'weight_gram' => $row['weight'],
                    'tracked' => true,
                    'reorder_point' => $row['min'] * 5,
                    'allow_backorder' => false,
                    'lead_time_days' => 2,
                    'priority' => count(self::PRODUCTS) - $i,
                    'tags' => $row['tags'],
                ],
            );

            foreach ($zoneIds as $zoneId) {
                ProductZone::query()->firstOrCreate(['product_id' => $product->id, 'zone_id' => $zoneId]);
            }

            foreach ($activityTypeIds as $activityTypeId) {
                ProductActivityType::query()->firstOrCreate([
                    'product_id' => $product->id,
                    'activity_type_id' => $activityTypeId,
                ]);
            }

            $order = 0;
            foreach ($row['specs'] ?? [] as $key => $value) {
                ProductSpec::query()->firstOrCreate(
                    ['product_id' => $product->id, 'key' => $key],
                    ['value' => $value, 'order' => $order++],
                );
            }

            foreach ($row['sliders'] ?? [] as $key) {
                ProductSliderTag::query()->firstOrCreate(['product_id' => $product->id, 'slider_key' => $key]);
            }
        }
    }

    private function seedVariants(): void
    {
        $product = Product::query()->where('sku', self::VARIANTS['sku'])->firstOrFail();

        $axis = ProductVariantAxis::query()->firstOrCreate(
            ['product_id' => $product->id, 'name' => self::VARIANTS['axis']],
            ['order' => 0],
        );

        foreach (self::VARIANTS['values'] as $j => $value) {
            ProductVariantAxisValue::query()->firstOrCreate(
                ['axis_id' => $axis->id, 'value' => $value],
                ['order' => $j],
            );

            $sku = $product->sku.'-'.preg_replace('/\s+/', '', $value);

            ProductVariant::query()->firstOrCreate(
                ['product_id' => $product->id, 'sku' => $sku],
                [
                    'barcode' => $product->barcode.'-'.($j + 1),
                    'combination' => [self::VARIANTS['axis'] => $value],
                    'status' => 'active',
                ],
            );
        }
    }
}
