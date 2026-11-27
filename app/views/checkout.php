<?php
/** Checkout & payments – port of Checkout, Payment_success, create_cashfree_order, create_veno_order, Make_design(_polo) */

final class Pricing
{
    /** Same rule as static/js/cart.js: qty >= offer_qty -> qty * (offer_amount / offer_qty), else qty * int(price). */
    public static function line(?Product $p, int $qty): float
    {
        if (!$p) return 0.0;
        $qty = max(1, $qty);
        $price = is_numeric($p->raw('b2c_price')) ? (int)(float)$p->raw('b2c_price') : 0;
        $oq = (int)($p->raw('product_quantity_offer') ?? 0);
        $oa = is_numeric($p->raw('product_amount_offer')) ? (float)$p->raw('product_amount_offer') : 0.0;
        if ($oq > 0 && $oa > 0 && $qty >= $oq) return round($qty * ($oa / $oq), 2);
        return (float)($qty * $price);
    }

    public static function cartItem(Cart $c): float
    {
        $p = $c->product ?: $c->inner_products_card?->product;
        return self::line($p, (int)$c->raw('quantity'));
    }

    public static function cartTotal(CustomUser $u): float
    {
        $t = 0.0;
        foreach (Cart::filter(['user' => $u->pk()]) as $c) $t += self::cartItem($c);
        return round($t, 2);
    }
}

final class Payments
{
    /** Razorpay: payment must exist and be authorized/captured. */
    public static function razorpayOk(?string $paymentId): bool
    {
        if (!$paymentId) return false;
        $id = config('razorpay.key_id'); $sec = config('razorpay.key_secret');
        if (!$id || !$sec) return false;
        try {
            $r = Http::get('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId), ['Authorization' => 'Basic ' . base64_encode("$id:$sec")], 12);
            return in_array($r['json']['status'] ?? '', ['captured', 'authorized'], true);
        } catch (Throwable $e) { log_error('razorpay verify: ' . $e->getMessage()); return false; }
    }

    private static function cashfreeHeaders(): array
    {
        return ['x-client-id' => (string)config('cashfree.app_id'), 'x-client-secret' => (string)config('cashfree.secret_key'), 'x-api-version' => '2023-08-01'];
    }
    public static function cashfreeBase(): string
    {
        return str_starts_with((string)config('cashfree.app_id'), 'TEST') ? 'https://sandbox.cashfree.com/pg/orders' : 'https://api.cashfree.com/pg/orders';
    }
    /** Cashfree: order_status must be PAID. */
    public static function cashfreeOk(?string $orderId): bool
    {
        if (!$orderId || !config('cashfree.app_id')) return false;
        try {
            $r = Http::get(self::cashfreeBase() . '/' . rawurlencode($orderId), self::cashfreeHeaders(), 12);
            return ($r['json']['order_status'] ?? '') === 'PAID';
        } catch (Throwable $e) { log_error('cashfree verify: ' . $e->getMessage()); return false; }
    }
    public static function cashfreeCreate(array $payload): array
    {
        return Http::postJson(self::cashfreeBase(), $payload, self::cashfreeHeaders(), 10);
    }
}

function selected_address(Request $r, CustomUser $u): ?Address_user
{
    $id = $r->post('address_id') ?: $r->post('address_ids');
    return $id ? Address_user::filter(['id' => $id, 'user' => $u->pk()])->first() : null;
}

final class Checkout
{
    public function get(Request $r): Response
    {
        $u = Auth::user();
        $main = null; $inner = null; $size = null; $amount = 0; $qty = 1; $cart = null;
        $enc = $r->get('data');
        if ($enc) {
            $d = json_decode((string)base64_decode(strtr($enc, ' ', '+')), true) ?: [];
            $size = $d['p_size'] ?? null;
            $qty = $d['quent'] ?? 1;
            $q = is_numeric($qty) ? (float)$qty : 1;
            if (!empty($d['inner_product'])) {
                $inner = Color_product::find($d['inner_product']);
                if ($inner) $amount = new Decimal(number_format($q * (float)$inner->product?->raw('b2c_price'), 2, '.', ''));
            } elseif (!empty($d['product_id_p'])) {
                $main = Product::find($d['product_id_p']);
                if ($main) $amount = new Decimal(number_format($q * (float)$main->raw('b2c_price'), 2, '.', ''));
            }
        } else {
            $cart = Cart::filter(['user' => $u->pk()]);
            $total = 0;
            foreach ($cart as $i) $total += (int)$i->total_price() + 0;
            $totalInner = 0;
            foreach ($cart as $i) $totalInner += $i->total_inner_product_price();
            if ((int)$totalInner + (int)$total < 1) {
                msg_error('Cart is empty lor amount is too low to process.');
                return redirect('/');
            }
        }
        $amt = $amount instanceof Decimal ? (float)$amount->v : (float)$amount;
        return render($r, 'checkout.html', [
            'cart_products' => $cart,
            'key_id' => config('razorpay.key_id'),
            'product_main' => $main,
            'product_inner' => $inner,
            'amount' => $amount,
            'quanityt' => $qty,
            'size' => $size,
            'totalfinal' => (int)$amt * (int)(is_numeric($qty) ? $qty : 1),
            'user_address' => Address_user::filter(['user' => $u->pk()])->order_by('-id'),
        ]);
    }

