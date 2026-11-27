<?php
/** Admin dashboard – port of Dahsboardview, Update_product, update_dashboard_form_etc_data, color_django_verint, ordermodel_popup, Upload_pd_in_bulk, Web_ouwner */

final class Web_ouwner
{
    public function get(Request $r): Response { return redirect('/usvesta-deshboard/'); }
    public function post(Request $r): Response
    {
        require_superuser();
        $id = $r->post('o_id'); $st = $r->post('o_status');
        if (!$id || !$st) return http('Invalid Order Id or Status');
        if ($o = Order::find($id)) { $o->status = $st; $o->save(); }
        return redirect('/customer-orders');
    }
}

function to_bool_str(mixed $v): bool { return is_string($v) ? strtolower($v) === 'true' : (bool)$v; }

/** naive "YYYY-MM-DD[THH:MM]" in Asia/Kolkata -> UTC "Y-m-d H:i:s" */
function local_to_utc(?string $s): string
{
    if (blank($s)) return now_utc_str();
    try { return (new DateTimeImmutable($s, app_tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    catch (Throwable) { return now_utc_str(); }
}

final class Dahsboardview
{
    public function get(Request $r): Response
    {
        require_superuser();
        $u = Auth::user();
        $valid = Order::objects()->exclude(['status' => 'Pending']);
        $rev = Order::filter(['payment_status' => 'paid'])->aggregate('sum', 'total_price');
        if (!(float)$rev) $rev = Order::objects()->aggregate('sum', 'total_price');
        $nProducts = Product::objects()->count();
        $avg = (float)(Product::objects()->aggregate('sum', 'b2c_price') ?? 0) / max($nProducts, 1);

        $year = (int)now_local()->format('Y');
        $mOrders = array_fill(0, 12, 0);
        $mSales = array_fill(0, 12, 0.0);
        foreach (DB::all("SELECT created_at, total_price FROM cloth_app_order WHERE (status IS NULL OR status <> 'Pending')") as $row) {
            $d = to_local(new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')));
            if ((int)$d->format('Y') !== $year) continue;
            $m = (int)$d->format('n') - 1;
            $mOrders[$m]++;
            $mSales[$m] += (float)$row['total_price'];
        }
        $merchant = Merchant::filter(['user' => $u->pk()])->first();

        return render($r, 'dashboard/dashboard.html', [
            'category' => Product_category::objects()->order_by('id'),
            'all_orders' => $valid->order_by('-id'),
            'products_all' => Product::objects()->order_by('-id'),
            'coordinator' => sub_category_coordinator::objects()->order_by('id'),
            'reviews' => User_Reviews::objects()->order_by('-id'),
            'color_productskkx' => Color_product::objects(),
            'sub_cat' => Product_sub_category::objects(),
            'banners' => Banners::objects()->order_by('-id'),
            'testimonials' => Testimoninal::objects()->order_by('-id'),
            'trusted_clients' => Our_trusted_client::objects()->order_by('-id'),
            'insta_images' => Insta_images::objects()->order_by('-id'),
            'aplus_listings' => A_plus_listing::objects()->order_by('-id'),
            'blog_categories' => Blog_Category::objects()->order_by('-id'),
            'blog_posts' => Blog_post::objects()->order_by('-id'),
            'contact_messages' => Contact_US::objects()->order_by('-id'),
            'abandoned_checkouts' => Leave_checkout_page_billing_details::objects()->order_by('-id'),
            'chatbot_leads' => Chatbot_users::objects()->order_by('-id'),
            'api_keys' => $merchant ? APIKey::filter(['merchant' => $merchant->pk()])->order_by('-created_at') : APIKey::objects()->order_by('-created_at'),
            'return_requests' => Return_and_Cancel_products::objects()->order_by('-id'),
            'customers' => CustomUser::objects()->order_by('-id'),
            'merchants' => Merchant::objects()->order_by('-created_at'),
            'total_orders' => $valid->count(),
            'total_revenue' => (float)($rev ?? 0),
            'total_customers' => CustomUser::filter(['is_superuser' => false])->count(),
            'total_products' => $nProducts,
            'avg_price' => $avg,
            'monthly_orders_json' => json_encode($mOrders),
            'monthly_sales_json' => json_encode($mSales),
        ]);
    }

    public function post(Request $r): Response
    {
        require_superuser();
        $u = Auth::user();
        $p = fn($k, $d = null) => $r->post($k, $d);

        /* ---------------- homepage CMS */
        if ($p('update_homepage_cms')) {
            $cms = HomePageEditor::current();
            $text = ['header_announcement_text', 'announcement_bg_color', 'announcement_text_color', 'announcement_font_size',
                'logo_width', 'logo_height', 'logo_link', 'header_bg_color', 'header_text_color', 'mobile_header_bg_color', 'mobile_header_text_color',
                'express_shipping_title', 'express_shipping_subtitle',
                'hero_subtitle', 'hero_subtitle_font_size', 'hero_subtitle_color', 'hero_title', 'hero_title_font_size', 'hero_title_color',
                'hero_title_font_family', 'hero_description', 'hero_description_font_size', 'hero_button_text', 'hero_button_link',
                'hero_button_bg_color', 'hero_button_text_color', 'hero_overlay_opacity', 'section4_slider_title',
                'fit_section_badge', 'fit_card1_title', 'fit_card1_desc', 'fit_card2_title', 'fit_card2_desc', 'fit_section_bg_color',
                'grid_tile1_link', 'grid_tile2_link', 'grid_tile3_link', 'grid_tile4_link', 'grid_center_button_text', 'grid_center_button_link',
                'grid_center_button_bg', 'grid_center_button_color', 'footer_tagline', 'footer_facebook_url', 'footer_instagram_url',
                'footer_linkedin_url', 'footer_youtube_url', 'footer_twitter_url', 'footer_copyright', 'footer_bg_color'];
            for ($i = 1; $i <= 5; $i++) foreach (['title', 'price', 'desc', 'link'] as $k) $text[] = "section3_card{$i}_$k";
            foreach ($text as $k) if ($r->has($k)) $cms->$k = (string)$p($k);
            if ($r->has('announcement_enabled')) $cms->announcement_enabled = $p('announcement_enabled') === '1';
            foreach (['section2_enabled', 'section3_enabled', 'section3_desktop_enabled', 'section3_mobile_enabled', 'women_coming_soon_enabled',
                         'section4_enabled', 'section4_slider_enabled', 'section4_slider_desktop_enabled', 'section4_slider_mobile_enabled',
                         'section5_enabled', 'section6_enabled', 'enable_country_modal'] as $k) $cms->$k = $p($k) === '1';
            if ($r->has('section4_slider_mobile_item_count') && is_numeric($p('section4_slider_mobile_item_count'))) $cms->section4_slider_mobile_item_count = (int)$p('section4_slider_mobile_item_count');
            foreach (HomePageEditor::FLOAT_FIELDS as $k) if ($r->has($k) && is_numeric($p($k))) $cms->$k = (float)$p($k);
            foreach (HomePageEditor::IMAGE_FIELDS as $k) {
                if ($f = $r->file($k)) { $n = Storage::save($f, 'cms_images', IMG_CMS); if ($n) $cms->$k = $n; }
            }
            foreach (['header_nav_json', 'mobile_nav_json', 'mega_menu_cards_json', 'mega_menu_featured_links_json', 'footer_nav_json'] as $k) {
                if ($r->has($k)) { $d = json_decode((string)$p($k), true); if (json_last_error() === JSON_ERROR_NONE) $cms->$k = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
            }
            $cms->save();
            return json(['res' => 'Homepage CMS Editor updated successfully!']);
        }

        /* ---------------- merchants */
        if ($p('onboard_merchant')) {
            $name = $p('business_name'); $vpa = $p('merchant_vpa');
            if ($name && $vpa) {
                $m = Merchant::create(['business_name' => $name, 'merchant_vpa' => $vpa, 'daily_amount_limit' => (float)($p('daily_amount_limit') ?: 100000), 'status' => 'ACTIVE']);
                APIKey::generate_key_pair($m, 'TEST');
                APIKey::generate_key_pair($m, 'LIVE');
                msg_success("Merchant '$name' onboarded successfully!");
                return redirect('/usvesta-deshboard/#paymentgateway-section');
            }
        }
        if ($p('toggle_merchant_status')) {
            if ($m = Merchant::find($p('merchant_id'))) {
                $m->status = $m->raw('status') === 'ACTIVE' ? 'DISABLED' : 'ACTIVE';
                $m->save();
                msg_success('Merchant status changed to ' . $m->raw('status'));
                return redirect('/usvesta-deshboard/#paymentgateway-section');
            }
        }
        if ($p('delete_merchant')) {
            Merchant::filter(['id' => $p('merchant_id')])->delete();
            msg_success('Merchant deleted successfully');
            return redirect('/usvesta-deshboard/#paymentgateway-section');
        }

        /* ---------------- reviews toggle */
        if ($rid = $p('toggle_review_id')) {
            $rv = User_Reviews::find($rid);
            if (!$rv) return json(['error' => 'Review not found'], 404);
            $rv->show = !$rv->show;
            $rv->save();
            return json(['res' => 'Review status updated successfully', 'show' => (bool)$rv->show]);
        }

        /* ---------------- API keys */
        if ($p('create_api_key')) {
            $m = Merchant::filter(['user' => $u->pk()])->first() ?? Merchant::objects()->first() ?? Merchant::create(['user' => $u->pk()]);
            [$key, $raw] = APIKey::generate_key_pair($m, strtoupper((string)$p('mode', 'TEST')) === 'LIVE' ? 'LIVE' : 'TEST');
            return json(['res' => 'API Key Created Successfully', 'public_key' => $key->raw('public_key'), 'secret_key' => $raw]);
        }
        if ($kid = $p('delete_api_key')) {
            APIKey::filter(['id' => $kid])->delete();
            return json(['res' => 'API Key Revoked Successfully']);
        }

        /* ---------------- banners & content blocks */
        $act = $p('banner_action');
        if ($act === 'add') {
            Banners::create([
                'image' => ($f = $r->file('banner_image')) ? (Storage::save($f, 'media/banners_all', IMG_BANNER) ?? '') : '',
                'image_phone' => ($f = $r->file('banner_image_phone')) ? (Storage::save($f, 'media/phone-banners_all', IMG_BANNER_PHONE) ?? '') : '',
                'video' => ($f = $r->file('banner_video')) ? (Storage::save($f, 'media/banners_video_all') ?? '') : '',
                'image_alt_name' => (string)$p('banner_alt', ''),
                'category_images_slider' => $p('banner_sub_cat') ? Product_sub_category::find($p('banner_sub_cat'))?->pk() : null,
                'show_on_pages' => implode(',', $r->getlist('banner_pages')),
            ]);
            return json(['res' => 'Banner Added Successfully']);
        } elseif ($act === 'delete') {
            Banners::filter(['id' => $p('banner_id')])->delete();
            return json(['res' => 'Banner Deleted Successfully']);
        }

        $act = $p('testimonial_action');
        if ($act === 'add') {
            Testimoninal::create(['image' => ($f = $r->file('testimonial_image')) ? (Storage::save($f, 'media/testimonial', IMG_DEFAULT) ?? '') : '',
                'image_alt_name' => (string)$p('testimonial_alt', ''), 'name' => (string)$p('testimonial_name', ''),
                'company_name' => (string)$p('testimonial_company', ''), 'content' => (string)$p('testimonial_content', '')]);
            return json(['res' => 'Testimonial Added Successfully']);
        } elseif ($act === 'delete') {
            Testimoninal::filter(['id' => $p('testimonial_id')])->delete();
            return json(['res' => 'Testimonial Deleted Successfully']);
        }

        $act = $p('client_action');
        if ($act === 'add') {
            Our_trusted_client::create(['image' => ($f = $r->file('client_image')) ? (Storage::save($f, 'media/trustedclient', IMG_DEFAULT) ?? '') : '', 'image_alt_name' => (string)$p('client_alt', '')]);
            return json(['res' => 'Trusted Client Logo Added']);
        } elseif ($act === 'delete') {
            Our_trusted_client::filter(['id' => $p('client_id')])->delete();
            return json(['res' => 'Trusted Client Logo Deleted']);
        }

        $act = $p('insta_action');
        if ($act === 'add') {
            Insta_images::create(['image' => ($f = $r->file('insta_image')) ? (Storage::save($f, 'media/insta_images', IMG_DEFAULT) ?? '') : '']);
            return json(['res' => 'Instagram Image Added']);
        } elseif ($act === 'delete') {
            Insta_images::filter(['id' => $p('insta_id')])->delete();
            return json(['res' => 'Instagram Image Deleted']);
        }

        $act = $p('aplus_action');
        if ($act === 'add') {
            A_plus_listing::create(['image' => ($f = $r->file('aplus_image')) ? (Storage::save($f, 'media/apluslisting', IMG_DEFAULT) ?? '') : '', 'image_alt_name' => (string)$p('aplus_alt', '')]);
            return json(['res' => 'A+ Listing Image Added']);
        } elseif ($act === 'delete') {
            A_plus_listing::filter(['id' => $p('aplus_id')])->delete();
            return json(['res' => 'A+ Listing Image Deleted']);
        }

        $act = $p('blog_cat_action');
        if ($act === 'add') {
            Blog_Category::create(['category' => (string)$p('blog_category_name')]);
            return json(['res' => 'Blog Category Created']);
        } elseif ($act === 'delete') {
            Blog_Category::filter(['id' => $p('blog_category_id')])->delete();
            return json(['res' => 'Blog Category Deleted']);
        }

        $act = $p('blog_post_action');
        if ($act === 'add') {
            $cat = Blog_Category::find($p('blog_category'));
            if (!$cat) return json(['res' => 'Please choose a blog category'], 400);
            Blog_post::create([
                'author' => (string)$p('blog_author', ''), 'category' => $cat->pk(),
                'blog_image' => ($f = $r->file('blog_image')) ? (Storage::save($f, 'media/letestblog', IMG_DEFAULT) ?? '') : '',
                'image_alt_name' => (string)$p('blog_alt', ''), 'meta_title' => (string)$p('blog_title', ''), 'meta_keyword' => (string)$p('blog_keyword', ''),
                'meta_description' => (string)$p('blog_desc', ''), 'blog_content' => (string)$p('blog_content', ''), 'date' => local_to_utc($p('blog_date')),
            ]);
            return json(['res' => 'Blog Post Published']);
        } elseif ($act === 'delete') {
            Blog_post::filter(['id' => $p('blog_id')])->delete();
            return json(['res' => 'Blog Post Deleted']);
        }

        if ($p('contact_action') === 'delete') {
            Contact_US::filter(['id' => $p('contact_id')])->delete();
            return json(['res' => 'Contact Message Deleted']);
        }

        /* ---------------- bulk delete */
        if ($p('bulk_delete_products')) {
            $ids = $r->getlist('product_ids[]');
            if (!$ids) return json(['res' => 'No products selected'], 400);
            Product::filter(['id__in' => $ids])->delete();
            Color_product::filter(['id__in' => $ids])->delete();
            return json(['res' => count($ids) . ' product(s) deleted successfully']);
        }
        if ($p('bulk_delete_orders')) {
            $ids = $r->getlist('order_ids[]');
            if (!$ids) return json(['res' => 'No orders selected'], 400);
            Order::filter(['id__in' => $ids])->delete();
            return json(['res' => count($ids) . ' order(s) deleted successfully']);
        }
        if ($p('delete_single_order')) {
            if (!$p('order_id')) return json(['res' => 'Order not found'], 400);
            Order::filter(['id' => $p('order_id')])->delete();
            return json(['res' => 'Order deleted successfully']);
        }

        /* ---------------- categories / orders / product delete */
        if ($p('sub_category_name') && ($f = $r->file('sub_category_image'))) {
            Product_sub_category::create(['category_image' => Storage::save($f, 'cat_image', IMG_DEFAULT) ?? '', 'name' => (string)$p('sub_category_name')]);
            return json(['res' => 'Category Added Successfully']);
        }
        if ($sid = $p('subeditcategoryid')) {
            if ($a = Product_sub_category::find($sid)) {
                if ($f = $r->file('subeditcategory_image')) $a->category_image = Storage::save($f, 'cat_image', IMG_DEFAULT) ?? $a->raw('category_image');
                $a->name = (string)$p('subeditcategory');
                $a->save();
            }
            return json(['res' => 'Category Update Successfully']);
        }
        if ($sid = $p('remove_sub_category')) {
            Product_sub_category::filter(['id' => $sid])->delete();
            return json(['res' => 'Category Remove Successfully']);
        }
        if ($p('category_name') && ($f = $r->file('category_image'))) {
            Product_category::create(['category_image' => Storage::save($f, 'cat_image', IMG_DEFAULT) ?? '', 'name' => (string)$p('category_name')]);
            return json(['res' => 'Category Added Successfully']);
        }
        if ($p('o_id') && $p('o_status')) {
            if ($o = Order::find($p('o_id'))) { $o->status = $p('o_status'); $o->save(); }
            return redirect('/usvesta-deshboard');
        }
        if ($cid = $p('remove_category')) {
            Product_category::filter(['id' => $cid])->delete();
            return json(['res' => 'Category Remove Successfully']);
        }
        if ($cid = $p('editcategoryid')) {
            if ($a = Product_category::find($cid)) {
                if ($f = $r->file('editcategory_image')) $a->category_image = Storage::save($f, 'cat_image', IMG_DEFAULT) ?? $a->raw('category_image');
                $a->name = (string)$p('editcategory');
                $a->save();
            }
            return json(['res' => 'Category Update Successfully']);
        }
        if ($pid = $p('remove_product_from_crm')) {
            if ($x = Product::find($pid)) { $x->delete(); return json(['res' => 'Product Remove Successfully']); }
        } elseif ($pid = $p('inner_remove_product_from_crm')) {
            if ($x = Color_product::find($pid)) { $x->delete(); return json(['res' => 'Product Remove Successfully']); }
        }

        /* ---------------- create product */
        try {
            return json(ProductForm::create($r, $u));
        } catch (Throwable $e) {
            log_error('create product: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

/** Shared product create/update logic (dashboard "Add product" + Update_product). */
final class ProductForm
{
    public static function sizes(Product $p, mixed $json, bool $replace): void
    {
        if ($replace) Size_price_par_post::filter(['product' => $p->pk()])->delete();
        $list = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($list)) return;
        foreach ($list as $sz) {
            $s = strtoupper(trim((string)$sz));
            if ($s !== '' && !in_array($s, ['1', '0', 'ON', 'TRUE', 'FALSE'], true) && !ctype_digit($s)) {
                Size_price_par_post::get_or_create(['product' => $p->pk(), 'size' => $s]);
            }
        }
    }

    private static function applyFields(Product $p, Request $r, CustomUser $u, bool $creating): void
    {
        $g = fn($k) => $r->post($k);
        $name = (string)$g('name');
        $p->user = $u->pk();
        $p->product_name = $name;
        $p->brand = $creating ? ($g('brand') ?: 'US Vesta') : (string)$g('brand');
        $p->top_eight_products = to_bool_str($g('show_at_home'));
        $p->product_status = to_bool_str($g('is_active')) ? 'Publish' : 'Draft';
        $p->product_description = clean_froala((string)$g('description'));
        $p->key_words = (string)($g('keywords') ?? '');
        $p->t_shirt_type = (string)($g('tshirt_type') ?? '');
        $p->color = (string)($g('color') ?? '');
        $p->color_code = (string)($g('color_code') ?? '');
        if ($creating) {
            $p->b2c_price = blank($g('sale_price')) || !is_numeric(trim((string)$g('sale_price'))) ? 0.0 : (float)$g('sale_price');
            $p->discount_price = blank($g('regular_price')) ? '0' : trim((string)$g('regular_price'));
            $p->ratings = blank($g('ratings')) ? '0' : trim((string)$g('ratings'));
            $p->total_sale_products = blank($g('total_sale_product')) ? '0' : trim((string)$g('total_sale_product'));
        } else {
            $p->b2c_price = is_numeric($g('sale_price')) ? $g('sale_price') : 0;
            $p->discount_price = (string)$g('regular_price');
            $p->ratings = (string)$g('ratings');
            $p->total_sale_products = (string)$g('total_sale_product');
        }
        $p->product_quantity_offer = !blank($g('product_quantity_offer')) && is_numeric(trim((string)$g('product_quantity_offer'))) ? (int)$g('product_quantity_offer') : null;
        $p->product_amount_offer = !blank($g('product_amount_offer')) && is_numeric(trim((string)$g('product_amount_offer'))) ? (float)$g('product_amount_offer') : null;
        if ($f = $r->file('main_image')) { $n = Storage::save($f, 'product_image', IMG_PRODUCT); if ($n) $p->product_image = $n; }
        $alt = $g('image_alt');
        $p->product_image_alt = $alt ?: $name;
    }

    private static function subcat(mixed $id): ?Product_sub_category
    {
        if (blank($id) || !ctype_digit(trim((string)$id))) return null;
        return Product_sub_category::find((int)trim((string)$id));
    }

    public static function create(Request $r, CustomUser $u): array
    {
        $sub = self::subcat($r->post('category')) ?? Product_sub_category::objects()->first();
        $p = new Product();
        self::applyFields($p, $r, $u, true);
        $p->sub_category = $sub?->pk();
        $p->category = $sub?->raw('category_id');
        $p->save();
        if ($h = clean_froala($r->post('highlight'))) Hightlight_for_product::create(['product' => $p->pk(), 'highlight' => $h]);
        foreach ($r->files('gallery_images') as $img) {
            $n = Storage::save($img, 'product_image', IMG_PRODUCT);
            if ($n) product_Gelery_images::create(['product' => $p->pk(), 'image' => $n, 'alt' => $p->raw('product_name') . ' gallery']);
        }
        if (!blank($r->post('sizes'))) self::sizes($p, $r->post('sizes'), false);
        self::variants($p, $r, true);
        return ['status' => 'success', 'message' => 'Product created successfully', 'product_id' => $p->pk()];
    }

    public static function update(Request $r, CustomUser $u, Product $p, bool $setCategoryFromSub): array
    {
        $sub = self::subcat($r->post('category'));
        self::applyFields($p, $r, $u, false);
        $p->sub_category = $sub?->pk();
        if ($setCategoryFromSub && $sub && $sub->raw('category_id')) $p->category = $sub->raw('category_id');
        if ($h = clean_froala($r->post('highlight'))) {
            Hightlight_for_product::filter(['product' => $p->pk()])->delete();
            Hightlight_for_product::create(['product' => $p->pk(), 'highlight' => $h]);
        }
        $p->save();
        self::sizes($p, $r->post('sizes') ?? '[]', true);
        $gallery = $r->files('gallery_images');
        if ($gallery) {
            product_Gelery_images::filter(['product' => $p->pk()])->delete();
            foreach ($gallery as $img) {
                $n = Storage::save($img, 'product_image', IMG_PRODUCT);
                if ($n) product_Gelery_images::create(['product' => $p->pk(), 'image' => $n, 'alt' => $p->raw('product_name') . ' gallery']);
            }
        }
        self::variants($p, $r, false);
        return ['status' => 'success', 'message' => 'Product updated successfully', 'product_id' => $p->pk()];
    }

    private static function variants(Product $p, Request $r, bool $creating): void
    {
        $json = $r->post('color_variants', $creating ? null : '[]');
        if (blank($json)) return;
        $list = json_decode((string)$json, true);
        if (!is_array($list)) return;
        foreach ($list as $cv) {
            $cname = (string)($cv['color_name'] ?? '');
            if (!$creating && $cname === '') continue;
            $code = (string)($cv['color_code'] ?? '');
            $alt = $creating ? (($cv['image_alt'] ?? '') ?: $cname) : ($cv['image_alt'] ?? "$cname variant");
            $idx = $cv['variant_index'] ?? '';
            $count = is_numeric($cv['gallery_count'] ?? null) ? (int)$cv['gallery_count'] : 0;
            $imgFile = $r->file("variant_image_$idx");
            $img = $imgFile ? Storage::save($imgFile, 'color_images', IMG_DEFAULT) : null;
            $isNew = $cv['is_new'] ?? true;
            $existing = $cv['existing_id'] ?? null;

            if (!$creating && !$isNew && $existing) {
                $obj = Color_product::filter(['id' => $existing, 'product' => $p->pk()])->first();
                if ($obj) {
                    $obj->color = $cname; $obj->color_code = $code;
                    if ($img) $obj->color_images = $img;
                    $obj->save();
                    $g = color_product_Gelery_images::filter(['color_product' => $obj->pk()])->first();
                    if (!$g) color_product_Gelery_images::create(['color_product' => $obj->pk(), 'image' => $img ?? '', 'alt' => $alt]);
                    else { $g->alt = $alt; if ($img) $g->image = $img; $g->save(); }
                } else {
                    $obj = Color_product::create(['product' => $p->pk(), 'color' => $cname, 'color_code' => $code, 'color_images' => $img ?? '']);
                    color_product_Gelery_images::create(['color_product' => $obj->pk(), 'image' => $img ?? '', 'alt' => $alt]);
                }
            } else {
                $obj = Color_product::create(['product' => $p->pk(), 'color' => $cname, 'color_code' => $code, 'color_images' => $img ?? '']);
                if ($creating) color_product_Gelery_images::create(['color_product' => $obj->pk(), 'image' => $img ?? '', 'alt' => $alt]);
            }
            for ($i = 0; $i < $count; $i++) {
                if ($gf = $r->file("variant_gallery_{$idx}_$i")) {
                    $n = Storage::save($gf, 'product_image', IMG_PRODUCT);
                    if ($n) color_product_Gelery_images::create(['color_product' => $obj->pk(), 'image' => $n, 'alt' => $alt]);
                }
            }
        }
    }
}

final class Update_product
{
    public function get(Request $r, string $slug): Response
    {
        require_superuser();
        $type = $r->get('product-type');
        $target = null;
        if ($type === 'product') {
            $product = Product::filter(['uuid_product' => $slug])->first();
            $target = $product;
        } elseif ($type === 'inner-product') {
            $product = Color_product::filter(['uuid_product' => $slug])->first();
            $target = $product?->product;
        } else {
            throw new Http404();
        }
        $sizes = $target ? Size_price_par_post::filter(['product' => $target->pk()]) : [];
        $hl = $target ? Hightlight_for_product::filter(['product' => $target->pk()])->first() : null;
        return render($r, 'dashboard/update_product.html', [
            'subcategory' => Product_sub_category::objects(),
            'a' => $product,
            'slug' => $slug,
            'color_product' => $target ? Color_product::filter(['product' => $target->pk()]) : [],
            'products_size' => $sizes,
            'product_gellery_image' => $target ? product_Gelery_images::filter(['product' => $target->pk()]) : [],
            'available_sizes' => array_map(fn($s) => $s->raw('size'), is_array($sizes) ? $sizes : $sizes->get()),
            'all_sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
            'product_type' => $type,
            'highlight_val' => $hl ? $hl->raw('highlight') : '',
        ]);
    }

    public function post(Request $r, string $slug): Response
    {
        require_superuser();
        $u = Auth::user();
        try {
            $type = $r->post('product_type');
            if ($type === 'product') {
                $p = Product::filter(['uuid_product' => $slug])->first();
                if (!$p) return json(['status' => 'error', 'message' => 'Product not found']);
                return json(ProductForm::update($r, $u, $p, true));
            }
            if ($type === 'inner-product') {
                $ip = Color_product::filter(['uuid_product' => $slug])->first();
                if (!$ip || !$ip->product) return json(['status' => 'error', 'message' => 'Inner product not found']);
                return json(ProductForm::update($r, $u, $ip->product, false));
            }
            return json(null);
        } catch (Throwable $e) {
            log_error('update product: ' . $e->getMessage());
            return json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

final class update_dashboard_form_etc_data
{
    public function post(Request $r): Response
    {
        require_superuser();
        $g = fn($k) => $r->post($k);
        $back = $r->referer('/');

        if ($g('coordinator_name') && $g('coordinator_content') && $g('coordinator_sub_category')) {
            sub_category_coordinator::create(['name' => $g('coordinator_name'), 'sub_category' => Product_sub_category::find($g('coordinator_sub_category'))?->pk(),
                'containt' => $g('coordinator_content'), 'show_on_pages' => implode(',', $r->getlist('show_on_pages'))]);
            msg_success('Coordinator Added Successfully!');
            return redirect($back);
        } elseif ($id = $g('coordinator_update_id')) {
            $c = sub_category_coordinator::find($id);
            if ($c) {
                if ($v = $g('coordinator_name_update')) $c->name = $v;
                if ($v = $g('coordinator_content_update')) $c->containt = $v;
                if (($s = $g('coordinator_sub_category_update')) && ($sc = Product_sub_category::find($s))) $c->sub_category = $sc->pk();
                $c->show_on_pages = implode(',', $r->getlist('show_on_pages_update'));
                $c->save();
            }
            msg_success('Coordinator Update Successfully!');
            return redirect($back);
        } elseif ($id = $g('coordinator_delete_id')) {
            sub_category_coordinator::filter(['id' => $id])->delete();
            return json(['res' => 'Coordinator Delete Successfully!']);
        }

        if ($g('update_review_name') && $g('update_review_id') && $g('update_review_email') && $g('update_reviews_rating') && $g('update_reviews_meseje')) {
            if ($rv = User_Reviews::find($g('update_review_id'))) {
                $rv->name = $g('update_review_name');
                $rv->email = $g('update_review_email');
                $rv->msg = $g('update_reviews_meseje');
                $rv->show = $g('update_reviews_showhide') === 'on';
                $rv->star = $g('update_reviews_rating');
                $rv->save();
                msg_success('Review Update Successfully!');
                return redirect($back);
            }
        } elseif ($id = $g('review_delete_id')) {
            User_Reviews::filter(['id' => $id])->delete();
            return json(['res' => 'Review Delete Successfully!']);
        }
        return redirect($back);
    }
}

final class color_django_verint
{
    public function post(Request $r): Response
    {
        require_superuser();
        $mainId = $r->post('main_product_id_for_color_variant_id');
        $type = $r->post('product_type');
        $names = $r->getlist('color_name[]');
        $ids = $r->getlist('color_variant_id[]');
        $codes = $r->getlist('color_code[]');
        if (!in_array($type, ['product', 'inner-product'], true)) return json(null);
        if (!($mainId && $names && $ids)) return json(['status' => 'error', 'res' => 'Missing data.']);
        if ($type === 'product') {
            $product = Product::find($mainId);
        } else {
            $product = Color_product::filter(['product' => $mainId])->first()?->product;
        }
        if (!$product) return json(['status' => 'error', 'res' => 'Product not found.']);
        foreach ($ids as $i => $vid) {
            $c = Color_product::find($vid);
            if (!$c) continue;
            $c->product = $product->pk();
            $c->color = $names[$i] ?? '';
            $c->color_code = $codes[$i] ?? '';
            if ($f = $r->file("color_image_$i")) { $n = Storage::save($f, 'color_images', IMG_DEFAULT); if ($n) $c->color_images = $n; }
            $c->save();
            $gallery = $r->files("gallery_images_{$i}[]");
            if ($gallery) {
                color_product_Gelery_images::filter(['color_product' => $c->pk()])->delete();
                foreach ($gallery as $g) {
                    $n = Storage::save($g, 'product_image', IMG_PRODUCT);
                    if ($n) color_product_Gelery_images::create(['color_product' => $c->pk(), 'image' => $n]);
                }
            }
        }
        return json(['status' => 'success', 'res' => 'Color variants updated successfully!']);
    }
}

final class ordermodel_popup
{
    public function post(Request $r): Response
    {
        require_superuser();
        $o = $r->post('order_id') ? Order::find($r->post('order_id')) : null;
        if (!$o) return http('Invalid Order ID', 400);
        return render($r, 'dashboard/order_popup.html', ['a' => $o]);
    }
}

final class Upload_pd_in_bulk
{
    public function post(Request $r): Response
    {
        require_superuser();
        $f = $r->file('xl_file_name_for_bluk_upload');
        if (!$f) return json(['status' => 'error', 'message' => 'No Excel file found']);
        $dir = BASE_PATH . '/storage/bulk_uploads';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $path = $dir . '/' . time() . '_' . Storage::validName($f->name);
        if (!@move_uploaded_file($f->tmp, $path) && !@copy($f->tmp, $path)) return json(['status' => 'error', 'message' => 'Upload failed']);
        $uid = Auth::user()->pk();
        after_response(fn() => BulkImport::run($path, (int)$uid));
        return json(['status' => 'success', 'message' => 'Bulk upload started in background']);
    }
}

final class BulkImport
{
    /** Google Drive share link -> [bytes, fileId] */
    private static function drive(?string $url): ?array
    {
        if (!$url || !preg_match('#/d/([^/]+)#', trim($url), $m)) return null;
        try {
            $r = Http::get('https://drive.google.com/uc?export=download&id=' . $m[1], [], 30);
            return $r['status'] === 200 && $r['body'] !== '' ? [$r['body'], $m[1]] : null;
        } catch (Throwable) { return null; }
    }

    public static function run(string $path, int $userId): void
    {
        $rows = Xlsx::readRows($path);
        if (!$rows) return;
        $headers = array_map(fn($h) => $h === null ? '' : str_replace(' ', '_', trim((string)$h)), array_shift($rows));
        $galleryCols = array_values(array_filter($headers, fn($h) => str_starts_with($h, 'Product_Gallery_Image')));
        $colorGalleryCols = array_values(array_filter($headers, fn($h) => str_starts_with($h, 'color_variant_gallery_image')));
        $highlightCols = array_values(array_filter($headers, fn($h) => str_starts_with($h, 'Highlight')));
        $created = [];
        foreach ($rows as $row) {
            $d = [];
            foreach ($headers as $i => $h) $d[$h] = $row[$i] ?? null;
            $sku = trim((string)($d['Products_SKU'] ?? ''));
            if ($sku === '') continue;
            $parts = explode('-', $sku);
            $code = trim($parts[0]);
            $sizeFromSku = isset($parts[1]) ? trim($parts[1]) : null;

            if (!isset($created[$code])) {
                $cur = Product::filter(['product_sku' => $code])->first();
                if (!$cur) {
                    $img = '';
                    if ($dl = self::drive($d['Main_Products_Image'] ?? null)) $img = Storage::putContents($dl[0], 'product_image', $dl[1] . '.jpg', IMG_PRODUCT);
                    $cur = Product::create([
                        'user' => $userId, 'product_sku' => $code,
                        'category' => Product_category::filter(['name__iexact' => trim((string)($d['category'] ?? ''))])->first()?->pk(),
                        'sub_category' => Product_sub_category::filter(['name__iexact' => trim((string)($d['sub_category'] ?? ''))])->first()?->pk(),
                        'brand' => ($d['brand'] ?? null) ?: 'US Vesta',
                        'top_eight_products' => in_array(strtolower((string)($d['top_eight_products'] ?? '')), ['1', 'true', 'yes'], true),
                        'ratings' => $d['ratings'] ?? null, 'total_sale_products' => $d['total_sale_products'] ?? null,
                        'product_name' => (string)($d['Products_Title'] ?? ''), 'product_image' => $img, 'product_image_alt' => (string)($d['Products_Title'] ?? ''),
                        'key_words' => (string)($d['SEO_Keywords'] ?? ''), 'product_description' => (string)($d['Products_Description'] ?? ''),
                        'discount_price' => (string)($d['Regular_Price'] ?? 0 ?: 0), 'b2b_price' => is_numeric($d['b2b_price'] ?? null) ? $d['b2b_price'] : 0,
                        'b2c_price' => is_numeric($d['Sale_Price'] ?? null) ? $d['Sale_Price'] : 0, 'color' => (string)($d['Product_Color'] ?? ''),
                        'product_quantity_offer' => is_numeric($d['product_quantity_offer'] ?? null) ? (int)$d['product_quantity_offer'] : null,
                        'product_amount_offer' => is_numeric($d['product_amount_offer'] ?? null) ? $d['product_amount_offer'] : null,
                        't_shirt_type' => $d['t_shirt_type'] ?? null, 'select_page' => $d['select_page'] ?? null,
                        'product_status' => ($d['product_status'] ?? null) ?: 'Publish',
                    ]);
                    foreach ($highlightCols as $hc) if (!blank($d[$hc] ?? null)) Hightlight_for_product::create(['product' => $cur->pk(), 'highlight' => trim((string)$d[$hc])]);
                }
                $created[$code] = $cur;
            }
            $cur = $created[$code];

            foreach ($galleryCols as $col) {
                if (blank($d[$col] ?? null)) continue;
                if ($dl = self::drive($d[$col])) {
                    if (!product_Gelery_images::filter(['product' => $cur->pk(), 'image__icontains' => $dl[1]])->exists()) {
                        product_Gelery_images::create(['product' => $cur->pk(), 'image' => Storage::putContents($dl[0], 'product_image', $dl[1] . '.jpg', IMG_PRODUCT), 'alt' => $cur->raw('product_name')]);
                    }
                }
            }
            $size = $sizeFromSku ?: ($d['Product_Size'] ?? null);
            if (!blank($size)) Size_price_par_post::get_or_create(['product' => $cur->pk(), 'size' => trim((string)$size)]);

            $color = $d['color_variant'] ?? null;
            if (!blank($color)) {
                $cimg = '';
                if ($dl = self::drive($d['color_variant_image'] ?? null)) $cimg = Storage::putContents($dl[0], 'color_images', $dl[1] . '.jpg', IMG_DEFAULT);
                [$cobj] = Color_product::get_or_create(['product' => $cur->pk(), 'color' => trim((string)$color)], ['color_images' => $cimg]);
                foreach ($colorGalleryCols as $col) {
                    if (blank($d[$col] ?? null)) continue;
                    if ($dl = self::drive($d[$col])) {
                        if (!color_product_Gelery_images::filter(['color_product' => $cobj->pk(), 'image__icontains' => $dl[1]])->exists()) {
                            color_product_Gelery_images::create(['color_product' => $cobj->pk(), 'image' => Storage::putContents($dl[0], 'product_image', $dl[1] . '.jpg', IMG_PRODUCT),
                                'alt' => $cur->raw('product_name') . ' - ' . $color]);
                        }
                    }
                }
            }
        }
        @unlink($path);
    }
}

/** Product feed API for partners: GET /api/v1/products/ with X-API-KEY (public key of an active API key). */
final class ProductSyncAPIView
{
    public function get(Request $r): Response
    {
        $key = $r->header('X-API-KEY') ?? $r->get('api_key');
        if (!$key) return json(['error' => 'Authentication credentials were not provided. Use X-API-KEY header or api_key query parameter.'], 401);
        $api = APIKey::filter(['public_key' => $key, 'is_active' => true])->first();
        if (!$api) return json(['error' => 'Invalid or inactive API Key.'], 403);
        $host = rtrim(absolute_url(''), '/');
        $out = [];
        foreach (Product::filter(['product_status' => 'Publish'])->order_by('-id') as $p) {
            $gallery = [];
            foreach (product_Gelery_images::filter(['product' => $p->pk()]) as $g) if ($g->image->name !== '') $gallery[] = ['image_url' => $host . $g->image->url(), 'alt' => $g->raw('alt') ?: ''];
            $variants = [];
            foreach (Color_product::filter(['product' => $p->pk()]) as $cv) {
                $vg = [];
                foreach (color_product_Gelery_images::filter(['color_product' => $cv->pk()]) as $g) if ($g->image->name !== '') $vg[] = ['image_url' => $host . $g->image->url(), 'alt' => $g->raw('alt') ?: ''];
                $variants[] = ['id' => $cv->pk(), 'color' => $cv->raw('color'), 'color_code' => $cv->raw('color_code'),
                    'color_image' => $cv->color_images->name !== '' ? $host . $cv->color_images->url() : '', 'gallery' => $vg];
            }
            $out[] = [
                'id' => $p->pk(), 'uuid' => $p->raw('uuid_product'), 'name' => $p->raw('product_name'), 'slug' => $p->raw('product_slug'),
                'sku' => $p->raw('product_sku') ?: '', 'brand' => $p->raw('brand'), 'category' => $p->category ? (string)$p->category : '',
                'sub_category' => $p->sub_category ? (string)$p->sub_category : '', 'description' => $p->raw('product_description'),
                'keywords' => $p->raw('key_words') ?: '', 'regular_price' => $p->raw('discount_price'), 'sale_price' => (string)$p->b2c_price,
                'b2b_price' => $p->raw('b2b_price') ? (string)$p->b2b_price : '', 't_shirt_type' => $p->raw('t_shirt_type') ?: '',
                'ratings' => $p->raw('ratings') ?: '0', 'quantity_offer' => $p->raw('product_quantity_offer') ?: 0,
                'amount_offer' => $p->raw('product_amount_offer') ? (string)$p->product_amount_offer : '',
                'main_image' => $p->product_image->name !== '' ? $host . $p->product_image->url() : '',
                'sizes' => Size_price_par_post::filter(['product' => $p->pk()])->values_list('size'),
                'gallery' => $gallery, 'color_variants' => $variants, 'date_created' => $p->date?->format(DATE_ATOM),
            ];
        }
        return json(['status' => 'success', 'count' => count($out), 'products' => $out]);
    }
}
