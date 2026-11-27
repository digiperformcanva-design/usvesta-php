-- US Vesta schema for MySQL 8 / MariaDB 10.4+ (generated from the Django SQLite database)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `cloth_app_a_plus_listing` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NOT NULL,
  `image_alt_name` TEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_address_user` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `state` TEXT NOT NULL,
  `phone_numer` TEXT NOT NULL,
  `pin_code` TEXT NOT NULL,
  `landmark` TEXT NOT NULL,
  `user_address` LONGTEXT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_apikey` (
  `is_active` TINYINT(1) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `mode` TEXT NOT NULL,
  `public_key` VARCHAR(191) NULL,
  `secret_key_hash` TEXT NOT NULL,
  `secret_key_preview` TEXT NOT NULL,
  `id` CHAR(32) NOT NULL,
  `merchant_id` CHAR(32) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_public_key` (`public_key`(64)),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_auditlog` (
  `id` CHAR(32) NOT NULL,
  `action` TEXT NOT NULL,
  `details` LONGTEXT NOT NULL,
  `ip_address` TEXT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `user_id` BIGINT NULL,
  `merchant_id` CHAR(32) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_banners` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NULL,
  `video` TEXT NULL,
  `image_alt_name` TEXT NOT NULL,
  `category_images_slider_id` BIGINT NULL,
  `show_on_pages` TEXT NULL,
  `image_phone` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_category_images_slider_id` (`category_images_slider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_blog_category` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `category` TEXT NOT NULL,
  `ct_slug` VARCHAR(800) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ct_slug` (`ct_slug`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_blog_post` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `author` TEXT NOT NULL,
  `blog_image` TEXT NOT NULL,
  `image_alt_name` TEXT NOT NULL,
  `meta_title` TEXT NOT NULL,
  `meta_keyword` TEXT NULL,
  `meta_description` TEXT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `blog_content` LONGTEXT NOT NULL,
  `date` DATETIME(6) NOT NULL,
  `category_id` BIGINT NOT NULL,
  `slug` VARCHAR(1000) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`(191)),
  KEY `ix_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_cart` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `quantity` INT UNSIGNED NOT NULL,
  `date` DATETIME(6) NOT NULL,
  `user_id` BIGINT NOT NULL,
  `product_id` BIGINT NULL,
  `inner_products_card_id` BIGINT NULL,
  `size` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_product_id` (`product_id`),
  KEY `ix_inner_products_card_id` (`inner_products_card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_chatbot_users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `phone` TEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_color_product` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `color` TEXT NOT NULL,
  `product_id` BIGINT NOT NULL,
  `color_code` TEXT NOT NULL,
  `uuid_product` VARCHAR(191) NULL,
  `color_images` TEXT NULL,
  `temp_color_image_url` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_uuid_product` (`uuid_product`(64)),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_color_product_gelery_images` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NOT NULL,
  `alt` TEXT NULL,
  `color_product_id` BIGINT NOT NULL,
  `temp_image_url` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_color_product_id` (`color_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_contact_us` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `phone` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `message` LONGTEXT NOT NULL,
  `date` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_customuser` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `password` TEXT NOT NULL,
  `last_login` DATETIME(6) NULL,
  `is_superuser` TINYINT(1) NOT NULL,
  `username` VARCHAR(191) NOT NULL,
  `first_name` TEXT NOT NULL,
  `last_name` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `is_staff` TINYINT(1) NOT NULL,
  `is_active` TINYINT(1) NOT NULL,
  `date_joined` DATETIME(6) NOT NULL,
  `profile_image` TEXT NULL,
  `email_token` VARCHAR(191) NULL,
  `about` TEXT NULL,
  `bank_details` TEXT NULL,
  `password_reset_requested_at` DATETIME(6) NULL,
  `user_type` TEXT NOT NULL,
  `decrypt_password` TEXT NULL,
  `email_verification` TINYINT(1) NOT NULL,
  `otp_created_at` DATETIME(6) NULL,
  `phone_number_otp` TEXT NULL,
  `phone` VARCHAR(191) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_phone` (`phone`),
  KEY `ix_email_token` (`email_token`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_hightlight_for_product` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `highlight` LONGTEXT NULL,
  `product_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_homepageeditor` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `header_logo` TEXT NULL,
  `header_announcement_text` TEXT NOT NULL,
  `footer_logo` TEXT NULL,
  `footer_tagline` LONGTEXT NOT NULL,
  `footer_facebook_url` TEXT NOT NULL,
  `footer_instagram_url` TEXT NOT NULL,
  `footer_linkedin_url` TEXT NOT NULL,
  `footer_copyright` TEXT NOT NULL,
  `hero_subtitle` TEXT NOT NULL,
  `hero_title` TEXT NOT NULL,
  `hero_description` TEXT NOT NULL,
  `hero_button_text` TEXT NOT NULL,
  `hero_button_link` TEXT NOT NULL,
  `hero_bg_image` TEXT NULL,
  `fit_section_badge` TEXT NOT NULL,
  `fit_card1_title` TEXT NOT NULL,
  `fit_card1_desc` LONGTEXT NOT NULL,
  `fit_card2_title` TEXT NOT NULL,
  `fit_card2_desc` LONGTEXT NOT NULL,
  `fit_hero_image` TEXT NULL,
  `grid_tile1_image` TEXT NULL,
  `grid_tile1_link` TEXT NOT NULL,
  `grid_tile2_image` TEXT NULL,
  `grid_tile2_link` TEXT NOT NULL,
  `grid_tile3_image` TEXT NULL,
  `grid_tile3_link` TEXT NOT NULL,
  `grid_tile4_image` TEXT NULL,
  `grid_tile4_link` TEXT NOT NULL,
  `grid_center_button_text` TEXT NOT NULL,
  `grid_center_button_link` TEXT NOT NULL,
  `custom_links_json` LONGTEXT NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `footer_nav_json` LONGTEXT NOT NULL,
  `header_nav_json` LONGTEXT NOT NULL,
  `hero_subtitle_color` TEXT NOT NULL,
  `hero_subtitle_font_size` TEXT NOT NULL,
  `hero_title_color` TEXT NOT NULL,
  `hero_title_font_family` TEXT NOT NULL,
  `hero_title_font_size` TEXT NOT NULL,
  `announcement_bg_color` TEXT NOT NULL,
  `announcement_enabled` TINYINT(1) NOT NULL,
  `announcement_font_size` TEXT NOT NULL,
  `announcement_font_weight` TEXT NOT NULL,
  `announcement_text_color` TEXT NOT NULL,
  `fit_section_bg_color` TEXT NOT NULL,
  `footer_bg_color` TEXT NOT NULL,
  `footer_logo_height` TEXT NOT NULL,
  `footer_text_color` TEXT NOT NULL,
  `footer_twitter_url` TEXT NOT NULL,
  `footer_youtube_url` TEXT NOT NULL,
  `grid_center_button_bg` TEXT NOT NULL,
  `grid_center_button_color` TEXT NOT NULL,
  `grid_tile1_alt` TEXT NOT NULL,
  `grid_tile2_alt` TEXT NOT NULL,
  `grid_tile3_alt` TEXT NOT NULL,
  `grid_tile4_alt` TEXT NOT NULL,
  `header_bg_color` TEXT NOT NULL,
  `header_text_color` TEXT NOT NULL,
  `hero_bg_mobile_image` TEXT NULL,
  `hero_button_bg_color` TEXT NOT NULL,
  `hero_button_text_color` TEXT NOT NULL,
  `hero_description_color` TEXT NOT NULL,
  `hero_description_font_size` TEXT NOT NULL,
  `hero_overlay_opacity` TEXT NOT NULL,
  `hero_title_font_weight` TEXT NOT NULL,
  `logo_height` TEXT NOT NULL,
  `logo_link` TEXT NOT NULL,
  `logo_width` TEXT NOT NULL,
  `section2_enabled` TINYINT(1) NOT NULL,
  `section3_enabled` TINYINT(1) NOT NULL,
  `section4_enabled` TINYINT(1) NOT NULL,
  `section5_enabled` TINYINT(1) NOT NULL,
  `section6_enabled` TINYINT(1) NOT NULL,
  `mobile_header_bg_color` TEXT NOT NULL,
  `mobile_header_logo` TEXT NULL,
  `mobile_header_text_color` TEXT NOT NULL,
  `mega_menu_cards_json` LONGTEXT NOT NULL,
  `mega_menu_featured_links_json` LONGTEXT NOT NULL,
  `mobile_nav_json` LONGTEXT NOT NULL,
  `express_shipping_icon` TEXT NOT NULL,
  `express_shipping_subtitle` TEXT NOT NULL,
  `express_shipping_title` TEXT NOT NULL,
  `mega_card1_image` TEXT NULL,
  `mega_card2_image` TEXT NULL,
  `mega_card3_image` TEXT NULL,
  `mega_card4_image` TEXT NULL,
  `mega_card5_image` TEXT NULL,
  `fit_img1` TEXT NULL,
  `fit_img2` TEXT NULL,
  `fit_img3` TEXT NULL,
  `fit_img5` TEXT NULL,
  `fit_img6` TEXT NULL,
  `fit_section_bg_image` TEXT NULL,
  `aed_conversion_rate` DOUBLE NOT NULL,
  `enable_country_modal` TINYINT(1) NOT NULL,
  `eur_conversion_rate` DOUBLE NOT NULL,
  `gbp_conversion_rate` DOUBLE NOT NULL,
  `usd_conversion_rate` DOUBLE NOT NULL,
  `section4_slider_desktop_enabled` TINYINT(1) NOT NULL,
  `section4_slider_enabled` TINYINT(1) NOT NULL,
  `section4_slider_mobile_item_count` BIGINT NOT NULL,
  `section4_slider_mobile_enabled` TINYINT(1) NOT NULL,
  `section4_slider_title` TEXT NOT NULL,
  `section3_card1_desc` TEXT NOT NULL,
  `section3_card1_image` TEXT NULL,
  `section3_card1_link` TEXT NOT NULL,
  `section3_card1_price` TEXT NOT NULL,
  `section3_card1_title` TEXT NOT NULL,
  `section3_card2_desc` TEXT NOT NULL,
  `section3_card2_image` TEXT NULL,
  `section3_card2_link` TEXT NOT NULL,
  `section3_card2_price` TEXT NOT NULL,
  `section3_card2_title` TEXT NOT NULL,
  `section3_card3_desc` TEXT NOT NULL,
  `section3_card3_image` TEXT NULL,
  `section3_card3_link` TEXT NOT NULL,
  `section3_card3_price` TEXT NOT NULL,
  `section3_card3_title` TEXT NOT NULL,
  `section3_card4_desc` TEXT NOT NULL,
  `section3_card4_image` TEXT NULL,
  `section3_card4_link` TEXT NOT NULL,
  `section3_card4_price` TEXT NOT NULL,
  `section3_card4_title` TEXT NOT NULL,
  `section3_card5_desc` TEXT NOT NULL,
  `section3_card5_image` TEXT NULL,
  `section3_card5_link` TEXT NOT NULL,
  `section3_card5_price` TEXT NOT NULL,
  `section3_card5_title` TEXT NOT NULL,
  `section3_desktop_enabled` TINYINT(1) NOT NULL,
  `section3_mobile_enabled` TINYINT(1) NOT NULL,
  `women_coming_soon_enabled` TINYINT(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_insta_images` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_leave_checkout_page_billing_details` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `first_name` TEXT NOT NULL,
  `last_name` TEXT NOT NULL,
  `phone` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `address` LONGTEXT NOT NULL,
  `country` TEXT NOT NULL,
  `landmark` TEXT NOT NULL,
  `city` TEXT NOT NULL,
  `pincode` TEXT NOT NULL,
  `payment_method` TEXT NOT NULL,
  `amount` TEXT NOT NULL,
  `date` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_merchant` (
  `id` CHAR(32) NOT NULL,
  `business_name` TEXT NOT NULL,
  `merchant_vpa` TEXT NOT NULL,
  `status` TEXT NOT NULL,
  `daily_amount_limit` DECIMAL(14,2) NOT NULL,
  `daily_transaction_limit` BIGINT NOT NULL,
  `priority` BIGINT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_order` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `quantity` TEXT NOT NULL,
  `order_id` VARCHAR(191) NOT NULL,
  `transaction_id` TEXT NULL,
  `total_price` DECIMAL(14,2) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `address` LONGTEXT NULL,
  `status` TEXT NOT NULL,
  `payment_status` TEXT NOT NULL,
  `payment_method` TEXT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `product_id` BIGINT NULL,
  `transaction_order_id` VARCHAR(200) NULL,
  `custom_design` TEXT NULL,
  `custom_email` TEXT NULL,
  `custom_name` TEXT NULL,
  `custom_number` TEXT NULL,
  `inner_color_product_id` BIGINT NULL,
  `messag` LONGTEXT NULL,
  `product_size` TEXT NOT NULL,
  `awb_number` TEXT NULL,
  `shipment_id` TEXT NULL,
  `shipment_status` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_id` (`order_id`),
  KEY `ix_transaction_order_id` (`transaction_order_id`(64)),
  KEY `ix_transaction_id` (`transaction_id`(64)),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_product_id` (`product_id`),
  KEY `ix_inner_color_product_id` (`inner_color_product_id`),
  KEY `ix_shipment_id` (`shipment_id`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_our_trusted_client` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NOT NULL,
  `image_alt_name` TEXT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_paymentorder` (
  `payment_id` VARCHAR(191) NOT NULL,
  `mode` TEXT NOT NULL,
  `order_id` TEXT NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` TEXT NOT NULL,
  `status` TEXT NOT NULL,
  `payment_provider` TEXT NOT NULL,
  `provider_reference` TEXT NULL,
  `qr_payload` LONGTEXT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `success_at` DATETIME(6) NULL,
  `failed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `merchant_id` CHAR(32) NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `ix_order_id` (`order_id`(64)),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `product_name` TEXT NOT NULL,
  `product_image_alt` TEXT NOT NULL,
  `product_description` LONGTEXT NOT NULL,
  `product_status` TEXT NOT NULL,
  `product_slug` VARCHAR(191) NOT NULL,
  `uuid_product` VARCHAR(191) NULL,
  `date` DATETIME(6) NOT NULL,
  `brand` TEXT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `category_id` BIGINT NULL,
  `product_image` TEXT NOT NULL,
  `discount_price` TEXT NOT NULL,
  `b2b_price` DECIMAL(14,2) NULL,
  `b2c_price` DECIMAL(14,2) NOT NULL,
  `color` TEXT NOT NULL,
  `color_code` TEXT NOT NULL,
  `key_words` TEXT NOT NULL,
  `select_page` TEXT NULL,
  `t_shirt_type` TEXT NULL,
  `ratings` TEXT NULL,
  `top_eight_products` TINYINT(1) NOT NULL,
  `total_sale_products` TEXT NULL,
  `product_amount_offer` DECIMAL(14,2) NULL,
  `sub_category_id` BIGINT NULL,
  `temp_product_image_url` TEXT NULL,
  `product_quantity_offer` BIGINT NULL,
  `product_sku` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_slug` (`product_slug`(191)),
  KEY `ix_uuid_product` (`uuid_product`(64)),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_category_id` (`category_id`),
  KEY `ix_sub_category_id` (`sub_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product_category` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `uuid_product` VARCHAR(191) NULL,
  `date` DATETIME(6) NOT NULL,
  `category_image` TEXT NULL,
  `mokups_type` TEXT NULL,
  `name` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_uuid_product` (`uuid_product`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product_gelery_images` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NOT NULL,
  `alt` TEXT NOT NULL,
  `product_id` BIGINT NOT NULL,
  `temp_image_url` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product_sub_category` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `category_image` TEXT NULL,
  `name` TEXT NOT NULL,
  `uuid_product` TEXT NULL,
  `date` DATETIME(6) NOT NULL,
  `category_slug` VARCHAR(191) NOT NULL,
  `category_id` BIGINT NULL,
  `customcode` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_slug` (`category_slug`(191)),
  KEY `ix_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_return_and_cancel_products` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `product_image_retun` TEXT NULL,
  `reason` TEXT NOT NULL,
  `reason_mesej` LONGTEXT NOT NULL,
  `cancel_request` TINYINT(1) NOT NULL,
  `create_at` DATETIME(6) NOT NULL,
  `orders_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_orders_id` (`orders_id`),
  KEY `ix_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_size_price_par_post` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `size` TEXT NOT NULL,
  `product_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_sub_category_coordinator` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `containt` LONGTEXT NOT NULL,
  `sub_category_id` BIGINT NULL,
  `show_on_pages` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_category_id` (`sub_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_testimoninal` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` TEXT NOT NULL,
  `image_alt_name` TEXT NOT NULL,
  `name` TEXT NOT NULL,
  `company_name` TEXT NOT NULL,
  `content` TEXT NOT NULL,
  `date` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_user_reviews` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `email` TEXT NOT NULL,
  `msg` LONGTEXT NOT NULL,
  `star` TEXT NOT NULL,
  `show` TINYINT(1) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `product_id` BIGINT NULL,
  `user_id` BIGINT NULL,
  `inner_product_rew_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_inner_product_rew_id` (`inner_product_rew_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_webhookdelivery` (
  `id` CHAR(32) NOT NULL,
  `event_type` TEXT NOT NULL,
  `request_payload` LONGTEXT NOT NULL,
  `response_status` BIGINT NULL,
  `delivered` TINYINT(1) NOT NULL,
  `delivered_at` DATETIME(6) NULL,
  `attempts` BIGINT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `payment_order_id` TEXT NOT NULL,
  `webhook_endpoint_id` CHAR(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_payment_order_id` (`payment_order_id`(64)),
  KEY `ix_webhook_endpoint_id` (`webhook_endpoint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_webhookendpoint` (
  `id` CHAR(32) NOT NULL,
  `target_url` TEXT NOT NULL,
  `secret_key` TEXT NOT NULL,
  `event_type` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `merchant_id` CHAR(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_wishlist` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `added_at` DATETIME(6) NOT NULL,
  `product_id` BIGINT NULL,
  `user_id` BIGINT NOT NULL,
  `inner_products_wishlist_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_inner_products_wishlist_id` (`inner_products_wishlist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `socialaccount_socialaccount` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(200) NOT NULL,
  `uid` VARCHAR(191) NOT NULL,
  `last_login` DATETIME(6) NOT NULL,
  `date_joined` DATETIME(6) NOT NULL,
  `user_id` BIGINT NOT NULL,
  `extra_data` LONGTEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_uid` (`provider`(191), `uid`),
  KEY `ix_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
