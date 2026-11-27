<?php
/**
 * Models – direct port of cloth_app/models.py. Table and column names are identical to the
 * Django database, so existing data works unchanged.
 */

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function uuid_hex(): string { return str_replace('-', '', uuid4()); }

/** Django slugify + AutoSlugField(unique=True) */
function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower($s);
    $s = preg_replace('/[^\w\s-]/', '', $s);
    $s = preg_replace('/[-\s]+/', '-', $s);
    return trim($s, '-_');
}
function unique_slug(string $model, string $field, string $source, int $maxLen, mixed $excludePk = null): string
{
    $base = slugify($source);
    if ($base === '') $base = '-';
    $base = substr($base, 0, $maxLen);
    $slug = $base;
    $i = 2;
    while (true) {
        $q = $model::filter([$field => $slug]);
        if ($excludePk !== null) $q = $q->exclude([$model::pkName() => $excludePk]);
        if (!$q->exists()) return $slug;
        $suffix = '-' . $i++;
        $slug = substr($base, 0, $maxLen - strlen($suffix)) . $suffix;
    }
}

function now_utc_str(): string { return gmdate('Y-m-d H:i:s'); }

/* ====================================================================== users */

class CustomUser extends Model
{
    protected static string $table = 'cloth_app_customuser';
    protected static array $fields = [
        'id' => 'auto', 'password' => 'str', 'last_login' => 'datetime', 'is_superuser' => 'bool', 'username' => 'str',
        'first_name' => 'str', 'last_name' => 'str', 'email' => 'str', 'is_staff' => 'bool', 'is_active' => 'bool',
        'date_joined' => 'datetime', 'profile_image' => 'file', 'email_token' => 'str', 'about' => 'str', 'bank_details' => 'str',
        'password_reset_requested_at' => 'datetime', 'user_type' => 'str', 'decrypt_password' => 'str',
        'email_verification' => 'bool', 'otp_created_at' => 'datetime', 'phone_number_otp' => 'str', 'phone' => 'str',
    ];
    protected static array $defaults = [
        'password' => '', 'is_superuser' => 0, 'first_name' => '', 'last_name' => '', 'email' => '', 'is_staff' => 0,
        'is_active' => 1, 'user_type' => 'B2C', 'email_verification' => 0, 'profile_image' => '',
    ];

    protected function beforeSave(bool $creating): void
    {
        if ($creating && empty($this->attrs['date_joined'])) $this->attrs['date_joined'] = now_utc_str();
        if (empty($this->attrs['email_token'])) $this->attrs['email_token'] = uuid4();
    }

    public function is_authenticated(): bool { return true; }
    public function is_anonymous(): bool { return false; }
    public function get_full_name(): string { return trim(($this->attrs['first_name'] ?? '') . ' ' . ($this->attrs['last_name'] ?? '')); }
    public function get_username(): string { return (string)($this->attrs['username'] ?? ''); }

    public function set_password(?string $raw): void { $this->attrs['password'] = $raw === null ? '!' . bin2hex(random_bytes(20)) : Passwords::make($raw); }
    public function check_password(string $raw): bool { return Passwords::check($raw, (string)($this->attrs['password'] ?? '')); }

    public function is_otp_valid(): bool
    {
        $t = $this->otp_created_at;
        return $t instanceof DateTimeInterface && time() < $t->getTimestamp() + 300;
    }

    public function __toString(): string { return (string)($this->attrs['username'] ?? ''); }
}

class AnonymousUser
{
    public $id = null;
    public $pk = null;
    public $username = '';
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $is_superuser = false;
    public $is_staff = false;
    public $is_active = false;
    public $user_type = '';
    public $profile_image = null;
    public $about = '';
    public function is_authenticated(): bool { return false; }
    public function is_anonymous(): bool { return true; }
    public function __toString(): string { return 'AnonymousUser'; }
}

/* ====================================================================== catalog */

class Product_category extends Model
{
    protected static string $table = 'cloth_app_product_category';
    protected static array $fields = ['id' => 'auto', 'uuid_product' => 'str', 'date' => 'datetime', 'category_image' => 'file', 'mokups_type' => 'str', 'name' => 'str'];
    protected function beforeSave(bool $c): void
    {
        if (empty($this->attrs['uuid_product'])) $this->attrs['uuid_product'] = uuid4();
        if ($c && empty($this->attrs['date'])) $this->attrs['date'] = now_utc_str();
    }
    public function get_absolute_url(): string { return '/category?categories=' . $this->attrs['uuid_product']; }
    public function __toString(): string { return (string)($this->attrs['name'] ?? ''); }
    public static function onDelete(Model $m): void
    {
        Product::filter(['category' => $m->pk()])->delete();
        Product_sub_category::filter(['category' => $m->pk()])->delete();
    }
}

