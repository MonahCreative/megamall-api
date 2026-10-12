<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    /** GET /api/store — store name, phone, WhatsApp, ticker messages, payment channels. */
    public function store(): JsonResponse
    {
        $s = Setting::allValues();

        return response()->json([
            'name' => $s['store_name'] ?? config('app.name'),
            'tagline' => $s['tagline'] ?? null,
            'phone' => $s['phone'] ?? null,
            'whatsapp' => $s['whatsapp'] ?? null,
            'email' => $s['email'] ?? null,
            'ticker' => $s['ticker'] ?? [],
            'payment_channels' => collect(config('payments.channels'))
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    /** GET /api/categories — top-level categories with their sub-categories and product counts. */
    public function categories(): JsonResponse
    {
        $categories = Category::active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->active()->withCount(['products' => fn ($p) => $p->active()])])
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'image' => $c->imageUrl(),
                'product_count' => $c->products_count + $c->children->sum('products_count'),
                'children' => $c->children->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'product_count' => $child->products_count,
                ]),
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
        ]);

        $query = Product::active()->with(['mainImage', 'category', 'variants']);

        if ($slug = $request->string('category')->toString()) {
            $category = Category::active()->where('slug', $slug)->firstOrFail();
            $ids = $category->children()->pluck('id')->push($category->id);
            $query->whereIn('category_id', $ids);
        }

        if ($term = $request->string('q')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%"));
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
                    'price' => $b->product->price,
                    'compare_at_price' => $b->product->compare_at_price,
                    'discount_percent' => $b->product->discountPercent(),
                    'in_stock' => $b->product->stock > 0,
                    'has_video' => filled($b->product->video_url),
                ] : null,
            ]);

        return response()->json(['data' => $banners]);
    }
}
