<?php
/** Storefront views – port of the public views in cloth_app/views.py */

final class Home
{
    public function get(Request $r): Response
    {
        $data = single_templates_data($r);
        $data['category'] = Product_category::objects();
        $targets = ['T-Shirt', 'T-Shirts Collection', 'Shirt', 'Shirts Collection', 'Caps', 'Personalized Caps & Headwea', 'Bag', 'Custom Corporate Bags', 'Towels'];
        $cats = Product_category::filter(['name__in' => $targets]);
        $custom = Product::filter(['category__in' => $cats->values_list('id')])->slice(0, 8);
        if (!$custom->exists()) $custom = Product::filter(['product_status' => 'Publish'])->slice(0, 8);
        $data['custom_Apparel'] = $custom;

        $cms = $data['cms'];
        $n = (int)($cms->section4_slider_mobile_item_count ?: 4);
        if ($n < 1 || $n > 4) $n = 4;
        $featured = Product::filter(['product_status' => 'Publish'])->order_by('-id')->slice(0, 16)->get();
        if (!$featured) $featured = Product::objects()->order_by('-id')->slice(0, 16)->get();
        $data['featured_products'] = $featured;
        $data['featured_products_chunks'] = array_chunk($featured, $n);
        $data['corporate_Bags'] = Product::filter(['category__name__icontains' => 'Bag'])->slice(0, 8);
        $data['testimonials'] = Testimoninal::objects();
        return render($r, 'index.html', $data);
    }
}

final class About_us
{
    public function get(Request $r): Response
    {
        return render($r, 'about-us.html', [
            't-shirt' => Product::filter(['category' => 1])->order_by('-id')->slice(0, 8),
            'lowers' => Product::filter(['category' => 2])->order_by('-id')->slice(0, 8),
            'shirts' => Product::filter(['category' => 3])->order_by('-id')->slice(0, 8),
        ]);
    }
}

final class ContactUs
{
    public function get(Request $r): Response { return render($r, 'contact.html'); }
    public function post(Request $r): Response
    {
        $name = $r->post('name'); $email = $r->post('email'); $phone = $r->post('phone_number'); $message = $r->post('message');
        if ($name && $email && $phone) {
            Contact_US::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'message' => (string)$message]);
            msg_success('Your request has been submitted successfully. We will connect with you soon.');
        } else {
            msg_error('All fields are required.');
        }
        return redirect('/contact-us');
    }
}

final class Shop
{
    public function get(Request $r): Response
    {
        $min = Product::objects()->aggregate('minf', 'b2b_price');
        $max = Product::objects()->aggregate('maxf', 'b2b_price');
        $search = $r->get('searchbar');
        $categories = $r->get('categories');

        $subCounts = [];
        foreach (DB::all('SELECT sub_category_id AS s, COUNT(*) AS c FROM cloth_app_product WHERE sub_category_id IS NOT NULL GROUP BY sub_category_id') as $row) $subCounts[$row['s']] = (int)$row['c'];
        $cats = array_map(function ($s) use ($subCounts) { $s->extra['product_count'] = $subCounts[$s->pk()] ?? 0; return $s; }, Product_sub_category::objects()->get());
        $catCounts = [];
        foreach (DB::all('SELECT category_id AS s, COUNT(*) AS c FROM cloth_app_product_sub_category WHERE category_id IS NOT NULL GROUP BY category_id') as $row) $catCounts[$row['s']] = (int)$row['c'];
        $mainCats = array_map(function ($c) use ($catCounts) { $c->extra['sub_count'] = $catCounts[$c->pk()] ?? 0; return $c; }, Product_category::objects()->get());

        if ($categories) $products = Product::filter(['sub_category__category_slug' => $categories]);
        elseif ($search) $products = Product::filter(['product_name__icontains' => $search]);
        else $products = Product::objects()->order_by('-id');

        return render($r, 'shop.html', [
            'products_all' => annotate_reviews($products),
            'inner_products_all' => Color_product::objects(),
            'sub_ct' => $cats,
            'categories_main' => $mainCats,
            'breade_clum_cat' => $categories ? Product_sub_category::filter(['category_slug' => $categories])->first() : null,
            'min_price' => $min !== null ? (float)$min : 0,
            'max_price' => $max !== null ? (float)$max : 10000,
        ]);
    }
}

