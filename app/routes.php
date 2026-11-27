<?php
/** URL routes – mirror of cloth_app/urls.py + cloth_project/urls.py */

$r = new Router();
$exempt = ['csrf_exempt' => true];
$login = ['login' => true];
$staff = ['login' => '/usvesta-crm/'];

$r->add('api/v1/products/', [ProductSyncAPIView::class], $exempt);

$r->add('', [Home::class]);
$r->add('track-order', fn() => redirect('/profile?tab=orders'));
$r->add('track-order/', fn() => redirect('/profile?tab=orders'));
$r->add('our-story', [About_us::class]);
$r->add('shop', [Shop::class]);
$r->add('products/<slug>', [Product_details::class]);
$r->add('blogs/<slug>', [blogs_details::class]);
$r->add('contact-us', [ContactUs::class]);
$r->add('profile', [Profile::class], $login);
$r->add('3d-custom-design', [Make_design::class], $login);
$r->add('bhaarat-kshetram', StaticPage::make('bhaaratkshetram.html'));
$r->add('3d-custom-design-polo-tshirt', [Make_design_polo::class], $login);
$r->add('logout', [Logout::class]);
$r->add('blog', [Blogs_post::class]);
$r->add('for-corporates', [B2b::class]);
$r->add('login-register', [Register_and_login::class]);
$r->add('add-to-cart', [Add_Cart::class], $exempt);
$r->add('add-to-cart_inner', [Inner_product_cart::class], $exempt);
$r->add('privacy-policy', StaticPage::make('privacy.html'));
$r->add('terms-&-conditionst', StaticPage::make('terms.html'));
$r->add('shipping-&-delivery-policy', StaticPage::make('shipingpolcy.html'));
$r->add('return-&-refund-policy', StaticPage::make('retunrefundpolcy.html'));
$r->add('cancellation-policy', StaticPage::make('cancellationpolicy.html'));
$r->add('payment-policy', StaticPage::make('paymentpolicy.html'));
$r->add('wish-list', [Wish_list::class]);
$r->add('cookies', StaticPage::make('cookie.html'));
$r->add('customer-orders', [Web_ouwner::class]);
$r->add('customer-orders/', [Web_ouwner::class]);
$r->add('thank-you', StaticPage::make('thenks.html'));

$r->add('checkout', [Checkout::class], $login);
$r->add('reset-passsword/<str:email_token_url>', [Reset_password::class]);
$r->add('payment/success/', [Payment_success::class], $exempt);
$r->add('Chat_boat_urls_saveing_data', [Chat_boat::class]);
$r->add('category', [CategoryB2b::class]);
$r->add('get-cart-data', 'get_cart_data', $exempt);
$r->add('collections/<slug:slug>/', [Collections_for_men::class]);
$r->add('t-shirts-for-men', [Tshirtcategory::class]);

$r->add('usvesta-deshboard', [Dahsboardview::class], $staff);
$r->add('usvesta-deshboard/', [Dahsboardview::class], $staff);
$r->add('usvesta-deshboard-veriant', [color_django_verint::class], $staff);
$r->add('usvesta-deshboard/update-product/<slug>', [Update_product::class], $staff);
$r->add('usvesta-deshboard-sending_databdbjajnsjansJnj', [update_dashboard_form_etc_data::class], $staff);
$r->add('ordermodel_popup', [ordermodel_popup::class], $staff);
$r->add('product-feed.xml', 'product_feed_xml');
$r->add('llms.txt', 'llms_txt');
$r->add('upload-bulk-order', [Upload_pd_in_bulk::class], $staff);
$r->add('generate-fake-reviews', fn() => json(['status' => 'error', 'message' => 'This feature has been removed.'], 410));
$r->add('create-cashfree-order', [create_cashfree_order::class]);
$r->add('create-veno-order', [create_veno_order::class]);
$r->add('retun_request_cancel_requ', [Retun_and_cancel::class]);
$r->add('order-details/<uuid:uu_id>', [Order_details_pg::class], $login);

// project-level urls
$r->add('sitemap.xml', 'sitemap_xml');
$r->add('accounts/google/login/', [GoogleAuth::class, 'login']);
$r->add('accounts/google/login/callback/', [GoogleAuth::class, 'callback']);
$r->add('accounts/logout/', fn() => (Auth::logout() ?? redirect('/')));
$r->add('accounts/login/', fn() => redirect('/login-register'));
$r->add('usvesta-crm/', [StaffLogin::class]);
$r->add('usvesta-crm/login/', [StaffLogin::class]);
$r->add('usvesta-crm/<path:rest>', fn() => redirect('/usvesta-crm/'));

return $r;