    public function post(Request $r): Response
    {
        $u = Auth::user();
        $f = fn($k) => $r->post($k);

        // 1) Save a new address (htmx partial response)
        if ($f('fullname') && $f('landmark') && $f('phone_nummber') && $f('email_if_in_addres') && $f('state') && $f('zipcode') && $f('address')) {
            $new = Address_user::create([
                'user' => $u->pk(), 'name' => $f('fullname'), 'email' => $f('email_if_in_addres'), 'state' => $f('state'),
                'phone_numer' => $f('phone_nummber'), 'pin_code' => $f('zipcode'), 'landmark' => $f('landmark'), 'user_address' => $f('address'),
            ]);
            $list = Address_user::filter(['user' => $u->pk()])->order_by('-id');
            return render($r, $f('phone_addresS') ? 'phone_address_list.html' : 'address_list.html', ['user_address' => $list, 'new_id' => $new->pk()]);
        }

        // 2) Place order (COD, or after an online payment that we verify with the gateway)
        $qty = max(1, (int)($f('totalquantity') ?: 1));
        $rzpOrder = $f('razorpay_order_id'); $rzpPay = $f('razorpay_payment_id');
        $cfOrder = $f('chashfree_payment_id_usingdecstop'); $cfPay = $f('chashfree_order_id_usingdecstop');
        $txn = $cfPay ?: ($rzpPay ?: '');
        $txnOrder = $cfOrder ?: ($rzpOrder ?: '');
        $paid = false;
        if ($rzpPay) $paid = Payments::razorpayOk($rzpPay);
        elseif ($cfOrder) $paid = Payments::cashfreeOk($cfOrder);
        $payStatus = $paid ? 'paid' : 'Pending';
        $payMethod = $paid ? 'Online' : 'Cash On Delivery';

        $addr = selected_address($r, $u);
        $base = ['user' => $u->pk(), 'product_size' => (string)$f('product_size'), 'transaction_id' => $txn, 'transaction_order_id' => $txnOrder,
            'payment_status' => $payStatus, 'payment_method' => $payMethod];

        if ($f('product_id') && $addr) {
            $p = Product::find($f('product_id')) ?? throw new Http404();
            Order::create($base + ['product' => $p->pk(), 'quantity' => (string)$qty, 'order_id' => uuid4(),
                'total_price' => Pricing::line($p, $qty), 'address' => $addr->asOrderAddress()]);
        } elseif ($f('inner_product_id') && $addr) {
            $ip = Color_product::find($f('inner_product_id')) ?? throw new Http404();
            Order::create($base + ['inner_color_product' => $ip->pk(), 'quantity' => (string)$qty, 'order_id' => uuid4(),
                'total_price' => Pricing::line($ip->product, $qty), 'address' => $addr->asOrderAddress()]);
        } elseif ($f('product_card_wala_he') !== null && $addr) {
            foreach (Cart::filter(['user' => $u->pk()])->get() as $c) {
                Order::create(array_merge($base, [
                    'product' => $c->raw('product_id'), 'inner_color_product' => $c->raw('inner_products_card_id'),
                    'product_size' => (string)$c->raw('size'), 'quantity' => (string)$c->raw('quantity'), 'order_id' => uuid4(),
                    'total_price' => Pricing::cartItem($c), 'address' => $addr->asOrderAddress(),
                ]));
            }
            Cart::filter(['user' => $u->pk()])->delete();
        } elseif (!$addr) {
            msg_error('Please select a delivery address.');
            return redirect_back($r);
        }
        msg_success('Order placed successfully!');
        return redirect('/thank-you');
    }
}

/** Creates "Pending" orders before redirecting to an online gateway; returns the server-side amount to charge. */
function create_online_order_in_db(Request $r, string $orderId, bool $dryRun = false): float
{
    $u = Auth::user();
    if (!($u instanceof CustomUser)) return 0.0;
    $addr = selected_address($r, $u) ?? Address_user::filter(['user' => $u->pk()])->first();
    $addrStr = $addr ? $addr->asOrderAddress() : '';
    $size = $r->post('product_size') ?: 'M';
    $qty = max(1, (int)($r->post('totalquantity') ?: 1));
    $base = ['user' => $u->pk(), 'transaction_id' => $orderId, 'transaction_order_id' => $orderId, 'payment_status' => 'Pending',
        'payment_method' => 'Online', 'status' => 'Pending', 'address' => $addrStr];

    if (!blank($r->post('product_id'))) {
        $p = Product::find($r->post('product_id'));
        if (!$p) return 0.0;
        $amt = Pricing::line($p, $qty);
        if (!$dryRun) Order::create($base + ['product' => $p->pk(), 'product_size' => $size, 'quantity' => (string)$qty, 'order_id' => uuid4(), 'total_price' => $amt]);
        return $amt;
    }
    if (!blank($r->post('inner_product_id'))) {
        $ip = Color_product::find($r->post('inner_product_id'));
        if (!$ip) return 0.0;
        $amt = Pricing::line($ip->product, $qty);
        if (!$dryRun) Order::create($base + ['inner_color_product' => $ip->pk(), 'product_size' => $size, 'quantity' => (string)$qty, 'order_id' => uuid4(), 'total_price' => $amt]);
        return $amt;
    }
    $total = 0.0;
    foreach (Cart::filter(['user' => $u->pk()])->get() as $c) {
        $line = Pricing::cartItem($c);
        $total += $line;
        if (!$dryRun) Order::create(array_merge($base, [
            'product' => $c->raw('product_id'), 'inner_color_product' => $c->raw('inner_products_card_id'), 'product_size' => $c->raw('size') ?: 'M',
            'quantity' => (string)($c->raw('quantity') ?: 1), 'order_id' => uuid4(), 'total_price' => $line,
        ]));
    }
    return round($total, 2);
}

function customer_phone(CustomUser|AnonymousUser $u, Request $r): string
{
    if ($u instanceof CustomUser) {
        $a = selected_address($r, $u);
        foreach ([$u->raw('phone'), $a?->raw('phone_numer')] as $p) {
            $d = preg_replace('/\D/', '', (string)$p);
            if (strlen($d) >= 10) return substr($d, -10);
        }
    }
    return '9876543210';
}

final class create_cashfree_order
{
    public function post(Request $r): Response
    {
        $u = Auth::user();
        if (!($u instanceof CustomUser)) return json(['status' => 'error', 'message' => 'Please login to continue.'], 401);
        $orderId = 'ORDER_' . strtoupper(substr(uuid_hex(), 0, 12));
        $amount = create_online_order_in_db($r, $orderId, true);
        if ($amount < 1) return json(['status' => 'error', 'message' => 'Nothing to pay for.'], 400);
        $payload = [
            'order_id' => $orderId, 'order_amount' => round($amount, 2), 'order_currency' => 'INR',
            'customer_details' => ['customer_id' => 'cust_' . $u->pk(), 'customer_name' => (string)$u->raw('username'),
                'customer_email' => $u->raw('email') ?: 'customer@usvesta.com', 'customer_phone' => customer_phone($u, $r)],
            'order_meta' => new stdClass(),
        ];
        try {
            $res = Payments::cashfreeCreate($payload);
            $d = $res['json'] ?? [];
            if (in_array($res['status'], [200, 201], true) && isset($d['payment_session_id'])) {
                create_online_order_in_db($r, $orderId);
                return json(['status' => 'success', 'payment_session_id' => $d['payment_session_id'], 'order_id' => $orderId,
                    'checkout_url' => 'https://payments.cashfree.com/order/#/' . $d['payment_session_id']]);
            }
            return json(['status' => 'error', 'message' => $d['message'] ?? 'Unable to create Cashfree order'], 400);
        } catch (Throwable $e) {
            return json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

final class create_veno_order
{
    public function post(Request $r): Response
    {
        $u = Auth::user();
        if (!($u instanceof CustomUser)) return json(['status' => 'error', 'message' => 'Please login to continue.'], 401);
        $orderId = 'ORDER_PY_' . strtoupper(substr(uuid_hex(), 0, 8));
        $amount = create_online_order_in_db($r, $orderId, true);
        if ($amount < 1) return json(['status' => 'error', 'message' => 'Nothing to pay for.'], 400);

        $redirect = $r->build_absolute_uri('/payment/success/') . '?order_id=' . $orderId;
        if (str_starts_with($redirect, 'http://') && !preg_match('/127\.0\.0\.1|localhost/', $redirect)) $redirect = 'https://' . substr($redirect, 7);
        $cms = HomePageEditor::current();
        $brand = 'US VESTA';
        $logo = $cms->footer_logo->name !== '' ? $r->build_absolute_uri($cms->footer_logo->url()) : ($cms->header_logo->name !== '' ? $r->build_absolute_uri($cms->header_logo->url()) : $r->build_absolute_uri('/static/images/logo2.png'));
        if (preg_match('/127\.0\.0\.1|localhost/', $logo)) $logo = 'https://usvesta.com/static/images/logo2.png';
        elseif (str_starts_with($logo, 'http://')) $logo = 'https://' . substr($logo, 7);

        $payload = ['amount' => number_format($amount, 2, '.', ''), 'order_id' => $orderId, 'currency' => 'INR',
            'customer_email' => $u->raw('email') ?: 'customer@example.com', 'customer_phone' => customer_phone($u, $r), 'redirect_url' => $redirect,
            'business_name' => $brand, 'merchant_name' => $brand, 'brand_name' => $brand, 'logo_url' => $logo];
        try {
            $res = Http::postJson((string)config('veno.gateway_url'), $payload, ['x-api-key' => (string)config('veno.master_api_key')], 12);
            $d = $res['json'] ?? [];
            if (in_array($res['status'], [200, 201], true) || !empty($d['checkout_url'])) {
                create_online_order_in_db($r, $orderId);
                $_SESSION['veno_payments'][$orderId] = $d['payment_id'] ?? null;
                return json(['status' => 'success', 'checkout_url' => $d['checkout_url'] ?? null, 'payment_id' => $d['payment_id'] ?? null, 'order_id' => $orderId]);
            }
            return json(['status' => 'error', 'message' => $d['message'] ?? $d['error'] ?? 'Veno Gateway Order Creation Failed'], 400);
        } catch (Throwable $e) {
            return json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

final class Payment_success
{
    public function get(Request $r): Response
    {
        $orderId = (string)$r->get('order_id', '');
        $verified = false;
        if ($orderId !== '') {
            if (str_starts_with($orderId, 'ORDER_PY_')) {
                // Veno/Payzen has no verification API wired up: accept only for orders created in this session.
                $verified = array_key_exists($orderId, $_SESSION['veno_payments'] ?? []);
            } else {
                $verified = Payments::cashfreeOk($orderId);
            }
            if ($verified) {
                $ids = Order::filter(['transaction_order_id' => $orderId])->values_list('id');
                Order::filter(['transaction_order_id' => $orderId])->update(['payment_status' => 'paid', 'status' => 'Ordered', 'updated_at' => now_utc_str()]);
                foreach ($ids as $id) after_response(fn() => Delhivery::createForOrder((int)$id));
                unset($_SESSION['veno_payments'][$orderId]);
            }
        }
        $u = Auth::user();
        if ($u instanceof CustomUser && ($verified || $orderId === '')) Cart::filter(['user' => $u->pk()])->delete();
        if ($verified || $orderId === '') msg_success('Payment successful! Your order has been placed.');
        else msg_error('We could not confirm your payment yet. If money was debited, please contact us with order id ' . $orderId . '.');
        return redirect('/thank-you');
    }

    public function post(Request $r): Response
    {
        try { $d = $r->json(); } catch (Throwable) { return json(['error' => 'Invalid JSON'], 400); }
        $rzpOrder = $d['razorpay_order_id'] ?? null; $rzpPay = $d['razorpay_payment_id'] ?? null;
        $amount = ((int)($d['amount'] ?? 0)) / 100;
        $pid = $d['product_id'] ?? null;
        if (!$pid) return json(['error' => 'Product ID is missing'], 400);
        $u = Auth::user();
        if (!($u instanceof CustomUser)) return json(['error' => 'User not authenticated'], 401);
        $p = Product::find($pid);
        if (!$p) return json(['error' => 'Product not found'], 404);
        $addr = Address_user::filter(['user' => $u->pk()])->first();
        if (!$addr) return json(['error' => 'No address found for user'], 404);
        if (!Payments::razorpayOk($rzpPay)) return json(['error' => 'Payment could not be verified'], 402);
        $o = Order::create(['user' => $u->pk(), 'product' => $p->pk(), 'quantity' => '1', 'total_price' => $amount, 'order_id' => $rzpOrder ?: uuid4(),
            'transaction_order_id' => $rzpOrder, 'transaction_id' => $rzpPay, 'payment_status' => 'paid', 'payment_method' => 'Online',
            'status' => 'Ordered', 'address' => $addr->asOrderAddress()]);
        return json(['message' => 'Payment successful', 'order_id' => $o->pk()]);
    }
}

class Make_design
{
    protected string $template = '3dmokup.html';

    public function get(Request $r): Response
    {
        $pid = $r->get('product-id');
        $iid = $r->get('inner-product-id');
        return render($r, $this->template, [
            'product_main' => $pid ? Product::find($pid) : null,
            'price' => $r->get('product-price'),
            'inner_product' => $iid ? color_product_Gelery_images::find($iid) : null,
            'product_id' => $pid,
            'product_id_inner' => $iid,
            'key_id' => config('razorpay.key_id'),
        ]);
    }

    public function post(Request $r): Response
    {
        $u = Auth::user();
        $pdf = $r->file('pdf_design');
        $name = $r->post('customer_name'); $email = $r->post('emailid'); $phone = $r->post('phonenumber');
        $qty = $r->post('quantity'); $address = $r->post('address'); $msg = $r->post('meseg');
        $amount = $r->post('totalAmount');
        $rzpOrder = $r->post('razorpay_order_id'); $rzpPay = $r->post('razorpay_payment_id');
        if (!($pdf && $name && $email && $phone && $qty && $address)) return http('Please Try Again');
        $paid = $rzpPay && Payments::razorpayOk($rzpPay);
        $design = Storage::save($pdf, 'customdesign', IMG_DEFAULT) ?? '';
        $base = ['user' => $u->pk(), 'custom_name' => $name, 'custom_email' => $email, 'custom_number' => $phone, 'custom_design' => $design,
            'quantity' => (string)$qty, 'total_price' => is_numeric($amount) ? $amount : 0, 'address' => $address, 'messag' => $msg,
            'transaction_id' => $rzpPay ?: '', 'transaction_order_id' => $rzpOrder ?: '',
            'payment_status' => $paid ? 'paid' : 'Pending', 'payment_method' => $paid ? 'Online' : 'Cash On Delivery'];
        if ($pid = $r->post('proid')) {
            if ($p = Product::find($pid)) { Order::create($base + ['product' => $p->pk()]); return redirect('/profile'); }
        }
        if ($iid = $r->post('product_id_inner')) {
            $g = color_product_Gelery_images::find($iid);
            Order::create($base + ['inner_color_product' => $g?->raw('color_product_id')]);
            return redirect('/profile');
        }
        return http('Please Try Again');
    }
}

final class Make_design_polo extends Make_design { protected string $template = 'kolar3d.html'; }