class Product_sub_category extends Model
{
    protected static string $table = 'cloth_app_product_sub_category';
    protected static array $fields = ['id' => 'auto', 'category_image' => 'file', 'name' => 'str', 'uuid_product' => 'str', 'date' => 'datetime',
        'category_slug' => 'str', 'category' => 'fk:Product_category', 'customcode' => 'text'];
    protected function beforeSave(bool $c): void
    {
        if (empty($this->attrs['uuid_product'])) $this->attrs['uuid_product'] = uuid4();
        if ($c && empty($this->attrs['date'])) $this->attrs['date'] = now_utc_str();
        if ($c && empty($this->attrs['category_slug'])) $this->attrs['category_slug'] = unique_slug(static::class, 'category_slug', (string)($this->attrs['name'] ?? ''), 50);
    }
    public function get_absolute_url(): string { return '/collections/' . $this->attrs['category_slug']; }
    public function __toString(): string { return (string)($this->attrs['name'] ?? ''); }
    public static function onDelete(Model $m): void
    {
        Product::filter(['sub_category' => $m->pk()])->delete();
        Banners::filter(['category_images_slider' => $m->pk()])->delete();
        sub_category_coordinator::filter(['sub_category' => $m->pk()])->delete();
    }
}

trait ReviewStats
{
    public function average_rating(): float
    {
        $reviews = $this->user_reviews_set->filter(['show' => true]);
        $count = $reviews->count();
        if ($count === 0) return 0.0;
        $t = 0.0;
        foreach ($reviews as $r) if (is_numeric($r->raw('star'))) $t += (float)$r->raw('star');
        return Filters::pyRound($t / $count, 1);
    }
    public function dynamic_review_count(): int { return $this->user_reviews_set->filter(['show' => true])->count(); }
}

class Product extends Model
{
    use ReviewStats;
    protected static string $table = 'cloth_app_product';
    protected static array $fields = [
        'id' => 'auto', 'user' => 'fk:CustomUser', 'category' => 'fk:Product_category', 'sub_category' => 'fk:Product_sub_category',
        'brand' => 'str', 'top_eight_products' => 'bool', 'ratings' => 'str', 'product_sku' => 'str', 'total_sale_products' => 'str',
        'temp_product_image_url' => 'str', 'select_page' => 'str', 't_shirt_type' => 'str', 'product_name' => 'str',
        'product_image' => 'file', 'key_words' => 'str', 'product_image_alt' => 'str', 'product_description' => 'text',
        'product_status' => 'str', 'discount_price' => 'str', 'product_slug' => 'str', 'uuid_product' => 'str', 'date' => 'datetime',
        'b2b_price' => 'decimal:2', 'b2c_price' => 'decimal:2', 'color' => 'str', 'color_code' => 'str',
        'product_quantity_offer' => 'int', 'product_amount_offer' => 'decimal:2',
    ];
    protected static array $defaults = ['brand' => 'US Vesta', 'top_eight_products' => 0, 'product_status' => 'Publish', 'key_words' => '', 'product_image' => '', 'color' => '', 'color_code' => '', 'product_image_alt' => ''];

    protected function beforeSave(bool $c): void
    {
        if (empty($this->attrs['uuid_product'])) $this->attrs['uuid_product'] = uuid4();
        if ($c && empty($this->attrs['date'])) $this->attrs['date'] = now_utc_str();
        if ($c && empty($this->attrs['product_slug'])) $this->attrs['product_slug'] = unique_slug(static::class, 'product_slug', (string)($this->attrs['product_name'] ?? ''), 50);
    }
    public static function onDelete(Model $m): void
    {
        foreach ([Hightlight_for_product::class, Color_product::class, product_Gelery_images::class, Size_price_par_post::class,
                     Cart::class, Wishlist::class, Order::class, User_Reviews::class] as $cls) {
            $cls::filter(['product' => $m->pk()])->delete();
        }
    }

    public function get_absolute_url(): string { return '/products/' . $this->attrs['uuid_product']; }
    public function __toString(): string { return (string)($this->attrs['product_name'] ?? ''); }

    public function get_discount_percentage(): int
    {
        $reg = $this->attrs['discount_price'] ?? null; $sale = $this->attrs['b2c_price'] ?? null;
        if (!is_numeric($reg) || !is_numeric($sale)) return 0;
        $reg = (float)$reg; $sale = (float)$sale;
        if ($reg > 0 && $reg > $sale) return (int)Filters::pyRound((($reg - $sale) / $reg) * 100);
        return 0;
    }
    public function get_b2c_price_int(): mixed
    {
        $v = $this->attrs['b2c_price'] ?? null;
        return is_numeric($v) ? (string)(int)Filters::pyRound((float)$v) : $this->b2c_price;
    }
    public function get_discount_price_int(): mixed
    {
        $v = $this->attrs['discount_price'] ?? null;
        return is_numeric($v) ? (string)(int)Filters::pyRound((float)$v) : $v;
    }
}

class Hightlight_for_product extends Model
{
    protected static string $table = 'cloth_app_hightlight_for_product';
    protected static array $fields = ['id' => 'auto', 'product' => 'fk:Product', 'highlight' => 'text'];
}

