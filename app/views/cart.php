<?php
/** Cart & wishlist – port of Add_Cart, Inner_product_cart, Wish_list */

/** Python truthiness for decoded JSON values */
function jtruthy(mixed $v): bool { return !($v === null || $v === '' || $v === 0 || $v === 0.0 || $v === false || $v === []); }

final class Add_Cart
{
    public function post(Request $r): Response
    {
        try {
            $user = Auth::user();
            $d = $r->json();
            $productId = $d['product_id'] ?? null;
            $remove = $d['remove_product_id'] ?? null;
            $cardId = $d['card_id'] ?? null;
            $qty = $d['quantity'] ?? null;
            $sizeCard = $d['size_card_id'] ?? null;
            $size = $d['size_product'] ?? null;
            $sess = $_SESSION['anonymous_cart'] ?? [];

            if ($user instanceof CustomUser) {
                if (jtruthy($sizeCard) && jtruthy($size)) {
                    $c = Cart::filter(['user' => $user->pk(), 'id' => $sizeCard])->first();
                    if ($c) { $c->size = $size; $c->save(); }
                    return json(['message' => 'Size updated']);
                }
                if (jtruthy($cardId) && jtruthy($qty)) {
                    $c = Cart::filter(['user' => $user->pk(), 'id' => $cardId])->first();
                    if ($c) { $c->quantity = max(1, (int)$qty); $c->save(); }
                    return json(['status' => 200]);
                }
                if (jtruthy($remove)) {
                    Cart::filter(['user' => $user->pk(), 'id' => $remove])->delete();
                    return json(['message' => 'Product removed from cart']);
                }
                if (jtruthy($productId)) {
                    $p = Product::find($productId);
                    if ($p) {
                        if (Cart::filter(['user' => $user->pk(), 'product' => $p->pk()])->exists()) return json(['message' => 'Product already in cart']);
                        Cart::create(['user' => $user->pk(), 'product' => $p->pk(), 'size' => 'M']);
                        return json(['message' => 'Product added to cart']);
                    }
                }
                foreach ($sess as $item) {
                    $p = Product::find($item['product_id'] ?? null);
                    if ($p && !Cart::filter(['user' => $user->pk(), 'product' => $p->pk()])->exists()) {
                        Cart::create(['user' => $user->pk(), 'product' => $p->pk(), 'size' => !empty($item['size']) ? $item['size'] : 'M']);
                    }
                }
                $_SESSION['anonymous_cart'] = [];
                return json(['message' => 'Cart synced']);
            }

            if (jtruthy($remove)) {
                $_SESSION['anonymous_cart'] = array_values(array_filter($sess, fn($i) => (string)($i['product_id'] ?? '') !== (string)$remove));
                return json(['message' => 'Removed']);
            }
            if (jtruthy($productId)) {
                foreach ($sess as $i) if (($i['product_id'] ?? null) == $productId) return json(['message' => 'Already in cart']);
                $sess[] = ['product_id' => $productId, 'size' => null];
                $_SESSION['anonymous_cart'] = $sess;
                return json(['message' => 'Added to session cart']);
            }
            if (jtruthy($sizeCard) && jtruthy($size)) {
                foreach ($sess as &$i) if (($i['product_id'] ?? null) == $sizeCard) $i['size'] = $size;
                unset($i);
                $_SESSION['anonymous_cart'] = $sess;
                return json(['message' => 'Size updated']);
            }
            return json(null);
        } catch (Throwable $e) {
            return json(['error' => $e->getMessage()], 500);
        }
    }
}

final class Inner_product_cart
{
    public function get(Request $r): Response
    {
        $u = Auth::user();
        if ($u instanceof CustomUser) Cart::filter(['user' => $u->pk()])->delete();
        msg_success('Order placed successfully!');
        return redirect('/thank-you');
    }

    public function post(Request $r): Response
    {
        try {
            $user = Auth::user();
            $d = $r->json();
            $productId = $d['Inner_product_id'] ?? null;
            $remove = $d['remove_product_id_inner'] ?? null;
            $sizeCard = $d['size_card_id'] ?? null;
            $size = $d['size_product'] ?? null;
            $qty = $d['quantity'] ?? null;
            $cardId = $d['card_id'] ?? null;
            $sess = $_SESSION['anonymous_cart_inner'] ?? [];

            if ($user instanceof CustomUser) {
                if (jtruthy($sizeCard) && jtruthy($size)) {
                    $c = Cart::filter(['user' => $user->pk(), 'id' => $sizeCard])->first();
                    if ($c) { $c->size = $size; $c->save(); }
                    return json(['message' => 'Size updated']);
                }
                if (jtruthy($cardId) && jtruthy($qty)) {
                    $c = Cart::filter(['user' => $user->pk(), 'id' => $cardId])->first();
                    if ($c) { $c->quantity = max(1, (int)$qty); $c->save(); }
                    return json(['message' => 'Quantity updated']);
                }
                if (jtruthy($remove)) {
                    Cart::filter(['user' => $user->pk(), 'id' => $remove])->delete();
                    return json(['message' => 'Item removed']);
                }
                if (jtruthy($productId)) {
                    $p = Color_product::find($productId);
                    if ($p) {
                        if (Cart::filter(['user' => $user->pk(), 'inner_products_card' => $p->pk()])->exists()) return json(['message' => 'Product already in cart']);
                        Cart::create(['user' => $user->pk(), 'inner_products_card' => $p->pk(), 'size' => 'M']);
                        return json(['message' => 'Product added']);
                    }
                }
                foreach ($sess as $item) {
                    $p = Color_product::find($item['product_id'] ?? null);
                    if ($p && !Cart::filter(['user' => $user->pk(), 'inner_products_card' => $p->pk()])->exists()) {
                        Cart::create(['user' => $user->pk(), 'inner_products_card' => $p->pk(), 'size' => $item['size'] ?? 'M']);
                    }
                }
                $_SESSION['anonymous_cart_inner'] = [];
                return json(['message' => 'Cart synced']);
            }

            if (jtruthy($remove)) {
                $_SESSION['anonymous_cart_inner'] = array_values(array_filter($sess, fn($i) => (string)($i['product_id'] ?? '') !== (string)$remove));
                return json(['message' => 'Removed']);
            }
            if (jtruthy($productId)) {
                foreach ($sess as $i) if (($i['product_id'] ?? null) == $productId) return json(['message' => 'Already in cart']);
                $sess[] = ['product_id' => $productId, 'size' => null];
                $_SESSION['anonymous_cart_inner'] = $sess;
                return json(['message' => 'Added to session cart']);
            }
            if (jtruthy($sizeCard) && jtruthy($size)) {
                foreach ($sess as &$i) if (($i['product_id'] ?? null) == $sizeCard) $i['size'] = $size;
                unset($i);
                $_SESSION['anonymous_cart_inner'] = $sess;
                return json(['message' => 'Size updated in session']);
            }
            return json(null);
        } catch (Throwable $e) {
            return json(['error' => $e->getMessage()], 500);
        }
    }
}