final class Product_details
{
    private static function cleanSizes(QuerySet $q): array
    {
        return array_values(array_filter($q->get(), function ($s) {
            $v = (string)$s->raw('size');
            return !in_array($v, ['1', '0', 'on', 'true', 'false', ''], true) && !preg_match('/^\d+$/', $v);
        }));
    }

    public function get(Request $r, string $slug): Response
    {
        $innerGallery = [];
        $productColor = null;
        $innerMain = null;
        $sizes = null;
        $reviews = User_Reviews::objects()->none();

        if (trim($slug) === '' || in_array(trim($slug), ['None', 'null'], true)) return redirect('/shop');

        $product = Product::filter(['uuid_product' => $slug])->first();
        $inner = null;
        if (!$product) $inner = Color_product::filter(['uuid_product' => $slug])->first();
        if (!$product && !$inner) $product = Product::filter(['product_slug' => $slug])->first();
        if (!$product && !$inner && ctype_digit($slug)) $product = Product::find((int)$slug);
        if (!$product && !$inner) return redirect('/shop');

        $target = $inner ? $inner->product : $product;
        if ($product) {
            $reviews = User_Reviews::filter(['product' => $product->pk(), 'show' => true]);
            $sizes = self::cleanSizes(Size_price_par_post::filter(['product' => $product->pk()]));
        } elseif ($inner) {
            $reviews = User_Reviews::filter(['inner_product_rew' => $inner->pk(), 'show' => true]);
            $sizes = self::cleanSizes(Size_price_par_post::filter(['product' => $inner->raw('product_id')]));
        }
        if ($target && !$sizes) {
            foreach (['S', 'M', 'L', 'XL', 'XXL'] as $s) Size_price_par_post::get_or_create(['product' => $target->pk(), 'size' => $s]);
            $sizes = self::cleanSizes(Size_price_par_post::filter(['product' => $target->pk()]));
        }

        $gallery = [];
        if ($product) {
            $gallery = product_Gelery_images::filter(['product' => $product->pk()]);
        } elseif ($inner) {
            $innerMain = $inner->product;
            foreach (color_product_Gelery_images::filter(['color_product' => $inner->pk()]) as $g) $innerGallery[] = $g->image->url();
        }

        if ($product) $productColor = Color_product::filter(['product' => $product->pk()]);
        elseif ($inner) $productColor = Color_product::filter(['product' => $inner->raw('product_id')]);

        $colorId = $r->get('color-id-filter');
        if ($colorId) {
            $innerGallery = [];
            $inner = Color_product::find($colorId);
            if ($inner) foreach (color_product_Gelery_images::filter(['color_product' => $inner->pk()]) as $g) $innerGallery[] = $g->image->url();
        }

        $countReview = $reviews->count();
        $total = 0.0;
        foreach ($reviews as $rv) if (is_numeric($rv->raw('star'))) $total += (float)$rv->raw('star');
        $avg = $countReview > 0 ? Filters::pyRound($total / $countReview, 1) : 0.0;
        $bars = [];
        $tr = $countReview > 0 ? $countReview : 1;
        foreach ([5, 4, 3, 2, 1] as $s) {
            $cnt = $reviews->filter(['star' => (string)$s])->count();
            $bars[] = ['star' => $s, 'count' => $cnt, 'percent' => (int)Filters::pyRound($cnt / $tr * 100)];
        }

        // Recently viewed (session)
        $recent = [];
        $innerRecent = [];
        if ($product) {
            $rv = array_values(array_filter($_SESSION['recently_viewed'] ?? [], fn($x) => $x != $product->pk()));
            array_unshift($rv, $product->pk());
            $rv = array_slice($rv, 0, 10);
            $_SESSION['recently_viewed'] = $rv;
            $found = [];
            foreach (Product::filter(['id__in' => $rv])->exclude(['id' => $product->pk()]) as $p) $found[$p->pk()] = $p;
            foreach ($rv as $id) if (isset($found[$id])) $recent[] = $found[$id];
        }
        if ($inner) {
            $irv = array_values(array_filter($_SESSION['innerrecently_viewed'] ?? [], fn($x) => $x != $inner->pk()));
            array_unshift($irv, $inner->pk());
            $irv = array_slice($irv, 0, 10);
            $_SESSION['innerrecently_viewed'] = $irv;
            $found = [];
            foreach (Color_product::filter(['id__in' => $irv])->exclude(['id' => $inner->pk()]) as $p) $found[$p->pk()] = $p;
            foreach ($irv as $id) if (isset($found[$id])) $innerRecent[] = $found[$id];
        }

        return render($r, 'product-details.html', [
            'product' => $product,
            'active_product' => $inner ? $inner->product : $product,
            'inner_product' => $inner,
            'inner_singal_main_product' => $innerMain,
            'gelery_images' => $gallery,
            'gallery_image_list' => $innerGallery,
            'product_color' => $productColor,
            'product_innner_size' => $sizes,
            'reviews' => $reviews,
            'avg_rating' => $avg,
            'count_review' => $countReview,
            'rating_bars' => $bars,
            'related_product' => Product::objects(),
            'aplus' => A_plus_listing::objects(),
            'color_id_set_active' => $colorId,
            'recently_viewed' => $recent,
            'innerrecently_viewed_products' => $innerRecent,
        ]);
    }

