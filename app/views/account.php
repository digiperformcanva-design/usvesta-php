<?php
/** Accounts – port of Register_and_login, Logout, Reset_password, Profile, Retun_and_cancel, Order_details_pg + allauth Google login */

final class Register_and_login
{
    public function get(Request $r): Response
    {
        if (Auth::check()) return redirect($r->referer('/'));
        return render($r, 'login-register.html');
    }

    public function post(Request $r): Response
    {
        $phone = trim((string)$r->post('l_phone'));
        $otp = $r->post('otp_user') ?? $r->post('otp');
        if ($phone !== '') {
            [$u] = CustomUser::get_or_create(['phone' => $phone], ['username' => $phone]);
            $code = (string)random_int(100000, 999999);
            $u->phone_number_otp = $code;
            $u->otp_created_at = now_utc_str();
            $u->save();
            $_SESSION['phone'] = $phone;
            unset($_SESSION['otp_fails']);
            self::sendOtp($phone, $code);
            return json(['res' => 'OTP Send Successfully']);
        }
        if ($otp) {
            $phone = $_SESSION['phone'] ?? null;
            if (!$phone) return json(['res' => 'Session expired, please resend OTP']);
            $u = CustomUser::filter(['phone' => $phone])->first();
            if ($u && $u->raw('phone_number_otp') && hash_equals((string)$u->raw('phone_number_otp'), (string)$otp) && $u->is_otp_valid()) {
                unset($_SESSION['otp_fails']);
                $u->phone_number_otp = null;
                $u->save();
                Auth::login($u);
                msg_success('Login successful!');
                return json(['status' => 200]);
            }
            $_SESSION['otp_fails'] = ($_SESSION['otp_fails'] ?? 0) + 1;
            if ($_SESSION['otp_fails'] >= 5 && $u) {   // too many wrong guesses: burn the code
                CustomUser::updateWhere(['id' => $u->pk()], ['phone_number_otp' => null]);
                unset($_SESSION['otp_fails']);
            }
            return json(['res' => 'Invalid OTP or OTP expired']);
        }
        return json(['res' => 'Invalid request'], 400);
    }

    /** MSG91 flow API (configure msg91.auth_key + msg91.flow_id). */
    private static function sendOtp(string $phone, string $code): void
    {
        $key = config('msg91.auth_key'); $flow = config('msg91.flow_id');
        if (!$key || !$flow || $flow === 'YOUR_MSG91_FLOW_ID') { log_error("OTP for $phone not sent (MSG91 flow not configured)"); return; }
        $mobile = preg_replace('/\D/', '', $phone);
        if (strlen($mobile) === 10) $mobile = '91' . $mobile;
        try {
            Http::postJson('https://control.msg91.com/api/v5/flow', ['template_id' => $flow, 'short_url' => '0', 'recipients' => [['mobiles' => $mobile, 'otp' => $code]]],
                ['authkey' => $key, 'accept' => 'application/json'], 10);
        } catch (Throwable $e) { log_error('MSG91: ' . $e->getMessage()); }
    }
}

final class Logout
{
    public function get(Request $r): Response
    {
        Auth::logout();
        return redirect('/');
    }

    /** "Forgot password" (JSON body {reset_email}) */
    public function post(Request $r): Response
    {
        try { $d = $r->json(); } catch (Throwable) { $d = $r->POST; }
        $email = $d['reset_email'] ?? null;
        if (!$email) return json(['status' => 500]);
        $u = CustomUser::filter(['username' => $email])->first();
        if (!$u) return json(['status' => 400, 'mesej' => 'User Not Found !']);
        $u->password_reset_requested_at = now_utc_str();
        $u->save();
        $link = absolute_url('reset-passsword/' . $u->raw('email_token'));
        $msg = "Welcome To US Vesta\nThank You {$u->raw('first_name')}\nClick this link to reset your password: $link\n(This link is valid for 30 minutes.)";
        Mailer::send('Reset Your Password', $msg, $email);
        return json(['status' => 200, 'mesej' => 'We Sent Forget Paasword Link On Your Email']);
    }
}

final class Reset_password
{
    private const VALID_MINUTES = 30;

    private static function expired(CustomUser $u): bool
    {
        $t = $u->password_reset_requested_at;
        // a link is only valid within 30 minutes of an actual reset request
        return !($t instanceof DateTimeInterface) || time() - $t->getTimestamp() > self::VALID_MINUTES * 60;
    }