final class Wish_list
{
    public function get(Request $r): Response
    {
        $u = Auth::user();
        $wish = [];
        $wishInner = [];
        $sess = $_SESSION['anonymous_wish'] ?? [];
        $sessInner = $_SESSION['anonymous_wish_inner'] ?? [];

        if ($u instanceof CustomUser) {
            foreach ($sessInner as $id) if ($b = Color_product::find($id)) Wishlist::get_or_create(['user' => $u->pk(), 'inner_products_wishlist' => $b->pk()]);
            $_SESSION['anonymous_wish_inner'] = [];
            foreach ($sess as $id) if ($b = Product::find($id)) Wishlist::get_or_create(['user' => $u->pk(), 'product' => $b->pk()]);
            $_SESSION['anonymous_wish'] = [];
            $wishInner = Wishlist::filter(['user' => $u->pk()])->get();
            $wish = Wishlist::filter(['user' => $u->pk()])->get();
        } else {
            foreach ($sessInner as $id) if ($p = Color_product::find($id)) $wishInner[] = $p;
            foreach ($sess as $id) if ($p = Product::find($id)) $wish[] = $p;
        }
        return render($r, 'my-account.html', ['shopping_wish' => $wish, 'shopping_wish_inner' => $wishInner]);
    }

    public function post(Request $r): Response
    {
        try {
            $u = Auth::user();
            $d = $r->json();
            $pid = $d['product_id'] ?? null;
            $iid = $d['inner_product_id'] ?? null;
            $rem = $d['remove_product_id'] ?? null;
            $remI = $d['remove_product_id_inner'] ?? null;
            $sess = $_SESSION['anonymous_wish'] ?? [];
            $sessI = $_SESSION['anonymous_wish_inner'] ?? [];

            if ($u instanceof CustomUser) {
                if (jtruthy($rem)) {
                    Wishlist::filter(['user' => $u->pk(), 'id' => $rem])->delete();
                    return json(['message' => 'Product removed from Wishlist', 'added' => false]);
                }
                if (jtruthy($pid)) {
                    $p = Product::find($pid);
                    if (!$p) return json(['message' => 'Oops! Product not found.']);
                    $ex = Wishlist::filter(['user' => $u->pk(), 'product' => $p->pk()])->first();
                    if ($ex) { $ex->delete(); return json(['message' => 'Product removed from Wishlist', 'added' => false]); }
                    Wishlist::create(['user' => $u->pk(), 'product' => $p->pk()]);
                    return json(['message' => 'Product added to Wishlist', 'added' => true]);
                }
                if (jtruthy($iid)) {
                    $p = Color_product::find($iid);
                    if (!$p) return json(['message' => 'Oops! Variant product not found.']);
                    $ex = Wishlist::filter(['user' => $u->pk(), 'inner_products_wishlist' => $p->pk()])->first();
                    if ($ex) { $ex->delete(); return json(['message' => 'Product removed from Wishlist', 'added' => false]); }
                    Wishlist::create(['user' => $u->pk(), 'inner_products_wishlist' => $p->pk()]);
                    return json(['message' => 'Product added to Wishlist', 'added' => true]);
                }
                return json(null);
            }

            $toggle = function (array &$list, mixed $id, string $key): JsonResponse {
                $idx = array_search($id, $list);
                if ($idx !== false) { array_splice($list, $idx, 1); $_SESSION[$key] = $list; return json(['message' => 'Product removed from Wishlist', 'added' => false]); }
                $list[] = $id; $_SESSION[$key] = $list;
                return json(['message' => 'Product added to Wishlist', 'added' => true]);
            };
            if (jtruthy($rem)) {
                $idx = array_search($rem, $sess);
                if ($idx !== false) { array_splice($sess, $idx, 1); $_SESSION['anonymous_wish'] = $sess; }
                return json(['message' => 'Product removed from Wishlist', 'added' => false]);
            }
            if (jtruthy($remI)) {
                $idx = array_search($remI, $sessI);
                if ($idx !== false) { array_splice($sessI, $idx, 1); $_SESSION['anonymous_wish_inner'] = $sessI; }
                return json(['message' => 'Product removed from Wishlist', 'added' => false]);
            }
            if (jtruthy($pid)) return $toggle($sess, $pid, 'anonymous_wish');
            if (jtruthy($iid)) return $toggle($sessI, $iid, 'anonymous_wish_inner');
            return json(null);
        } catch (Throwable $e) {
            return json(['error' => $e->getMessage()], 500);
        }
    }
}