    public function post(Request $r, string $slug): Response
    {
        $u = Auth::user();
        $name = $r->post('r_name'); $email = $r->post('r_email'); $review = $r->post('review'); $rate = $r->post('rating');
        if ($name && $email && $review) {
            $data = ['name' => $name, 'email' => $email, 'msg' => $review, 'star' => (string)$rate];
            if ($u instanceof CustomUser) $data['user'] = $u->pk();
            if ($r->post('inner_product_id')) $data['inner_product_rew'] = Color_product::find($r->post('inner_product_id'))?->pk();
            else $data['product'] = Product::find($r->post('product_id'))?->pk();
            User_Reviews::create($data);
            msg_success($u instanceof CustomUser ? 'Thank you for review' : 'Thank you for reviewd');
        }
        return redirect($r->path);
    }
}

final class B2b
{
    public function get(Request $r): Response
    {
        $p = new Paginator(Product::filter(['product_status' => 'Publish']), 10);
        return render($r, 'b2b.html', [
            'category' => Product_category::objects(),
            'all_products_b2b' => $p->get_page($r->get('page')),
            'colors' => Color_product::objects(),
        ]);
    }
}

final class Blogs_post
{
    public function get(Request $r): Response
    {
        $q = Blog_post::objects()->order_by('-date');
        $cat = $r->get('category');
        if ($cat) $q = $q->filter(['category__ct_slug' => $cat]);
        $search = $r->get('q');
        if ($search) $q = $q->filter(['meta_title__icontains' => $search]);
        return render($r, 'blog.html', [
            'all_blog' => (new Paginator($q, 10))->get_page($r->get('page')),
            'b_cat' => Blog_Category::objects(),
            'recent_blogs' => Blog_post::objects()->order_by('-date')->slice(0, 5),
            'selected_category' => $cat,
            'search_query' => $search ?: '',
        ]);
    }
}

final class blogs_details
{
    public function get(Request $r, string $slug): Response
    {
        return render($r, 'blogs.html', [
            'blog_details' => Blog_post::filter(['slug' => $slug])->first(),
            'b_cat' => Blog_Category::objects(),
            'recent_blogs' => Blog_post::objects()->order_by('-date')->slice(0, 5),
        ]);
    }
}

final class CategoryB2b
{
    public function get(Request $r): Response
    {
        $c = $r->get('categories');
        $products = $c ? Product::filter(['product_status' => 'Publish', 'category__uuid_product' => $c]) : Product::objects()->none();
        $page = (new Paginator($products, 10))->get_page($r->get('page'));
        return render($r, 'b2b_category_flt.html', [
            'products_all' => $page,
            'breade_clum_cat' => $c ? Product_category::filter(['uuid_product' => $c])->first() : null,
            'page_obj' => $page,
        ]);
    }
}