    public function get(Request $r, string $token): Response
    {
        $u = CustomUser::filter(['email_token' => $token])->first();
        if (!$u) return http('Invalide Reset Password Link');
        if (self::expired($u)) return http('This reset password link has expired.');
        return render($r, 'resetpassword.html', ['token_res' => 'Make A Strong Password']);
    }

    public function post(Request $r, string $token): Response
    {
        $u = CustomUser::filter(['email_token' => $token])->first();
        $pwd = (string)$r->post('new_password');
        if (!$u) { msg_success('Invalid Request.'); return redirect_back($r); }
        if (self::expired($u)) return http('This reset password link has expired.');
        if (strlen($pwd) < 8) { msg_error('Password must be at least 8 characters.'); return redirect($r->path); }
        $u->set_password($pwd);
        $u->email_token = uuid4();
        $u->decrypt_password = null;   // never store plain-text passwords
        $u->password_reset_requested_at = null;
        $u->save();
        msg_success('Password Reset Successfully.');
        return redirect('/');
    }
}

final class Profile
{
    public function get(Request $r): Response
    {
        $u = Auth::user();
        $addr = Address_user::filter(['user' => $u->pk()]);
        $orders = Order::filter(['user' => $u->pk()])->exclude(['status' => 'Pending'])->order_by('-id')->slice(0, 10);
        $ids = $orders->values_list('id');
        if ($ids) after_response(fn() => Delhivery::refreshOrders($ids));
        return render($r, 'my-account.html', [
            'orders_of_user' => $orders,
            'address_of_user' => $addr->first(),
            'address_of_user_list' => $addr,
            'active_tab' => $r->get('tab', ''),
        ]);
    }

    public function post(Request $r): Response
    {
        $u = Auth::user();
        if ($r->has('full_name')) $u->first_name = (string)$r->post('full_name');
        if ($r->has('email')) $u->email = (string)$r->post('email');
        if ($r->has('phone')) {
            $ph = trim((string)$r->post('phone'));
            if ($ph === '') $u->phone = null;
            elseif (CustomUser::filter(['phone' => $ph])->exclude(['id' => $u->pk()])->exists()) { msg_error('This phone number is already used by another account.'); return redirect('/profile'); }
            else $u->phone = $ph;
        }
        if ($r->has('about')) $u->about = (string)$r->post('about');
        $pwd = (string)$r->post('new_pwd', '');
        if ($pwd !== '') $u->set_password($pwd);
        if ($f = $r->file('profile_image')) {
            $name = Storage::save($f, 'user_profile', IMG_DEFAULT);
            if ($name) $u->profile_image = $name;
        }
        $u->save();
        msg_success('Profile Update SuccessFully');
        return redirect('/profile');
    }
}

final class Retun_and_cancel
{
    public function post(Request $r): Response
    {
        $u = Auth::user();
        if (!($u instanceof CustomUser)) return redirect('/login-register?next=/profile');
        $retId = $r->post('retun_model'); $retReason = $r->post('retun_selectreason'); $retMsg = $r->post('retun_message');
        $canId = $r->post('cancel_order_id'); $canReason = $r->post('cancel_reason'); $canMsg = $r->post('cancel_message');

        if ($retId && $retReason && $retMsg) {
            $od = Order::find($retId) ?? throw new Http404();
            if ($od->raw('user_id') != $u->pk()) { msg_error('Unauthorized action'); return redirect('/profile'); }
            if (Return_and_Cancel_products::filter(['orders' => $od->pk()])->exists()) { msg_success('Return request already submitted'); return redirect('/profile'); }
            $od->status = 'Return in process';
            $od->save();
            $img = ($f = $r->file('return_image')) ? Storage::save($f, 'retun_p_image', IMG_DEFAULT) : '';
            Return_and_Cancel_products::create(['user' => $u->pk(), 'orders' => $od->pk(), 'product_image_retun' => $img ?? '', 'reason' => $retReason, 'reason_mesej' => $retMsg]);
            msg_success('Return request submitted successfully');
            return redirect('/profile');
        }
        if ($canId && $canReason && $canMsg) {
            $od = Order::find($canId) ?? throw new Http404();
            if ($od->raw('user_id') != $u->pk()) { msg_error('Unauthorized action'); return redirect('/profile'); }
            if (in_array($od->raw('status'), ['Cancelled', 'Delivered'], true)) { msg_error('Order already processed'); return redirect('/profile'); }
            $od->status = 'Cancelled';
            $od->save();
            Return_and_Cancel_products::create(['user' => $u->pk(), 'orders' => $od->pk(), 'reason' => $canReason, 'reason_mesej' => $canMsg, 'cancel_request' => true]);
            if ($awb = $od->raw('awb_number')) after_response(fn() => Delhivery::cancel($awb));
            msg_success('Cancel request submitted successfully');
            return redirect('/profile');
        }
        msg_error('Oops something went wrong, please try again later');
        return redirect('/profile');
    }
}

