<?php
/** Port of cloth_app/search_htmlbar.py (global template context) + shared view helpers. */

/** Lazily evaluated list (keeps context processors cheap when a template doesn't use a value). */
final class LazyList implements IteratorAggregate, Countable, ArrayAccess
{
    private ?array $items = null;
    public function __construct(private Closure $fn) {}
    public function items(): array { return $this->items ??= array_values(($this->fn)()); }
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->items()); }
    public function count(): int { return count($this->items()); }
    public function offsetExists(mixed $o): bool { return isset($this->items()[$o]); }
    public function offsetGet(mixed $o): mixed { return $this->items()[$o] ?? null; }
    public function offsetSet(mixed $o, mixed $v): void {}
    public function offsetUnset(mixed $o): void {}
    public function tplGet(string $k): mixed { return ctype_digit($k) ? ($this->items()[(int)$k] ?? Undefined::get()) : Undefined::get(); }
}

/**
 * Django: .annotate(avg_rating=Avg("user_reviews__star"), review_count=Count("user_reviews"))
 * @param iterable<Product> $products
 * @return Product[]
 */
function annotate_reviews(iterable $products): array
{
    $list = $products instanceof QuerySet ? $products->get() : (is_array($products) ? $products : iterator_to_array($products));
    if (!$list) return [];
    $ids = array_map(fn($p) => $p->pk(), $list);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::all("SELECT product_id, AVG(CAST(star AS DECIMAL(6,2))) AS a, COUNT(*) AS c FROM cloth_app_user_reviews WHERE product_id IN ($ph) GROUP BY product_id", $ids);
    $map = [];
    foreach ($rows as $r) $map[$r['product_id']] = $r;
    foreach ($list as $p) {
        $r = $map[$p->pk()] ?? null;
        $p->extra['avg_rating'] = $r ? (float)$r['a'] : null;
        $p->extra['review_count'] = $r ? (int)$r['c'] : 0;
    }
    return $list;
}

function single_templates_data(Request $req): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cms = HomePageEditor::current();
    $coord = sub_category_coordinator::objects()->order_by('-id');
    $categorys = Product_category::objects()->order_by('-id');
    $autocomplete = Product::objects()->order_by('id');
    return $cache = [
        'courdinators' => $coord,
        'courdinators_pages' => $coord,
        'cms' => $cms,
        'homepage_cms' => $cms,
        'products_all_index_page' => new LazyList(function () {
            $p = Product::filter(['top_eight_products' => false])->order_by('-id');
            if (!$p->exists()) $p = Product::objects()->order_by('-id');
            return $p->slice(0, 8)->get();
        }),
        'search_data' => $autocomplete,
        'products_all_index_page_top' => new LazyList(fn() => annotate_reviews(Product::filter(['top_eight_products' => true])->order_by('-id'))),
        'categorys' => $categorys->slice(0, 4),
        'nev_cat' => $categorys,
        'sub_category' => Product_sub_category::objects(),
        'banners' => Banners::objects(),
        'user' => Auth::user(),
        'product_sh' => $autocomplete,
        'testimonial' => Testimoninal::objects()->order_by('-id'),
        'trusted_brans' => Our_trusted_client::objects()->order_by('-id'),
        'appid' => config('app_id'),
        'appkey' => config('app_key'),
        'current_url' => $req->build_absolute_uri(),
        'insta_images' => Insta_images::objects()->order_by('-id'),
    ];
}

function single_templates_data_for_add_to_cart(Request $req): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $user = Auth::user();
    $cart = [];
    $cartInner = [];
    $sessCart = $_SESSION['anonymous_cart'] ?? [];
    $sessInner = $_SESSION['anonymous_cart_inner'] ?? [];

    if ($user instanceof CustomUser) {
        foreach ($sessInner as $item) {
            $b = Color_product::find($item['product_id'] ?? null);
            if ($b) {
                [$c] = Cart::get_or_create(['user' => $user->pk(), 'inner_products_card' => $b->pk()]);
                if (!empty($item['size'])) { $c->size = $item['size']; $c->save(); }
            }
        }
        $_SESSION['anonymous_cart_inner'] = [];
        $cartInner = Cart::filter(['user' => $user->pk(), 'inner_products_card__isnull' => false])->get();

        foreach ($sessCart as $item) {
            $b = Product::find($item['product_id'] ?? null);
            if ($b) {
                [$c] = Cart::get_or_create(['user' => $user->pk(), 'product' => $b->pk()]);
                if (!empty($item['size'])) { $c->size = $item['size']; $c->save(); }
            }
        }
        $_SESSION['anonymous_cart'] = [];
        $cart = Cart::filter(['user' => $user->pk(), 'product__isnull' => false])->get();
    } else {
        foreach ($sessInner as $item) {
            $p = Color_product::find($item['product_id'] ?? null);
            if ($p) { $p->extra['size'] = $item['size'] ?? null; $cartInner[] = $p; }
        }
        foreach ($sessCart as $item) {
            $p = Product::find($item['product_id'] ?? null);
            if ($p) { $p->extra['size'] = $item['size'] ?? null; $cart[] = $p; }
        }
    }
    return $cache = ['shopping_cart' => $cart, 'shopping_cart_inner' => $cartInner];
}

/** Guard for every dashboard endpoint (Django only guarded the main dashboard). */
function require_superuser(): void
{
    $u = Auth::user();
    if (!($u instanceof CustomUser) || !$u->is_superuser) throw new Http404();
}

/** Strip Froala editor attribution watermark. */
function clean_froala(?string $txt): ?string
{
    if ($txt === null || $txt === '') return $txt;
    $pats = [
        '/<p[^>]*data-f-id=["\']pbf["\'][^>]*>[\s\S]*?<\/p>/i',
        '/<p[^>]*>[\s\S]*?Powered by\s*<a[^>]*froala\.com[^>]*>[\s\S]*?<\/a>[\s\S]*?<\/p>/i',
        '/Powered by\s*<a[^>]*froala\.com[^>]*>[\s\S]*?<\/a>/i',
        '/<a[^>]*froala\.com[^>]*>[\s\S]*?<\/a>/i',
    ];
    foreach ($pats as $p) $txt = preg_replace($p, '', $txt);
    return trim($txt);
}

function blank(mixed $v): bool { return $v === null || trim((string)$v) === '' || in_array(trim((string)$v), ['None', 'null'], true); }