class Color_product extends Model
{
    use ReviewStats;
    protected static string $table = 'cloth_app_color_product';
    protected static array $fields = ['id' => 'auto', 'product' => 'fk:Product', 'color' => 'str', 'color_code' => 'str',
        'color_images' => 'file', 'uuid_product' => 'str', 'temp_color_image_url' => 'str'];
    protected function beforeSave(bool $c): void { if (empty($this->attrs['uuid_product'])) $this->attrs['uuid_product'] = uuid4(); }
    public function __toString(): string { return (string)($this->attrs['color'] ?? ''); }
    public static function onDelete(Model $m): void
    {
        color_product_Gelery_images::filter(['color_product' => $m->pk()])->delete();
        Cart::filter(['inner_products_card' => $m->pk()])->delete();
        Wishlist::filter(['inner_products_wishlist' => $m->pk()])->delete();
        Order::filter(['inner_color_product' => $m->pk()])->delete();
        User_Reviews::filter(['inner_product_rew' => $m->pk()])->delete();
    }
}

class color_product_Gelery_images extends Model
{
    protected static string $table = 'cloth_app_color_product_gelery_images';
    protected static array $fields = ['id' => 'auto', 'color_product' => 'fk:Color_product', 'image' => 'file', 'alt' => 'str', 'temp_image_url' => 'str'];
}

class product_Gelery_images extends Model
{
    protected static string $table = 'cloth_app_product_gelery_images';
    protected static array $fields = ['id' => 'auto', 'product' => 'fk:Product', 'image' => 'file', 'alt' => 'str', 'temp_image_url' => 'str'];
    protected static array $defaults = ['alt' => ''];
}

class Size_price_par_post extends Model
{
    protected static string $table = 'cloth_app_size_price_par_post';
    protected static array $fields = ['id' => 'auto', 'product' => 'fk:Product', 'size' => 'str'];
    public function __toString(): string { return (string)($this->attrs['size'] ?? ''); }
}

/* ====================================================================== shopping */

class Cart extends Model
{
    protected static string $table = 'cloth_app_cart';
    protected static array $fields = ['id' => 'auto', 'user' => 'fk:CustomUser', 'product' => 'fk:Product', 'inner_products_card' => 'fk:Color_product',
        'quantity' => 'int', 'date' => 'datetime', 'size' => 'str'];
    protected static array $defaults = ['quantity' => 1];
    protected function beforeSave(bool $c): void { $this->attrs['date'] = now_utc_str(); }

    private function unitPrice(?Product $p): float
    {
        if (!$p) return 0;
        $u = $this->user;
        $raw = ($u && $u->raw('user_type') === 'B2B') ? $p->raw('b2b_price') : $p->raw('b2c_price');
        return is_numeric($raw) ? (float)$raw : 0.0;
    }
    public function total_price(): float|int
    {
        if (!$this->product) return 0;
        return (int)$this->raw('quantity') * $this->unitPrice($this->product);
    }
    public function total_price_int(): int { $v = $this->total_price(); return $v ? (int)Filters::pyRound($v) : 0; }
    public function total_inner_product_price(): float|int
    {
        $ip = $this->inner_products_card;
        if (!$ip) return 0;
        return (int)$this->raw('quantity') * $this->unitPrice($ip->product);
    }
    public function total_inner_product_price_int(): int { $v = $this->total_inner_product_price(); return $v ? (int)Filters::pyRound($v) : 0; }
}

class Wishlist extends Model
{
    protected static string $table = 'cloth_app_wishlist';
    protected static array $fields = ['id' => 'auto', 'user' => 'fk:CustomUser', 'product' => 'fk:Product', 'inner_products_wishlist' => 'fk:Color_product', 'added_at' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['added_at'] = now_utc_str(); }
}

class Address_user extends Model
{
    protected static string $table = 'cloth_app_address_user';
    protected static array $fields = ['id' => 'auto', 'user' => 'fk:CustomUser', 'name' => 'str', 'email' => 'str', 'state' => 'str', 'phone_numer' => 'str',
        'pin_code' => 'str', 'landmark' => 'str', 'user_address' => 'text', 'created_at' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['created_at'] = now_utc_str(); }

    public function asOrderAddress(): string
    {
        return implode("\n", [
            'first_name=' . $this->raw('name'), 'phone=' . $this->raw('phone_numer'), 'email=' . $this->raw('email'),
            'state=' . $this->raw('state'), 'landmark=' . $this->raw('landmark'), 'pincode=' . $this->raw('pin_code'),
            'address=' . $this->raw('user_address'),
        ]);
    }
}

