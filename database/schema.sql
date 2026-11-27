-- US Vesta schema for MySQL 8 / MariaDB 10.4+ (generated from the Django SQLite database)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `cloth_app_a_plus_listing` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `image_alt_name` VARCHAR(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_address_user` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `state` VARCHAR(255) NOT NULL,
  `phone_numer` VARCHAR(255) NOT NULL,
  `pin_code` VARCHAR(255) NOT NULL,
  `landmark` VARCHAR(255) NOT NULL,
  `user_address` LONGTEXT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_apikey` (
  `is_active` TINYINT(1) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `mode` VARCHAR(255) NOT NULL,
  `public_key` VARCHAR(191) NULL,
  `secret_key_hash` VARCHAR(255) NOT NULL,
  `secret_key_preview` VARCHAR(255) NOT NULL,
  `id` CHAR(32) NOT NULL,
  `merchant_id` CHAR(32) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_public_key` (`public_key`(64)),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_auditlog` (
  `id` CHAR(32) NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `details` LONGTEXT NOT NULL,
  `ip_address` VARCHAR(255) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `user_id` BIGINT NULL,
  `merchant_id` CHAR(32) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_banners` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NULL,
  `video` VARCHAR(255) NULL,
  `image_alt_name` VARCHAR(500) NOT NULL,
  `category_images_slider_id` BIGINT NULL,
  `show_on_pages` VARCHAR(255) NULL,
  `image_phone` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_category_images_slider_id` (`category_images_slider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_blog_category` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(1000) NOT NULL,
  `ct_slug` VARCHAR(800) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ct_slug` (`ct_slug`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_blog_post` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `author` VARCHAR(550) NOT NULL,
  `blog_image` VARCHAR(255) NOT NULL,
  `image_alt_name` VARCHAR(500) NOT NULL,
  `meta_title` VARCHAR(255) NOT NULL,
  `meta_keyword` VARCHAR(500) NULL,
  `meta_description` VARCHAR(500) NOT NULL,
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
  `size` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_product_id` (`product_id`),
  KEY `ix_inner_products_card_id` (`inner_products_card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_chatbot_users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(500) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_color_product` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `color` VARCHAR(255) NOT NULL,
  `product_id` BIGINT NOT NULL,
  `color_code` VARCHAR(255) NOT NULL,
  `uuid_product` VARCHAR(191) NULL,
  `color_images` VARCHAR(500) NULL,
  `temp_color_image_url` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_uuid_product` (`uuid_product`(64)),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_color_product_gelery_images` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `alt` VARCHAR(500) NULL,
  `color_product_id` BIGINT NOT NULL,
  `temp_image_url` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_color_product_id` (`color_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_contact_us` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `message` LONGTEXT NOT NULL,
  `date` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_customuser` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `password` VARCHAR(255) NOT NULL,
  `last_login` DATETIME(6) NULL,
  `is_superuser` TINYINT(1) NOT NULL,
  `username` VARCHAR(191) NOT NULL,
  `first_name` VARCHAR(255) NOT NULL,
  `last_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `is_staff` TINYINT(1) NOT NULL,
  `is_active` TINYINT(1) NOT NULL,
  `date_joined` DATETIME(6) NOT NULL,
  `profile_image` VARCHAR(255) NULL,
  `email_token` VARCHAR(191) NULL,
  `about` VARCHAR(300) NULL,
  `bank_details` VARCHAR(255) NULL,
  `password_reset_requested_at` DATETIME(6) NULL,
  `user_type` VARCHAR(255) NOT NULL,
  `decrypt_password` VARCHAR(255) NULL,
  `email_verification` TINYINT(1) NOT NULL,
  `otp_created_at` DATETIME(6) NULL,
  `phone_number_otp` VARCHAR(255) NULL,
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
  `header_logo` VARCHAR(255) NULL,
  `header_announcement_text` VARCHAR(500) NOT NULL,
  `footer_logo` VARCHAR(255) NULL,
  `footer_tagline` LONGTEXT NOT NULL,
  `footer_facebook_url` VARCHAR(255) NOT NULL,
  `footer_instagram_url` VARCHAR(255) NOT NULL,
  `footer_linkedin_url` VARCHAR(255) NOT NULL,
  `footer_copyright` VARCHAR(255) NOT NULL,
  `hero_subtitle` VARCHAR(255) NOT NULL,
  `hero_title` VARCHAR(255) NOT NULL,
  `hero_description` VARCHAR(255) NOT NULL,
  `hero_button_text` VARCHAR(255) NOT NULL,
  `hero_button_link` VARCHAR(255) NOT NULL,
  `hero_bg_image` VARCHAR(255) NULL,
  `fit_section_badge` VARCHAR(255) NOT NULL,
  `fit_card1_title` VARCHAR(255) NOT NULL,
  `fit_card1_desc` LONGTEXT NOT NULL,
  `fit_card2_title` VARCHAR(255) NOT NULL,
  `fit_card2_desc` LONGTEXT NOT NULL,
  `fit_hero_image` VARCHAR(255) NULL,
  `grid_tile1_image` VARCHAR(255) NULL,
  `grid_tile1_link` VARCHAR(255) NOT NULL,
  `grid_tile2_image` VARCHAR(255) NULL,
  `grid_tile2_link` VARCHAR(255) NOT NULL,
  `grid_tile3_image` VARCHAR(255) NULL,
  `grid_tile3_link` VARCHAR(255) NOT NULL,
  `grid_tile4_image` VARCHAR(255) NULL,
  `grid_tile4_link` VARCHAR(255) NOT NULL,
  `grid_center_button_text` VARCHAR(255) NOT NULL,
  `grid_center_button_link` VARCHAR(255) NOT NULL,
  `custom_links_json` LONGTEXT NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `footer_nav_json` LONGTEXT NOT NULL,
  `header_nav_json` LONGTEXT NOT NULL,
  `hero_subtitle_color` VARCHAR(255) NOT NULL,
  `hero_subtitle_font_size` VARCHAR(255) NOT NULL,
  `hero_title_color` VARCHAR(255) NOT NULL,
  `hero_title_font_family` VARCHAR(255) NOT NULL,
  `hero_title_font_size` VARCHAR(255) NOT NULL,
  `announcement_bg_color` VARCHAR(255) NOT NULL,
  `announcement_enabled` TINYINT(1) NOT NULL,
  `announcement_font_size` VARCHAR(255) NOT NULL,
  `announcement_font_weight` VARCHAR(255) NOT NULL,
  `announcement_text_color` VARCHAR(255) NOT NULL,
  `fit_section_bg_color` VARCHAR(255) NOT NULL,
  `footer_bg_color` VARCHAR(255) NOT NULL,
  `footer_logo_height` VARCHAR(255) NOT NULL,
  `footer_text_color` VARCHAR(255) NOT NULL,
  `footer_twitter_url` VARCHAR(255) NOT NULL,
  `footer_youtube_url` VARCHAR(255) NOT NULL,
  `grid_center_button_bg` VARCHAR(255) NOT NULL,
  `grid_center_button_color` VARCHAR(255) NOT NULL,
  `grid_tile1_alt` VARCHAR(255) NOT NULL,
  `grid_tile2_alt` VARCHAR(255) NOT NULL,
  `grid_tile3_alt` VARCHAR(255) NOT NULL,
  `grid_tile4_alt` VARCHAR(255) NOT NULL,
  `header_bg_color` VARCHAR(255) NOT NULL,
  `header_text_color` VARCHAR(255) NOT NULL,
  `hero_bg_mobile_image` VARCHAR(255) NULL,
  `hero_button_bg_color` VARCHAR(255) NOT NULL,
  `hero_button_text_color` VARCHAR(255) NOT NULL,
  `hero_description_color` VARCHAR(255) NOT NULL,
  `hero_description_font_size` VARCHAR(255) NOT NULL,
  `hero_overlay_opacity` VARCHAR(255) NOT NULL,
  `hero_title_font_weight` VARCHAR(255) NOT NULL,
  `logo_height` VARCHAR(255) NOT NULL,
  `logo_link` VARCHAR(255) NOT NULL,
  `logo_width` VARCHAR(255) NOT NULL,
  `section2_enabled` TINYINT(1) NOT NULL,
  `section3_enabled` TINYINT(1) NOT NULL,
  `section4_enabled` TINYINT(1) NOT NULL,
  `section5_enabled` TINYINT(1) NOT NULL,
  `section6_enabled` TINYINT(1) NOT NULL,
  `mobile_header_bg_color` VARCHAR(255) NOT NULL,
  `mobile_header_logo` VARCHAR(255) NULL,
  `mobile_header_text_color` VARCHAR(255) NOT NULL,
  `mega_menu_cards_json` LONGTEXT NOT NULL,
  `mega_menu_featured_links_json` LONGTEXT NOT NULL,
  `mobile_nav_json` LONGTEXT NOT NULL,
  `express_shipping_icon` VARCHAR(255) NOT NULL,
  `express_shipping_subtitle` VARCHAR(255) NOT NULL,
  `express_shipping_title` VARCHAR(255) NOT NULL,
  `mega_card1_image` VARCHAR(255) NULL,
  `mega_card2_image` VARCHAR(255) NULL,
  `mega_card3_image` VARCHAR(255) NULL,
  `mega_card4_image` VARCHAR(255) NULL,
  `mega_card5_image` VARCHAR(255) NULL,
  `fit_img1` VARCHAR(255) NULL,
  `fit_img2` VARCHAR(255) NULL,
  `fit_img3` VARCHAR(255) NULL,
  `fit_img5` VARCHAR(255) NULL,
  `fit_img6` VARCHAR(255) NULL,
  `fit_section_bg_image` VARCHAR(255) NULL,
  `aed_conversion_rate` DOUBLE NOT NULL,
  `enable_country_modal` TINYINT(1) NOT NULL,
  `eur_conversion_rate` DOUBLE NOT NULL,
  `gbp_conversion_rate` DOUBLE NOT NULL,
  `usd_conversion_rate` DOUBLE NOT NULL,
  `section4_slider_desktop_enabled` TINYINT(1) NOT NULL,
  `section4_slider_enabled` TINYINT(1) NOT NULL,
  `section4_slider_mobile_item_count` BIGINT NOT NULL,
  `section4_slider_mobile_enabled` TINYINT(1) NOT NULL,
  `section4_slider_title` VARCHAR(255) NOT NULL,
  `section3_card1_desc` VARCHAR(255) NOT NULL,
  `section3_card1_image` VARCHAR(255) NULL,
  `section3_card1_link` VARCHAR(255) NOT NULL,
  `section3_card1_price` VARCHAR(255) NOT NULL,
  `section3_card1_title` VARCHAR(255) NOT NULL,
  `section3_card2_desc` VARCHAR(255) NOT NULL,
  `section3_card2_image` VARCHAR(255) NULL,
  `section3_card2_link` VARCHAR(255) NOT NULL,
  `section3_card2_price` VARCHAR(255) NOT NULL,
  `section3_card2_title` VARCHAR(255) NOT NULL,
  `section3_card3_desc` VARCHAR(255) NOT NULL,
  `section3_card3_image` VARCHAR(255) NULL,
  `section3_card3_link` VARCHAR(255) NOT NULL,
  `section3_card3_price` VARCHAR(255) NOT NULL,
  `section3_card3_title` VARCHAR(255) NOT NULL,
  `section3_card4_desc` VARCHAR(255) NOT NULL,
  `section3_card4_image` VARCHAR(255) NULL,
  `section3_card4_link` VARCHAR(255) NOT NULL,
  `section3_card4_price` VARCHAR(255) NOT NULL,
  `section3_card4_title` VARCHAR(255) NOT NULL,
  `section3_card5_desc` VARCHAR(255) NOT NULL,
  `section3_card5_image` VARCHAR(255) NULL,
  `section3_card5_link` VARCHAR(255) NOT NULL,
  `section3_card5_price` VARCHAR(255) NOT NULL,
  `section3_card5_title` VARCHAR(255) NOT NULL,
  `section3_desktop_enabled` TINYINT(1) NOT NULL,
  `section3_mobile_enabled` TINYINT(1) NOT NULL,
  `women_coming_soon_enabled` TINYINT(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_insta_images` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_leave_checkout_page_billing_details` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(500) NOT NULL,
  `last_name` VARCHAR(500) NOT NULL,
  `phone` VARCHAR(500) NOT NULL,
  `email` VARCHAR(500) NOT NULL,
  `address` LONGTEXT NOT NULL,
  `country` VARCHAR(500) NOT NULL,
  `landmark` VARCHAR(500) NOT NULL,
  `city` VARCHAR(500) NOT NULL,
  `pincode` VARCHAR(500) NOT NULL,
  `payment_method` VARCHAR(500) NOT NULL,
  `amount` VARCHAR(500) NOT NULL,
  `date` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_merchant` (
  `id` CHAR(32) NOT NULL,
  `business_name` VARCHAR(255) NOT NULL,
  `merchant_vpa` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL,
  `daily_amount_limit` DECIMAL(14,2) NOT NULL,
  `daily_transaction_limit` BIGINT NOT NULL,
  `priority` BIGINT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_id` (`user_id`),
  KEY `ix_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_order` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `quantity` VARCHAR(255) NOT NULL,
  `order_id` VARCHAR(191) NOT NULL,
  `transaction_id` VARCHAR(255) NULL,
  `total_price` DECIMAL(14,2) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `address` LONGTEXT NULL,
  `status` VARCHAR(255) NOT NULL,
  `payment_status` VARCHAR(255) NOT NULL,
  `payment_method` VARCHAR(255) NOT NULL,
  `user_id` BIGINT NOT NULL,
  `product_id` BIGINT NULL,
  `transaction_order_id` VARCHAR(200) NULL,
  `custom_design` VARCHAR(255) NULL,
  `custom_email` VARCHAR(500) NULL,
  `custom_name` VARCHAR(500) NULL,
  `custom_number` VARCHAR(500) NULL,
  `inner_color_product_id` BIGINT NULL,
  `messag` LONGTEXT NULL,
  `product_size` VARCHAR(255) NOT NULL,
  `awb_number` VARCHAR(255) NULL,
  `shipment_id` VARCHAR(255) NULL,
  `shipment_status` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_id` (`order_id`),
  KEY `ix_transaction_order_id` (`transaction_order_id`(64)),
  KEY `ix_order_id` (`order_id`),
  KEY `ix_transaction_id` (`transaction_id`),
  KEY `ix_user_id` (`user_id`),
  KEY `ix_product_id` (`product_id`),
  KEY `ix_transaction_order_id` (`transaction_order_id`),
  KEY `ix_inner_color_product_id` (`inner_color_product_id`),
  KEY `ix_shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_our_trusted_client` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `image_alt_name` VARCHAR(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_paymentorder` (
  `payment_id` VARCHAR(255) NOT NULL,
  `mode` VARCHAR(255) NOT NULL,
  `order_id` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` VARCHAR(255) NOT NULL,
  `status` VARCHAR(255) NOT NULL,
  `payment_provider` VARCHAR(255) NOT NULL,
  `provider_reference` VARCHAR(255) NULL,
  `qr_payload` LONGTEXT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `success_at` DATETIME(6) NULL,
  `failed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `merchant_id` CHAR(32) NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `ix_order_id` (`order_id`),
  KEY `ix_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(500) NOT NULL,
  `product_image_alt` VARCHAR(500) NOT NULL,
  `product_description` LONGTEXT NOT NULL,
  `product_status` VARCHAR(255) NOT NULL,
  `product_slug` VARCHAR(191) NOT NULL,
  `uuid_product` VARCHAR(191) NULL,
  `date` DATETIME(6) NOT NULL,
  `brand` VARCHAR(500) NOT NULL,
  `user_id` BIGINT NOT NULL,
  `category_id` BIGINT NULL,
  `product_image` VARCHAR(255) NOT NULL,
  `discount_price` VARCHAR(500) NOT NULL,
  `b2b_price` DECIMAL(14,2) NULL,
  `b2c_price` DECIMAL(14,2) NOT NULL,
  `color` VARCHAR(500) NOT NULL,
  `color_code` VARCHAR(500) NOT NULL,
  `key_words` VARCHAR(500) NOT NULL,
  `select_page` VARCHAR(255) NULL,
  `t_shirt_type` VARCHAR(500) NULL,
  `ratings` VARCHAR(500) NULL,
  `top_eight_products` TINYINT(1) NOT NULL,
  `total_sale_products` VARCHAR(500) NULL,
  `product_amount_offer` DECIMAL(14,2) NULL,
  `sub_category_id` BIGINT NULL,
  `temp_product_image_url` VARCHAR(255) NULL,
  `product_quantity_offer` BIGINT NULL,
  `product_sku` VARCHAR(500) NULL,
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
  `category_image` VARCHAR(255) NULL,
  `mokups_type` VARCHAR(500) NULL,
  `name` VARCHAR(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_uuid_product` (`uuid_product`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product_gelery_images` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `alt` VARCHAR(500) NOT NULL,
  `product_id` BIGINT NOT NULL,
  `temp_image_url` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_product_sub_category` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `category_image` VARCHAR(255) NULL,
  `name` VARCHAR(255) NOT NULL,
  `uuid_product` VARCHAR(255) NULL,
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
  `product_image_retun` VARCHAR(255) NULL,
  `reason` VARCHAR(500) NOT NULL,
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
  `size` VARCHAR(255) NOT NULL,
  `product_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_sub_category_coordinator` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(500) NOT NULL,
  `containt` LONGTEXT NOT NULL,
  `sub_category_id` BIGINT NULL,
  `show_on_pages` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_category_id` (`sub_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_testimoninal` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `image_alt_name` VARCHAR(500) NOT NULL,
  `name` VARCHAR(500) NOT NULL,
  `company_name` VARCHAR(500) NOT NULL,
  `content` VARCHAR(500) NOT NULL,
  `date` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_user_reviews` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `msg` LONGTEXT NOT NULL,
  `star` VARCHAR(255) NOT NULL,
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
  `event_type` VARCHAR(255) NOT NULL,
  `request_payload` LONGTEXT NOT NULL,
  `response_status` BIGINT NULL,
  `delivered` TINYINT(1) NOT NULL,
  `delivered_at` DATETIME(6) NULL,
  `attempts` BIGINT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `payment_order_id` VARCHAR(255) NOT NULL,
  `webhook_endpoint_id` CHAR(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_payment_order_id` (`payment_order_id`),
  KEY `ix_webhook_endpoint_id` (`webhook_endpoint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cloth_app_webhookendpoint` (
  `id` CHAR(32) NOT NULL,
  `target_url` VARCHAR(500) NOT NULL,
  `secret_key` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(255) NOT NULL,
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
  `provider` VARCHAR(255) NOT NULL,
  `uid` VARCHAR(255) NOT NULL,
  `last_login` DATETIME(6) NOT NULL,
  `date_joined` DATETIME(6) NOT NULL,
  `user_id` BIGINT NOT NULL,
  `extra_data` LONGTEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_uid` (`provider`, `uid`),
  KEY `ix_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