final class Order_details_pg
{
    public function get(Request $r, string $uu): Response
    {
        $u = Auth::user();
        $mine = Order::filter(['user' => $u->pk()]);
        $order = $mine->anyOf([['product__uuid_product' => $uu], ['inner_color_product__product__uuid_product' => $uu]])->order_by('-id')->first();
        if (!$order) {
            $alts = [['transaction_id' => $uu], ['transaction_order_id' => $uu]];
            if (ctype_digit($uu)) $alts[] = ['id' => (int)$uu];
            $order = $mine->anyOf($alts)->order_by('-id')->first();
        }
        if (!$order) return redirect('/profile');

        $target = $order->inner_color_product ? $order->inner_color_product->product : $order->product;
        $listing = ($target && is_numeric($target->raw('discount_price'))) ? (float)$target->raw('discount_price') : 0.0;
        $selling = ($target && is_numeric($target->raw('b2c_price'))) ? (float)$target->raw('b2c_price') : 0.0;
        $qty = is_numeric($order->raw('quantity')) && (int)$order->raw('quantity') > 0 ? (int)$order->raw('quantity') : 1;
        $total = is_numeric($order->raw('total_price')) && (float)$order->raw('total_price') ? (float)$order->raw('total_price') : $selling * $qty;
        $mrp = $listing > 0 ? $listing * $qty : $total;
        if ($mrp < $total) $mrp = $total;
        $disc = max(0.0, $mrp - $total);

        return render($r, 'order_details.html', [
            'orderd_product' => $order, 'target_product' => $target, 'unit_listing' => $listing, 'unit_selling' => $selling, 'qty' => $qty,
            'total_mrp' => $mrp, 'total_price' => $total, 'discount_amount' => $disc,
            'discount_percent' => $mrp > 0 ? (int)Filters::pyRound($disc / $mrp * 100) : 0,
            'parsed_address' => Order::parseAddress($order->raw('address')),
        ]);
    }
}

/* ================================================================ Google login (replaces django-allauth) */

final class GoogleAuth
{
    private static function redirectUri(): string { return absolute_url('accounts/google/login/callback/'); }