class Order extends Model
{
    protected static string $table = 'cloth_app_order';
    protected static array $fields = [
        'id' => 'auto', 'user' => 'fk:CustomUser', 'product' => 'fk:Product', 'inner_color_product' => 'fk:Color_product',
        'custom_name' => 'str', 'custom_email' => 'str', 'custom_number' => 'str', 'custom_design' => 'file', 'product_size' => 'str',
        'quantity' => 'str', 'order_id' => 'str', 'transaction_id' => 'str', 'transaction_order_id' => 'str', 'total_price' => 'decimal:2',
        'created_at' => 'datetime', 'updated_at' => 'datetime', 'address' => 'text', 'messag' => 'text', 'status' => 'str',
        'payment_status' => 'str', 'payment_method' => 'str', 'awb_number' => 'str', 'shipment_id' => 'str', 'shipment_status' => 'str',
    ];
    protected static array $defaults = ['status' => 'Ordered', 'payment_status' => 'pending', 'payment_method' => 'Cash On Delivery',
        'shipment_status' => 'Pending', 'product_size' => '', 'custom_design' => ''];

    protected function beforeSave(bool $c): void
    {
        if (empty($this->attrs['order_id'])) $this->attrs['order_id'] = 'ORD' . ($this->attrs['id'] ?? strtoupper(substr(uuid_hex(), 0, 8)));
        if (empty($this->attrs['transaction_id'])) $this->attrs['transaction_id'] = 'TXN' . strtoupper(substr(uuid_hex(), 0, 10));
        if (empty($this->attrs['transaction_order_id'])) $this->attrs['transaction_order_id'] = 'TOID' . strtoupper(substr(uuid_hex(), 0, 12));
        if ($c) $this->attrs['created_at'] = now_utc_str();
        $this->attrs['updated_at'] = now_utc_str();
        if (isset($this->attrs['total_price']) && ($this->attrs['total_price'] === '' || !is_numeric($this->attrs['total_price']))) $this->attrs['total_price'] = 0;
    }

    /** post_save signal: create a Delhivery shipment for new orders (runs after the response is sent). */
    protected function afterSave(bool $created): void
    {
        if ($created && empty($this->attrs['awb_number'])) {
            $id = $this->pk();
            after_response(fn() => Delhivery::createForOrder((int)$id));
        }
    }

    public static function onDelete(Model $m): void { Return_and_Cancel_products::filter(['orders' => $m->pk()])->delete(); }

    public static function parseAddress(?string $address): array
    {
        $out = [];
        foreach (explode("\n", (string)$address) as $line) {
            $line = trim($line);
            if (str_contains($line, '=')) { [$k, $v] = explode('=', $line, 2); $out[trim($k)] = trim($v); }
        }
        return $out;
    }

    public function __toString(): string { return 'Order ' . $this->raw('order_id') . ' - ' . ($this->user?->raw('username') ?? ''); }
}

class Return_and_Cancel_products extends Model
{
    protected static string $table = 'cloth_app_return_and_cancel_products';
    protected static array $fields = ['id' => 'auto', 'user' => 'fk:CustomUser', 'orders' => 'fk:Order', 'product_image_retun' => 'file',
        'reason' => 'str', 'reason_mesej' => 'text', 'cancel_request' => 'bool', 'create_at' => 'datetime'];
    protected static array $defaults = ['cancel_request' => 0, 'product_image_retun' => ''];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['create_at'] = now_utc_str(); }
}

class User_Reviews extends Model
{
    protected static string $table = 'cloth_app_user_reviews';
    protected static array $fields = ['id' => 'auto', 'product' => 'fk:Product', 'inner_product_rew' => 'fk:Color_product', 'user' => 'fk:CustomUser',
        'name' => 'str', 'email' => 'str', 'msg' => 'text', 'star' => 'str', 'show' => 'bool', 'created_at' => 'datetime'];
    protected static array $defaults = ['show' => 0];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['created_at'] = now_utc_str(); }
    public function __toString(): string { return (string)($this->attrs['name'] ?? ''); }
}

/* ====================================================================== content */

class Contact_US extends Model
{
    protected static string $table = 'cloth_app_contact_us';
    protected static array $fields = ['id' => 'auto', 'name' => 'str', 'phone' => 'str', 'email' => 'str', 'message' => 'text', 'date' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['date'] = now_utc_str(); }
    public function __toString(): string { return (string)($this->attrs['name'] ?? ''); }
}

class Blog_Category extends Model
{
    protected static string $table = 'cloth_app_blog_category';
    protected static array $fields = ['id' => 'auto', 'category' => 'str', 'ct_slug' => 'str'];
    protected function beforeSave(bool $c): void
    {
        if ($c && empty($this->attrs['ct_slug'])) $this->attrs['ct_slug'] = unique_slug(static::class, 'ct_slug', (string)($this->attrs['category'] ?? ''), 800);
    }
    public function __toString(): string { return (string)($this->attrs['category'] ?? ''); }
    public static function onDelete(Model $m): void { Blog_post::filter(['category' => $m->pk()])->delete(); }
}