final class Collections_for_men
{
    public function get(Request $r, string $slug): Response
    {
        $products = annotate_reviews(Product::filter(['sub_category__category_slug' => $slug])->order_by('-id'));
        $data = single_templates_data($r);
        $data['products_all'] = $products;
        $data['page_obj'] = (new Paginator($products, 12))->get_page($r->get('page'));
        $data['inner_products_all'] = color_product_Gelery_images::objects();
        $data['sub_cat_'] = Product_sub_category::filter(['category_slug' => $slug])->first();
        return render($r, 'colections/alltype_tshirts.html', $data);
    }
}

final class Tshirtcategory { public function get(Request $r): Response { return render($r, 'colections/category_mens_t_shoirt.html'); } }

final class StaticPage
{
    public static function make(string $tpl): Closure
    {
        return function (Request $r) use ($tpl) {
            if (!is_file(BASE_PATH . '/templates/' . $tpl)) throw new Http404();
            return $r->method === 'GET' || $r->method === 'HEAD' ? render($r, $tpl) : http('Method Not Allowed', 405);
        };
    }
}

final class Chat_boat
{
    public function post(Request $r): Response
    {
        $name = $r->post('name'); $email = $r->post('email'); $num = $r->post('number');
        if ($name && $email && $num) {
            Chatbot_users::create(['name' => $name, 'email' => $email, 'phone' => $num]);
            return json(['status' => 200]);
        }
        return json(['status' => 400], 400);
    }
}

function get_cart_data(Request $r): Response
{
    return render($r, 'partials/cart_items.html', single_templates_data_for_add_to_cart($r));
}

function product_feed_xml(Request $r): Response
{
    $x = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>'
        . '<title>Us Vesta Products</title><link>https://www.usvesta.com/</link><description>Product Feed</description>';
    foreach (Product::objects() as $p) {
        $desc = strip_tags(html_entity_decode((string)$p->raw('product_description')));
        $img = $p->product_image->name !== '' ? $r->build_absolute_uri($p->product_image->url()) : '';
        $xml .= '<item><g:id>' . $p->pk() . '</g:id><g:title>' . $x($p->raw('product_name')) . '</g:title>'
            . '<g:description>' . $x($desc) . '</g:description><g:link>' . $x($r->build_absolute_uri('/products/' . $p->raw('uuid_product'))) . '</g:link>'
            . '<g:image_link>' . $x($img) . '</g:image_link><g:price>' . $x($p->b2c_price) . ' INR</g:price>'
            . '<g:condition>new</g:condition><g:availability>in stock</g:availability></item>';
    }
    $xml .= '</channel></rss>';
    return http($xml, 200, 'application/xml');
}

function llms_txt(Request $r): Response
{
    return new Response(engine()->render('llms.txt', context_processors($r)), 200, 'text/plain; charset=utf-8');
}

function sitemap_xml(Request $r): Response
{
    $urls = [];
    $add = function (string $loc, ?DateTimeInterface $lastmod, string $freq, string $prio) use (&$urls, $r) {
        $urls[] = '<url><loc>' . htmlspecialchars($r->build_absolute_uri($loc), ENT_XML1) . '</loc>'
            . ($lastmod ? '<lastmod>' . $lastmod->format('Y-m-d') . '</lastmod>' : '')
            . "<changefreq>$freq</changefreq><priority>$prio</priority></url>";
    };
    foreach (['/', '/shop', '/3d-custom-design', '/blog', '/for-corporates', '/contact-us', '/t-shirts-for-men', '/our-story',
                 '/terms-&-conditionst', '/cookies', '/payment-policy', '/cancellation-policy', '/return-&-refund-policy',
                 '/shipping-&-delivery-policy', '/privacy-policy'] as $u) $add($u, null, 'daily', '1.0');
    foreach (Product::filter(['product_status' => 'Publish'])->exclude(['uuid_product__isnull' => true])->exclude(['uuid_product__in' => ['', 'None']]) as $p) $add($p->get_absolute_url(), $p->date, 'daily', '0.8');
    foreach (Blog_post::objects() as $b) $add('/blogs/' . $b->raw('slug'), $b->date, 'daily', '0.9');
    foreach (Product_sub_category::objects() as $s) $add('/collections/' . $s->raw('category_slug') . '/', $s->date, 'daily', '0.9');
    foreach (Product_category::objects() as $c) $add($c->get_absolute_url(), $c->date, 'daily', '0.9');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode("\n", $urls) . '</urlset>';
    return http($xml, 200, 'application/xml');
}