    public static function login(Request $r): Response
    {
        $cid = config('google.client_id');
        if (!$cid) { msg_error('Google login is not configured.'); return redirect('/'); }
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_next'] = self::safeNext($r->get('next') ?: '/');
        $q = http_build_query([
            'client_id' => $cid, 'redirect_uri' => self::redirectUri(), 'response_type' => 'code',
            'scope' => 'openid email profile', 'state' => $state, 'access_type' => 'online', 'prompt' => 'select_account',
        ]);
        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $q);
    }

    private static function safeNext(string $n): string { return (str_starts_with($n, '/') && !str_starts_with($n, '//')) ? $n : '/'; }

    public static function callback(Request $r): Response
    {
        $state = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);
        if (!$state || !hash_equals($state, (string)$r->get('state')) || !$r->get('code')) {
            msg_error('Google login was cancelled or failed. Please try again.');
            return redirect('/');
        }
        try {
            $tok = Http::postForm('https://oauth2.googleapis.com/token', [
                'code' => $r->get('code'), 'client_id' => config('google.client_id'), 'client_secret' => config('google.client_secret'),
                'redirect_uri' => self::redirectUri(), 'grant_type' => 'authorization_code',
            ], [], 15);
            $access = $tok['json']['access_token'] ?? null;
            if (!$access) throw new RuntimeException('token exchange failed: ' . $tok['body']);
            $info = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', ['Authorization' => 'Bearer ' . $access], 15)['json'];
            $sub = (string)($info['sub'] ?? '');
            if ($sub === '') throw new RuntimeException('no sub in userinfo');
        } catch (Throwable $e) {
            log_error('Google OAuth: ' . $e->getMessage());
            msg_error('Google login failed. Please try again.');
            return redirect('/');
        }

        $acct = SocialAccount::filter(['provider' => 'google', 'uid' => $sub])->first();
        $user = $acct?->user;
        $email = strtolower((string)($info['email'] ?? ''));
        if (!$user && $email !== '' && !empty($info['email_verified'])) {
            $user = CustomUser::filter(['email__iexact' => $email])->first();
        }
        if (!$user) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string)($info['given_name'] ?? '')) ?: preg_replace('/[^a-z0-9_]/', '', strstr($email, '@', true) ?: 'user'));
            $base = substr($base ?: 'user', 0, 30);
            $username = $base; $i = 1;
            while (CustomUser::filter(['username' => $username])->exists()) $username = $base . $i++;
            $user = new CustomUser(['username' => $username, 'email' => $email, 'first_name' => (string)($info['given_name'] ?? ''), 'last_name' => (string)($info['family_name'] ?? '')]);
            $user->set_password(null);
            $user->save();
        }
        if (!$user->is_active) { msg_error('This account is inactive.'); return redirect('/'); }
        if ($acct) {
            SocialAccount::updateWhere(['id' => $acct->pk()], ['last_login' => now_utc_str(), 'extra_data' => json_encode($info)]);
        } else {
            SocialAccount::create(['provider' => 'google', 'uid' => $sub, 'user' => $user->pk(), 'last_login' => now_utc_str(), 'date_joined' => now_utc_str(), 'extra_data' => $info]);
        }
        $next = $_SESSION['oauth_next'] ?? '/';
        Auth::login($user);
        return redirect(self::safeNext($next));
    }
}

/* ================================================================ staff login (replaces the Django admin login at /usvesta-crm/) */

final class StaffLogin
{
    private const MAX_FAILS = 5;
    private const WINDOW = 900; // 15 minutes

    private static function throttleFile(Request $r): string
    {
        return BASE_PATH . '/storage/cache/login_' . md5($r->ip()) . '.json';
    }

    private static function fails(Request $r): array
    {
        $f = self::throttleFile($r);
        $d = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
        return array_values(array_filter($d, fn($t) => $t > time() - self::WINDOW));
    }

    private static function safeNext(?string $n): string
    {
        return ($n && str_starts_with($n, '/') && !str_starts_with($n, '//')) ? $n : '/usvesta-deshboard/';
    }

    public function get(Request $r): Response
    {
        $u = Auth::user();
        if ($u instanceof CustomUser && ($u->is_superuser || $u->is_staff)) return redirect(self::safeNext($r->get('next')));
        return render($r, 'staff_login.html', ['next' => self::safeNext($r->get('next')), 'username' => '', 'error' => null]);
    }

    public function post(Request $r): Response
    {
        $username = trim((string)$r->post('username'));
        $next = self::safeNext($r->post('next'));
        $fails = self::fails($r);
        if (count($fails) >= self::MAX_FAILS) {
            return render($r, 'staff_login.html', ['next' => $next, 'username' => $username, 'error' => 'Too many failed attempts. Please wait 15 minutes and try again.'], 429);
        }
        $u = $username !== '' ? CustomUser::filter(['username' => $username])->first() : null;
        if ($u && $u->is_active && ($u->is_staff || $u->is_superuser) && $u->check_password((string)$r->post('password'))) {
            @unlink(self::throttleFile($r));
            // upgrade very slow legacy hashes transparently (Django used up to 1.2M PBKDF2 rounds)
            if (str_starts_with((string)$u->raw('password'), 'pbkdf2_sha256$')) { $u->set_password((string)$r->post('password')); $u->save(); }
            Auth::login($u);
            return redirect($next);
        }
        $fails[] = time();
        @file_put_contents(self::throttleFile($r), json_encode($fails));
        usleep(400000);
        return render($r, 'staff_login.html', ['next' => $next, 'username' => $username,
            'error' => 'Please enter the correct username and password for a staff account. Note that both fields may be case-sensitive.'], 200);
    }
}