class Blog_post extends Model
{
    protected static string $table = 'cloth_app_blog_post';
    protected static array $fields = ['id' => 'auto', 'author' => 'str', 'category' => 'fk:Blog_Category', 'blog_image' => 'file', 'image_alt_name' => 'str',
        'meta_title' => 'str', 'meta_keyword' => 'str', 'meta_description' => 'str', 'created_at' => 'datetime', 'blog_content' => 'text',
        'slug' => 'str', 'date' => 'datetime'];
    protected function beforeSave(bool $c): void
    {
        $this->attrs['created_at'] = now_utc_str();
        if ($c && empty($this->attrs['slug'])) $this->attrs['slug'] = unique_slug(static::class, 'slug', (string)($this->attrs['meta_title'] ?? ''), 1000);
    }
    public function get_absolute_url(): string { return '/' . $this->attrs['slug']; }
    public function __toString(): string { return (string)($this->attrs['slug'] ?? ''); }
}

class A_plus_listing extends Model
{
    protected static string $table = 'cloth_app_a_plus_listing';
    protected static array $fields = ['id' => 'auto', 'image' => 'file', 'image_alt_name' => 'str'];
}

const PAGE_CHOICES = [
    ['/', 'Home Page'], ['/our-story', 'Our Story Page'], ['/shop', 'Shop Page'], ['/contact-us', 'Contact US'],
    ['/for-corporates', 'For Corporates Page B2B'], ['/blog', 'Blogs'],
];

class Banners extends Model
{
    protected static string $table = 'cloth_app_banners';
    protected static array $fields = ['id' => 'auto', 'image' => 'file', 'image_phone' => 'file', 'video' => 'file', 'image_alt_name' => 'str',
        'category_images_slider' => 'fk:Product_sub_category', 'show_on_pages' => 'csv'];
    protected static array $defaults = ['image' => '', 'image_phone' => '', 'video' => '', 'image_alt_name' => ''];
    public function __toString(): string { return 'Banner for: ' . implode(', ', $this->show_on_pages ?? []); }
}

class Insta_images extends Model
{
    protected static string $table = 'cloth_app_insta_images';
    protected static array $fields = ['id' => 'auto', 'image' => 'file'];
}

class Testimoninal extends Model
{
    protected static string $table = 'cloth_app_testimoninal';
    protected static array $fields = ['id' => 'auto', 'image' => 'file', 'image_alt_name' => 'str', 'name' => 'str', 'company_name' => 'str', 'content' => 'str', 'date' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['date'] = now_utc_str(); }
}

class Our_trusted_client extends Model
{
    protected static string $table = 'cloth_app_our_trusted_client';
    protected static array $fields = ['id' => 'auto', 'image' => 'file', 'image_alt_name' => 'str'];
}

class Leave_checkout_page_billing_details extends Model
{
    protected static string $table = 'cloth_app_leave_checkout_page_billing_details';
    protected static array $fields = ['id' => 'auto', 'first_name' => 'str', 'last_name' => 'str', 'phone' => 'str', 'email' => 'str', 'address' => 'text',
        'country' => 'str', 'landmark' => 'str', 'city' => 'str', 'pincode' => 'str', 'payment_method' => 'str', 'amount' => 'str', 'date' => 'datetime'];
    protected function beforeSave(bool $c): void { $this->attrs['date'] = now_utc_str(); }
}

class Chatbot_users extends Model
{
    protected static string $table = 'cloth_app_chatbot_users';
    protected static array $fields = ['id' => 'auto', 'name' => 'str', 'email' => 'str', 'phone' => 'str'];
    public function __toString(): string { return (string)($this->attrs['name'] ?? ''); }
}

class sub_category_coordinator extends Model
{
    protected static string $table = 'cloth_app_sub_category_coordinator';
    protected static array $fields = ['id' => 'auto', 'sub_category' => 'fk:Product_sub_category', 'name' => 'str', 'containt' => 'text', 'show_on_pages' => 'csv'];
    public function __toString(): string { return (string)($this->attrs['name'] ?? ''); }
}

/* ====================================================================== payment gateway */

class Merchant extends Model
{
    protected static string $table = 'cloth_app_merchant';
    protected static array $fields = ['id' => 'uuid', 'user' => 'fk:CustomUser', 'business_name' => 'str', 'merchant_vpa' => 'str', 'status' => 'str',
        'daily_amount_limit' => 'decimal:2', 'daily_transaction_limit' => 'int', 'priority' => 'int', 'created_at' => 'datetime'];
    protected static array $defaults = ['business_name' => 'US VESTA Merchant', 'merchant_vpa' => '', 'status' => 'ACTIVE',
        'daily_amount_limit' => '500000.00', 'daily_transaction_limit' => 1000, 'priority' => 1];
    protected function beforeSave(bool $c): void
    {
        if ($c && empty($this->attrs['id'])) $this->attrs['id'] = uuid_hex();
        if ($c) $this->attrs['created_at'] = now_utc_str();
    }
    public static function onDelete(Model $m): void
    {
        APIKey::filter(['merchant' => $m->pk()])->delete();
        PaymentOrder::filter(['merchant' => $m->pk()])->delete();
        WebhookEndpoint::filter(['merchant' => $m->pk()])->delete();
        AuditLog::filter(['merchant' => $m->pk()])->update(['merchant' => null]);
    }
    public function __toString(): string { return $this->raw('business_name') . ' (' . $this->raw('merchant_vpa') . ')'; }
}

