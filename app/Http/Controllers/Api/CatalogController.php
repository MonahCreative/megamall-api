<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Product;
use App\Models\ProductSection;
use App\Models\Setting;
use App\Services\BackInStockNotifier;
use App\Services\PricingService;
use App\Services\SectionService;
use App\Support\LipaNamba;
use App\Support\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    /** GET /api/store — store name, phone, WhatsApp, colours and how upcountry orders pay (Lipa Namba). */
    public function store(): JsonResponse
    {
        $s = Setting::allValues();

        return response()->json([
            'name' => $s['store_name'] ?? config('app.name'),
            'tagline' => $s['tagline'] ?? null,
            'phone' => $s['phone'] ?? null,
            'whatsapp' => $s['whatsapp'] ?? null,
            'email' => $s['email'] ?? null,
            'return_window_days' => (int) ($s['return_window_days'] ?? 7),
            'theme' => collect(Theme::colours())->mapWithKeys(fn ($colour, $key) => [str_replace('theme_', '', $key) => $colour]),
            'payment_channels' => collect(config('payments.channels'))
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            // Upcountry orders are paid to the shop's Lipa Namba and checked by staff.
            'payment_method' => LipaNamba::METHOD,
            'lipa_numbers' => LipaNamba::accounts(),
            'lipa_note' => LipaNamba::note(),
        ]);
    }

    /** GET /api/categories — visible categories in menu order, with how many visible products each has. */
    public function categories(): JsonResponse
    {
        $categories = Category::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'product_count' => $c->products_count,
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * GET /api/products?category=fashion&q=shirt&sort=newest|price_asc|price_desc|popular|discount&featured=1&page=2
     */
    public function products(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'category' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,popular,discount'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
            'ids' => ['nullable', 'string', 'max:1000'], // "12,5,40": saved items / recently viewed
        ]);

        $query = Product::active()->with(['mainImage', 'category', 'variants']);

        if ($slug = $request->string('category')->toString()) {
            $category = Category::active()->where('slug', $slug)->firstOrFail();
            $query->where('category_id', $category->id);
        }

        if ($term = $request->string('q')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%"));
        }

        if ($request->filled('ids')) {
            $ids = array_slice(array_filter(array_map('intval', explode(',', $request->string('ids')->toString()))), 0, 60);
            $query->whereIn('id', $ids ?: [0]);
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        match ($request->input('sort', 'newest')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('sold_count'),
            'discount' => $query->whereNotNull('compare_at_price')
                ->orderByRaw('(compare_at_price - price) * 1.0 / compare_at_price DESC'),
            default => $query->latest('id'),
        };

        return ProductResource::collection(
            $query->paginate($request->integer('per_page', 24))->withQueryString()
        );
    }

    /** POST /api/products/{slug}/notify {phone, variant_id?} — "Tell me when it's back in stock" (staff contact them). */
    public function notifyWhenBack(Request $request, string $slug, BackInStockNotifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $product = Product::active()->where('slug', $slug)->firstOrFail();
        $notifier->request($product, $data['phone'], $data['variant_id'] ?? null);

        return response()->json(['message' => 'Done! We will contact you on this number as soon as it is back in stock.'], 201);
    }

    /** GET /api/sitemap — every public address, for the shop's sitemap.xml (Google). */
    public function sitemap(): JsonResponse
    {
        return response()->json([
            'products' => Product::active()->orderBy('id')->get(['slug', 'updated_at'])
                ->map(fn (Product $product) => ['slug' => $product->slug, 'updated_at' => $product->updated_at?->toIso8601String()]),
            'categories' => Category::active()->orderBy('sort_order')->get(['slug', 'updated_at'])
                ->map(fn (Category $category) => ['slug' => $category->slug, 'updated_at' => $category->updated_at?->toIso8601String()]),
            'pages' => ContentPage::published()->orderBy('sort_order')->get(['slug', 'updated_at'])
                ->map(fn (ContentPage $page) => ['slug' => $page->slug, 'updated_at' => $page->updated_at?->toIso8601String()]),
        ]);
    }

    /** GET /api/products/{slug} — full product page data plus related products. */
    public function product(string $slug): JsonResponse
    {
        $product = Product::active()
            ->where('slug', $slug)
            ->with(['images', 'mainImage', 'category', 'variants'])
            ->firstOrFail();

        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with(['mainImage', 'category', 'variants'])
            ->inRandomOrder()
            ->limit(8)
            ->get();


        return response()->json([
            'data' => ProductResource::make($product),
            'related' => ProductResource::collection($related),
        ]);
    }

    /** GET /api/sections — the product rows on the home page, in order, with their products. */
    public function sections(SectionService $sections): JsonResponse
    {
        $data = ProductSection::active()
            ->with(['category', 'products'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'source' => $section->source,
                'link' => match ($section->source) {
                    'category' => $section->category ? '/category/'.$section->category->slug : null,
                    'discounted' => '/products?sort=discount',
                    'best_selling' => '/products?sort=popular',
                    default => '/products',
                },
                'products' => ProductResource::collection($sections->products($section)),
            ])
            ->filter(fn (array $section) => $section['products']->count() > 0)
            ->values();

        return response()->json(['data' => $data]);
    }

    /** GET /api/banners — hero slider. */
    public function banners(): JsonResponse
    {
        $banners = Banner::active()
            ->with(['product.mainImage', 'product.variants'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'badge' => $b->badge,
                'image' => $b->imageUrl() ?? $b->product?->mainImage?->url(),
                'button_text' => $b->button_text ?? 'Shop now',
                'link' => $b->link,
                'product' => $b->product ? [
                    'slug' => $b->product->slug,
                    ...collect(app(PricingService::class)->display($b->product))->only(['price', 'compare_at_price', 'discount_percent', 'promotion'])->all(),
                    'in_stock' => $b->product->totalStock() > 0,
                    'has_video' => filled($b->product->video_url),
                ] : null,
            ]);

        return response()->json(['data' => $banners]);
    }
}