class APIKey extends Model
{
    protected static string $table = 'cloth_app_apikey';
    protected static array $fields = ['id' => 'uuid', 'merchant' => 'fk:Merchant', 'mode' => 'str', 'public_key' => 'str', 'secret_key_hash' => 'str',
        'secret_key_preview' => 'str', 'is_active' => 'bool', 'created_at' => 'datetime'];
    protected static array $defaults = ['mode' => 'TEST', 'secret_key_hash' => '', 'secret_key_preview' => '', 'is_active' => 1];
    protected function beforeSave(bool $c): void
    {
        if ($c && empty($this->attrs['id'])) $this->attrs['id'] = uuid_hex();
        if ($c) $this->attrs['created_at'] = now_utc_str();
    }

    /** Key rotation: deactivates previous keys of that mode for the merchant. Returns [APIKey, rawSecret]. */
    public static function generate_key_pair(Merchant $merchant, string $mode = 'TEST'): array
    {
        self::filter(['merchant' => $merchant->pk(), 'mode' => $mode, 'is_active' => true])->update(['is_active' => false]);
        $suffix = uuid_hex();
        $pub = ($mode === 'TEST' ? 'pub_test_' : 'pub_live_') . substr($suffix, 0, 16);
        $secPrefix = $mode === 'TEST' ? 'sec_test_' : 'sec_live_';
        $raw = $secPrefix . $suffix;
        $key = self::create([
            'merchant' => $merchant, 'mode' => $mode, 'public_key' => $pub, 'secret_key_hash' => hash('sha256', $raw),
            'secret_key_preview' => $secPrefix . substr($suffix, 0, 6) . '...' . substr($suffix, -4), 'is_active' => true,
        ]);
        return [$key, $raw];
    }
    public function __toString(): string { return ($this->merchant?->raw('business_name') ?? '') . ' [' . $this->raw('mode') . '] - ' . $this->raw('public_key'); }
}

class PaymentOrder extends Model
{
    protected static string $table = 'cloth_app_paymentorder';
    protected static string $pk = 'payment_id';
    protected static array $fields = ['payment_id' => 'str', 'merchant' => 'fk:Merchant', 'mode' => 'str', 'order_id' => 'str', 'amount' => 'decimal:2',
        'currency' => 'str', 'status' => 'str', 'payment_provider' => 'str', 'provider_reference' => 'str', 'qr_payload' => 'text',
        'expires_at' => 'datetime', 'success_at' => 'datetime', 'failed_at' => 'datetime', 'created_at' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c) $this->attrs['created_at'] = now_utc_str(); }
    public function is_expired(): bool
    {
        if (in_array($this->raw('status'), ['SUCCESS', 'FAILED'], true)) return false;
        $e = $this->expires_at;
        return $e && time() > $e->getTimestamp();
    }
}

class WebhookEndpoint extends Model
{
    protected static string $table = 'cloth_app_webhookendpoint';
    protected static array $fields = ['id' => 'uuid', 'merchant' => 'fk:Merchant', 'target_url' => 'str', 'secret_key' => 'str', 'event_type' => 'str', 'is_active' => 'bool', 'created_at' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c && empty($this->attrs['id'])) $this->attrs['id'] = uuid_hex(); if ($c) $this->attrs['created_at'] = now_utc_str(); }
}

class WebhookDelivery extends Model
{
    protected static string $table = 'cloth_app_webhookdelivery';
    protected static array $fields = ['id' => 'uuid', 'webhook_endpoint' => 'fk:WebhookEndpoint', 'payment_order' => 'fk:PaymentOrder', 'event_type' => 'str',
        'request_payload' => 'json', 'response_status' => 'int', 'delivered' => 'bool', 'delivered_at' => 'datetime', 'attempts' => 'int', 'created_at' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c && empty($this->attrs['id'])) $this->attrs['id'] = uuid_hex(); if ($c) $this->attrs['created_at'] = now_utc_str(); }
}

class AuditLog extends Model
{
    protected static string $table = 'cloth_app_auditlog';
    protected static array $fields = ['id' => 'uuid', 'user' => 'fk:CustomUser', 'merchant' => 'fk:Merchant', 'action' => 'str', 'details' => 'json', 'ip_address' => 'str', 'created_at' => 'datetime'];
    protected function beforeSave(bool $c): void { if ($c && empty($this->attrs['id'])) $this->attrs['id'] = uuid_hex(); if ($c) $this->attrs['created_at'] = now_utc_str(); }
}

/* ====================================================================== social login */

class SocialAccount extends Model
{
    protected static string $table = 'socialaccount_socialaccount';
    protected static array $fields = ['id' => 'auto', 'provider' => 'str', 'uid' => 'str', 'last_login' => 'datetime', 'date_joined' => 'datetime', 'user' => 'fk:CustomUser', 'extra_data' => 'json'];
}

/* ====================================================================== CMS */

class HomePageEditor extends Model
{
    protected static string $table = 'cloth_app_homepageeditor';
    public const IMAGE_FIELDS = ['header_logo', 'footer_logo', 'hero_bg_image', 'fit_hero_image', 'fit_section_bg_image', 'fit_img1', 'fit_img2', 'fit_img3',
        'fit_img5', 'fit_img6', 'grid_tile1_image', 'grid_tile2_image', 'grid_tile3_image', 'grid_tile4_image', 'mobile_header_logo', 'hero_bg_mobile_image',
        'section3_card1_image', 'section3_card2_image', 'section3_card3_image', 'section3_card4_image', 'section3_card5_image',
        'mega_card1_image', 'mega_card2_image', 'mega_card3_image', 'mega_card4_image', 'mega_card5_image'];
    public const BOOL_FIELDS = ['section4_slider_enabled', 'section4_slider_desktop_enabled', 'section4_slider_mobile_enabled', 'enable_country_modal',
        'announcement_enabled', 'section2_enabled', 'section3_enabled', 'section3_desktop_enabled', 'section3_mobile_enabled', 'section4_enabled',
        'section5_enabled', 'section6_enabled', 'women_coming_soon_enabled'];
    public const JSON_FIELDS = ['header_nav_json', 'mobile_nav_json', 'footer_nav_json', 'mega_menu_cards_json', 'mega_menu_featured_links_json', 'custom_links_json'];
    public const FLOAT_FIELDS = ['usd_conversion_rate', 'eur_conversion_rate', 'gbp_conversion_rate', 'aed_conversion_rate'];

    protected static array $fields = [];
    protected static array $defaults = [
        'header_announcement_text' => 'FREE SHIPPING ON ALL ORDERS OVER ₹999',
        'footer_tagline' => 'Thoughtfully designed and built to endure. US Vesta merges modern luxury with functional simplicity.',
        'footer_facebook_url' => 'https://www.facebook.com/profile.php?id=61581390461341', 'footer_instagram_url' => 'https://www.instagram.com/usvesta25/',
        'footer_linkedin_url' => 'https://www.linkedin.com/company/us-vesta/', 'footer_copyright' => '© 2013 — 2026 US Vesta. Designed for modern living. All rights reserved.',
        'hero_subtitle' => 'THE STREET IS OUR STAGE', 'hero_title' => 'COLLECTIVE DROP', 'hero_description' => 'EXPLORE THE UNTAMED.',
        'hero_button_text' => 'COLLECTION', 'hero_button_link' => '/shop', 'fit_section_badge' => 'US VESTA | NEW ARRIVAL',
        'fit_card1_title' => 'MULTI-DIMENSIONAL FIT', 'fit_card1_desc' => 'HEAVYWEIGHT 300 GSM COTTON. TAILORED DITTO STRUCTURE.',
        'fit_card2_title' => 'ENGINEERED COMFORT', 'fit_card2_desc' => 'BREATHABLE WEAVE FOR ALL-DAY URBAN MOBILITY.',
        'section4_slider_enabled' => 1, 'section4_slider_desktop_enabled' => 1, 'section4_slider_mobile_enabled' => 1, 'section4_slider_mobile_item_count' => 4,
        'section4_slider_title' => 'FEATURED PRODUCTS', 'enable_country_modal' => 1, 'usd_conversion_rate' => 0.012, 'eur_conversion_rate' => 0.011,
        'gbp_conversion_rate' => 0.0095, 'aed_conversion_rate' => 0.044,
        'grid_tile1_link' => '/shop', 'grid_tile2_link' => '/shop', 'grid_tile3_link' => '/shop', 'grid_tile4_link' => '/shop',
        'grid_center_button_text' => 'COLLECTIONS →', 'grid_center_button_link' => '/shop',
        'hero_title_font_size' => 'clamp(2rem, 4.2vw, 3.6rem)', 'hero_title_color' => '#ffffff', 'hero_title_font_family' => "'Playfair Display', serif",
        'hero_subtitle_font_size' => 'clamp(0.85rem, 1.4vw, 1.1rem)', 'hero_subtitle_color' => '#ffffff',
        'announcement_enabled' => 1, 'announcement_bg_color' => '#0d0d0d', 'announcement_text_color' => '#ffffff', 'announcement_font_size' => '0.75rem', 'announcement_font_weight' => '600',
        'logo_width' => '140px', 'logo_height' => 'auto', 'logo_link' => '/', 'header_bg_color' => '#ffffff', 'header_text_color' => '#000000',
        'mobile_header_bg_color' => '#ffffff', 'mobile_header_text_color' => '#0d0d0d',
        'hero_title_font_weight' => '500', 'hero_description_font_size' => 'clamp(0.8rem, 1.3vw, 1.05rem)', 'hero_description_color' => '#ffffff',
        'hero_button_bg_color' => '#c5a059', 'hero_button_text_color' => '#ffffff', 'hero_overlay_opacity' => '0.3',
        'section2_enabled' => 1, 'section3_enabled' => 1, 'section3_desktop_enabled' => 1, 'section3_mobile_enabled' => 0,
        'section3_card1_title' => 'Fears Heavyweight', 'section3_card1_price' => '₹ 1199', 'section3_card1_desc' => '300 GSM cotton | Limited run', 'section3_card1_link' => '/shop',
        'section3_card2_title' => 'Print Gprint', 'section3_card2_price' => '₹ 1199', 'section3_card2_desc' => '300 GSM cotton | Limited run', 'section3_card2_link' => '/shop',
        'section3_card3_title' => 'Maroon Oversized', 'section3_card3_price' => '₹ 1199', 'section3_card3_desc' => '300 GSM cotton | Limited run', 'section3_card3_link' => '/shop',
        'section3_card4_title' => 'Rhythmic Resonance', 'section3_card4_price' => '₹ 1199', 'section3_card4_desc' => '300 GSM cotton | Limited run', 'section3_card4_link' => '/shop',
        'section3_card5_title' => 'US VESTA Essential', 'section3_card5_price' => '₹ 1199', 'section3_card5_desc' => '300 GSM cotton | Limited run', 'section3_card5_link' => '/shop',
        'section4_enabled' => 1, 'section5_enabled' => 1, 'section6_enabled' => 1, 'fit_section_bg_color' => '#0d0d0d',
        'grid_tile1_alt' => 'Make Dope Stuff Tee', 'grid_tile2_alt' => 'Black Joker Graphic Tee', 'grid_tile3_alt' => 'Black Panda Polo Shirt', 'grid_tile4_alt' => 'Black Fears Biker Graphic Tee',
        'grid_center_button_bg' => 'rgba(255, 255, 255, 0.15)', 'grid_center_button_color' => '#ffffff',
        'footer_logo_height' => '45px', 'footer_bg_color' => '#0d0d0d', 'footer_text_color' => '#ffffff', 'footer_youtube_url' => '', 'footer_twitter_url' => '',
        'header_nav_json' => '[]', 'mobile_nav_json' => '[]', 'footer_nav_json' => '[]', 'mega_menu_cards_json' => '{}', 'mega_menu_featured_links_json' => '[]',
        'express_shipping_title' => 'EXPRESS SHIPPING', 'express_shipping_subtitle' => 'Complimentary priority delivery on orders over ₹1,999.', 'express_shipping_icon' => 'fa-truck-fast',
        'women_coming_soon_enabled' => 1, 'custom_links_json' => '{}',
    ];

    public static function fields(): array
    {
        if (static::$fields) return static::$fields;
        $f = ['id' => 'auto', 'updated_at' => 'datetime', 'section4_slider_mobile_item_count' => 'int'];
        foreach (array_keys(static::$defaults) as $k) $f[$k] ??= 'str';
        foreach (['fit_card1_desc', 'fit_card2_desc', 'footer_tagline'] as $k) $f[$k] = 'text';
        foreach (self::IMAGE_FIELDS as $k) $f[$k] = 'file';
        foreach (self::BOOL_FIELDS as $k) $f[$k] = 'bool';
        foreach (self::JSON_FIELDS as $k) $f[$k] = 'json';
        foreach (self::FLOAT_FIELDS as $k) $f[$k] = 'float';
        return static::$fields = $f;
    }

    public function __construct(array $a = []) { static::fields(); parent::__construct($a); }
    public static function hydrate(array $row): static { static::fields(); return parent::hydrate($row); }
    protected function beforeSave(bool $c): void { $this->attrs['updated_at'] = now_utc_str(); }

    public static function current(): self
    {
        static::fields();
        $cms = self::objects()->first();
        return $cms ?: self::create([]);
    }
}

Registry::register(
    'CustomUser', 'Product_category', 'Product_sub_category', 'Product', 'Hightlight_for_product', 'Color_product',
    'color_product_Gelery_images', 'product_Gelery_images', 'Size_price_par_post', 'Cart', 'Wishlist', 'Address_user', 'Order',
    'Return_and_Cancel_products', 'User_Reviews', 'Contact_US', 'Blog_Category', 'Blog_post', 'A_plus_listing', 'Banners',
    'Insta_images', 'Testimoninal', 'Our_trusted_client', 'Leave_checkout_page_billing_details', 'Chatbot_users',
    'sub_category_coordinator', 'Merchant', 'APIKey', 'PaymentOrder', 'WebhookEndpoint', 'WebhookDelivery', 'AuditLog', 'HomePageEditor', 'SocialAccount'
);
