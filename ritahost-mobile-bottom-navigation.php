<?php
/**
 * Plugin Name: RitaHost Mobile Bottom Navigation
 * Description: Adds a configurable fixed bottom navigation bar for mobile devices with WooCommerce and ElementsKit compatibility.
 * Version: 1.4.1
 * Author: RitaHost
 * Text Domain: ritahost
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تنظیمات پیش‌فرض نمایش منو.
 * 1 = نمایش
 * 0 = عدم نمایش
 */
function rhmbn_default_visibility_settings() {
	return array(
		'cart'           => 1,
		'checkout'       => 1,
		'account'        => 0,
		'single_product' => 0,
	);
}

/**
 * دریافت تنظیمات ذخیره‌شده با ادغام پیش‌فرض‌ها.
 */
function rhmbn_get_visibility_settings() {
	$saved = get_option( 'rhmbn_visibility_settings', array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, rhmbn_default_visibility_settings() );
}

/**
 * پاک‌سازی تنظیمات.
 */
function rhmbn_sanitize_visibility_settings( $input ) {
	$defaults = rhmbn_default_visibility_settings();
	$output   = array();

	foreach ( $defaults as $key => $default ) {
		$output[ $key ] = ( isset( $input[ $key ] ) && '1' === (string) $input[ $key ] ) ? 1 : 0;
	}

	return $output;
}

/**
 * ثبت تنظیمات.
 */
function rhmbn_register_settings() {
	register_setting(
		'rhmbn_settings_group',
		'rhmbn_visibility_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'rhmbn_sanitize_visibility_settings',
			'default'           => rhmbn_default_visibility_settings(),
		)
	);
}
add_action( 'admin_init', 'rhmbn_register_settings' );

/**
 * افزودن صفحه تنظیمات.
 */
function rhmbn_add_settings_page() {
	$page_title = function_exists( 'ritahost_admin_text' ) ? ritahost_admin_text( 'تنظیمات منوی پایین موبایل', 'Mobile Bottom Navigation Settings' ) : ( is_rtl() ? 'تنظیمات منوی پایین موبایل' : 'Mobile Bottom Navigation Settings' );
	$menu_title = function_exists( 'ritahost_admin_text' ) ? ritahost_admin_text( 'منوی پایین موبایل', 'Mobile Bottom Navigation' ) : ( is_rtl() ? 'منوی پایین موبایل' : 'Mobile Bottom Navigation' );
	if ( function_exists( 'ritahost_register_admin_tool' ) ) {
		add_submenu_page( 'ritahost-panel', $page_title, $menu_title, 'manage_options', 'rhmbn-settings', 'rhmbn_render_settings_page' );
		return;
	}
	add_options_page(
		$page_title,
		$menu_title,
		'manage_options',
		'rhmbn-settings',
		'rhmbn_render_settings_page'
	);
}
add_action( 'admin_menu', 'rhmbn_add_settings_page' );

if ( function_exists( 'ritahost_register_admin_tool' ) ) {
	ritahost_register_admin_tool( 'rhmbn-settings', 'منوی پایین موبایل', 'Mobile Bottom Navigation', 'نمایش نوار ناوبری ثابت در صفحات مختلف موبایل را کنترل می‌کند.', 'Controls where the fixed mobile bottom navigation is displayed.', 'manage_options' );
}

/**
 * صفحه تنظیمات افزونه.
 */
function rhmbn_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = rhmbn_get_visibility_settings();
	?>
	<div class="wrap">
		<h1>RitaHost Mobile Bottom Navigation</h1>

		<p>
			تعیین کنید نوار پایین موبایل در کدام صفحات ویژه ووکامرس نمایش داده شود.
			در سایر صفحات سایت، نوار به‌صورت عادی نمایش داده می‌شود.
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'rhmbn_settings_group' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">صفحه سبد خرید</th>
						<td>
							<label>
								<input type="checkbox"
								       name="rhmbn_visibility_settings[cart]"
								       value="1"
									<?php checked( 1, (int) $settings['cart'] ); ?>>
								نمایش نوار پایین در صفحه سبد خرید
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">صفحه تسویه‌حساب</th>
						<td>
							<label>
								<input type="checkbox"
								       name="rhmbn_visibility_settings[checkout]"
								       value="1"
									<?php checked( 1, (int) $settings['checkout'] ); ?>>
								نمایش نوار پایین در Checkout
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">ناحیه کاربری</th>
						<td>
							<label>
								<input type="checkbox"
								       name="rhmbn_visibility_settings[account]"
								       value="1"
									<?php checked( 1, (int) $settings['account'] ); ?>>
								نمایش نوار پایین در My Account
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">صفحه تک محصول</th>
						<td>
							<label>
								<input type="checkbox"
								       name="rhmbn_visibility_settings[single_product]"
								       value="1"
									<?php checked( 1, (int) $settings['single_product'] ); ?>>
								نمایش نوار پایین در صفحه تک محصول
							</label>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>

		<hr>

		<p>
			<strong>نکته:</strong>
			گزینه «دسته‌بندی» در نوار پایین، منوی Off-Canvas افزونه ElementsKit را باز می‌کند.
			اگر Trigger المنت‌کیت روی صفحه موجود نباشد، کاربر به صفحه فروشگاه هدایت می‌شود.
		</p>
	</div>
	<?php
}

/**
 * تعیین اینکه نوار در صفحه فعلی نمایش داده شود یا خیر.
 */
function rhmbn_should_show_nav() {
	if ( is_admin() ) {
		return false;
	}

	$settings = rhmbn_get_visibility_settings();

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return ! empty( $settings['cart'] );
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return ! empty( $settings['checkout'] );
	}

	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return ! empty( $settings['account'] );
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		return ! empty( $settings['single_product'] );
	}

	return true;
}

/**
 * بررسی مسیر فعلی برای حالت Active
 */
function rhmbn_current_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path = wp_parse_url( $uri, PHP_URL_PATH );

	return '/' . trim( (string) $path, '/' );
}

function rhmbn_is_home_active() {
	return rhmbn_current_path() === '/';
}

function rhmbn_is_shop_active() {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}

	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		return true;
	}

	if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
		return true;
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		return true;
	}

	return false;
}

function rhmbn_is_cart_active() {
	return function_exists( 'is_cart' ) && is_cart();
}

function rhmbn_is_account_active() {
	return function_exists( 'is_account_page' ) && is_account_page();
}

/**
 * خروجی نوار پایین موبایل
 */
function rhmbn_render_mobile_bottom_nav() {
	if ( ! rhmbn_should_show_nav() ) {
		return;
	}

	$home_url = home_url( '/' );

	$shop_url = function_exists( 'wc_get_page_permalink' )
		? wc_get_page_permalink( 'shop' )
		: home_url( '/shop/' );

	$account_url = function_exists( 'wc_get_page_permalink' )
		? wc_get_page_permalink( 'myaccount' )
		: wp_login_url();

	$cart_url = function_exists( 'wc_get_cart_url' )
		? wc_get_cart_url()
		: home_url( '/cart/' );

	$account_label = 'حساب کاربری';

	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$user_name    = trim( (string) $current_user->first_name );

		if ( '' === $user_name ) {
			$user_name = trim( (string) $current_user->display_name );
		}

		if ( '' !== $user_name ) {
			$account_label = sprintf( 'سلام %s', $user_name );
		}
	}
	?>
	<nav class="rhmbn" dir="rtl" aria-label="منوی پایین موبایل">

		<a class="rhmbn__item <?php echo rhmbn_is_home_active() ? 'is-active' : ''; ?>"
		   href="<?php echo esc_url( $home_url ); ?>"
		   aria-label="خانه">

			<span class="rhmbn__icon">
				<svg viewBox="0 0 24 24" aria-hidden="true">
					<path d="M3.5 10.2 12 3.4l8.5 6.8V20a1 1 0 0 1-1 1h-5.2v-5.7a2.3 2.3 0 0 0-4.6 0V21H4.5a1 1 0 0 1-1-1v-9.8Z"
					      fill="currentColor"/>
				</svg>
			</span>

			<span class="rhmbn__label">خانه</span>
		</a>


		<button type="button"
		        class="rhmbn__item rhmbn__categories-trigger <?php echo rhmbn_is_shop_active() ? 'is-active' : ''; ?>"
		        data-fallback-url="<?php echo esc_url( $shop_url ); ?>"
		        aria-label="باز کردن دسته‌بندی‌ها"
		        aria-expanded="false">

			<span class="rhmbn__icon">
				<svg viewBox="0 0 24 24" aria-hidden="true">
					<rect x="3.2" y="3.2" width="6.6" height="6.6" rx="1.8"></rect>
					<rect x="14.2" y="3.2" width="6.6" height="6.6" rx="1.8"></rect>
					<rect x="3.2" y="14.2" width="6.6" height="6.6" rx="1.8"></rect>
					<rect x="14.2" y="14.2" width="6.6" height="6.6" rx="1.8"></rect>
				</svg>
			</span>

			<span class="rhmbn__label">دسته‌بندی</span>
		</button>


		<a class="rhmbn__item rhmbn__cart <?php echo rhmbn_is_cart_active() ? 'is-active' : ''; ?>"
		   href="<?php echo esc_url( $cart_url ); ?>"
		   aria-label="سبد خرید">

			<span class="rhmbn__cart-shortcode" aria-hidden="true">
				<?php
				if ( shortcode_exists( 'rh_cart_icon' ) ) {
					echo do_shortcode( '[rh_cart_icon]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					?>
					<span class="rhmbn__fallback-cart">
						<svg viewBox="0 0 24 24" aria-hidden="true">
							<path d="M3 4h2l2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.8a1.5 1.5 0 0 0 1.5-1.1L21 7H6.1"
							      fill="none" stroke="currentColor" stroke-width="1.8"
							      stroke-linecap="round" stroke-linejoin="round"/>
							<circle cx="9" cy="20" r="1.3" fill="currentColor"/>
							<circle cx="18" cy="20" r="1.3" fill="currentColor"/>
						</svg>
					</span>
					<?php
				}
				?>
			</span>

			<?php
			$cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
			?>
			<span class="rhmbn__cart-count<?php echo $cart_count > 0 ? ' has-items' : ''; ?>"
			      aria-label="<?php echo esc_attr( sprintf( '%d کالا در سبد خرید', $cart_count ) ); ?>"><?php echo esc_html( $cart_count ); ?></span>

			<span class="rhmbn__label rhmbn__cart-label">سبد خرید</span>
		</a>


		<a class="rhmbn__item <?php echo rhmbn_is_account_active() ? 'is-active' : ''; ?>"
		   href="<?php echo esc_url( $account_url ); ?>"
		   aria-label="حساب کاربری">

			<span class="rhmbn__icon">
				<svg viewBox="0 0 24 24" aria-hidden="true">
					<circle cx="12" cy="7.5" r="3.7"></circle>
					<path d="M4.5 20c.7-4 3.5-6.2 7.5-6.2s6.8 2.2 7.5 6.2"></path>
				</svg>
			</span>

			<span class="rhmbn__label rhmbn__account-label"><?php echo esc_html( $account_label ); ?></span>
		</a>

	</nav>
	<?php
}
add_action( 'wp_footer', 'rhmbn_render_mobile_bottom_nav', 50 );

/**
 * استایل‌ها
 */
function rhmbn_mobile_bottom_nav_styles() {
	if ( ! rhmbn_should_show_nav() ) {
		return;
	}
	?>
	<style id="rhmbn-styles">
		.rhmbn {
			display: none;
		}

		@media (max-width: 767px) {
			body {
				padding-bottom: calc(78px + env(safe-area-inset-bottom)) !important;
			}

			.rhmbn {
				position: fixed;
				right: 0;
				bottom: 0;
				left: 0;
				z-index: 999999;
				display: flex;
				align-items: stretch;
				justify-content: space-around;
				width: 100%;
				min-height: 70px;
				padding: 7px 5px calc(7px + env(safe-area-inset-bottom));
				background: rgba(255, 255, 255, .97);
				border-top: 1px solid #e8e8e8;
				box-shadow: 0 -4px 15px rgba(0, 0, 0, .07);
				backdrop-filter: blur(12px);
				-webkit-backdrop-filter: blur(12px);
				box-sizing: border-box;
			}

			.rhmbn__item {
				position: relative;
				flex: 1 1 25%;
				min-width: 0;
				min-height: 56px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				gap: 4px;
				padding: 2px 3px;
				margin: 0;
				color: #777;
				text-decoration: none !important;
				background: transparent !important;
				border: 0;
				box-sizing: border-box;
				-webkit-tap-highlight-color: transparent;
			}

			.rhmbn__item:hover,
			.rhmbn__item:focus,
			.rhmbn__item:visited {
				text-decoration: none !important;
			}

			.rhmbn__categories-trigger,
			.rhmbn__categories-trigger:hover,
			.rhmbn__categories-trigger:focus,
			.rhmbn__categories-trigger:active,
			.rhmbn__categories-trigger[aria-expanded="true"] {
				appearance: none !important;
				-webkit-appearance: none !important;
				background: transparent !important;
				background-color: transparent !important;
				background-image: none !important;
				border: 0 !important;
				box-shadow: none !important;
				outline: none !important;
				color: inherit;
				font: inherit;
				cursor: pointer;
			}

			.rhmbn__icon {
				width: 27px;
				height: 27px;
				display: flex;
				align-items: center;
				justify-content: center;
				color: inherit;
			}

			.rhmbn__icon svg,
			.rhmbn__fallback-cart svg {
				width: 26px;
				height: 26px;
			}

			.rhmbn__icon svg:not([fill="currentColor"]) {
				fill: none;
				stroke: currentColor;
				stroke-width: 1.7;
				stroke-linecap: round;
				stroke-linejoin: round;
			}

			.rhmbn__label {
				display: block;
				max-width: 100%;
				margin: 0;
				padding: 0;
				color: inherit;
				font-family: inherit;
				font-size: 11.5px;
				font-weight: 400;
				line-height: 1.35;
				text-align: center;
				white-space: nowrap;
			}

			.rhmbn__account-label {
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.rhmbn__item.is-active {
				color: #111;
			}

			.rhmbn__item.is-active .rhmbn__label {
				font-weight: 500;
			}

			.rhmbn__cart {
				cursor: pointer;
				font: inherit;
				appearance: none;
				-webkit-appearance: none;
			}

			.rhmbn__cart-shortcode {
				position: relative;
				width: 30px;
				height: 29px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0;
				padding: 0;
				color: inherit;
			}

			.rhmbn__cart-shortcode > * {
				margin: 0 !important;
			}

			.rhmbn__cart-shortcode a {
				pointer-events: none !important;
				display: flex !important;
				align-items: center;
				justify-content: center;
				width: 30px;
				height: 29px;
				margin: 0 !important;
				padding: 0 !important;
				color: inherit;
				text-decoration: none !important;
			}

			.rhmbn__cart-shortcode svg,
			.rhmbn__cart-shortcode img {
				max-width: 28px;
				max-height: 28px;
			}

			.rhmbn__cart-count {
				position: absolute;
				top: 0;
				left: calc(50% + 7px);
				z-index: 3;
				display: none;
				align-items: center;
				justify-content: center;
				min-width: 18px;
				height: 18px;
				padding: 0 5px;
				border: 2px solid #fff;
				border-radius: 999px;
				background: #e53935;
				color: #fff;
				font-size: 10px;
				font-weight: 700;
				line-height: 1;
				pointer-events: none;
			}

			.rhmbn__cart-count.has-items {
				display: inline-flex;
			}

			.rhmbn__fallback-cart {
				color: inherit;
			}

			.rhmbn * {
				box-sizing: border-box;
			}
		}

		@media (min-width: 768px) {
			.rhmbn {
				display: none !important;
			}
		}
	</style>
	<?php
}
add_action( 'wp_head', 'rhmbn_mobile_bottom_nav_styles', 99 );

/**
 * به‌روزرسانی Badge تعداد سبد خرید با WooCommerce Cart Fragments.
 */
function rhmbn_cart_count_fragment( $fragments ) {
	$cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;

	ob_start();
	?>
	<span class="rhmbn__cart-count<?php echo $cart_count > 0 ? ' has-items' : ''; ?>"
	      aria-label="<?php echo esc_attr( sprintf( '%d کالا در سبد خرید', $cart_count ) ); ?>"><?php echo esc_html( $cart_count ); ?></span>
	<?php
	$fragments['.rhmbn__cart-count'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'rhmbn_cart_count_fragment' );

/**
 * باز کردن Off-Canvas واقعی ElementsKit با گزینه دسته‌بندی.
 */
function rhmbn_mobile_bottom_nav_script() {
	if ( ! rhmbn_should_show_nav() ) {
		return;
	}
	?>
	<script id="rhmbn-script">
		document.addEventListener('DOMContentLoaded', function () {
			const categoryButton = document.querySelector('.rhmbn__categories-trigger');

			if (!categoryButton) return;

			function isVisible(el) {
				if (!el) return false;
				const style = window.getComputedStyle(el);

				return (
					style.display !== 'none' &&
					style.visibility !== 'hidden' &&
					el.getClientRects().length > 0
				);
			}

			function openElementsKitOffcanvas() {
				/*
				 * روش اصلی ElementsKit:
				 * پیدا کردن Trigger واقعی Off-Canvas در هدر و اجرای کلیک همان دکمه.
				 */
				const triggers = Array.from(
					document.querySelectorAll('.ekit_offcanvas-sidebar')
				);

				const trigger =
					triggers.find(function (el) {
						return !el.closest('.rhmbn') && isVisible(el);
					}) ||
					triggers.find(function (el) {
						return !el.closest('.rhmbn');
					});

				if (trigger) {
					trigger.dispatchEvent(
						new MouseEvent('click', {
							bubbles: true,
							cancelable: true,
							view: window
						})
					);

					setTimeout(function () {
						if (document.querySelector('.ekit-sidebar-group.ekit_isActive')) {
							categoryButton.setAttribute('aria-expanded', 'true');
						}
					}, 30);

					return true;
				}

				/*
				 * Fallback:
				 * اگر Trigger المنت‌کیت در DOM وجود نداشت ولی پنل وجود داشت،
				 * خود پنل فعال شود.
				 */
				const groups = Array.from(
					document.querySelectorAll('.ekit-sidebar-group')
				);

				const group = groups.find(function (el) {
					return !el.closest('.rhmbn');
				});

				if (group) {
					group.classList.add('ekit_isActive');
					categoryButton.setAttribute('aria-expanded', 'true');

					try {
						const settings = group.dataset.settings
							? JSON.parse(group.dataset.settings)
							: {};

						if (settings.disable_bodyscroll === 'yes') {
							document.body.style.overflow = 'hidden';
						}
					} catch (e) {}

					return true;
				}

				return false;
			}

			categoryButton.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();

				if (!openElementsKitOffcanvas()) {
					const fallbackUrl = categoryButton.getAttribute('data-fallback-url');

					if (fallbackUrl) {
						window.location.href = fallbackUrl;
					}
				}
			});

			/*
			 * وقتی پنل با دکمه بستن یا Overlay بسته شد،
			 * aria-expanded هم برگردد.
			 */
			document.addEventListener('click', function (event) {
				if (
					event.target.closest('.ekit_close-side-widget, .ekit-overlay')
				) {
					setTimeout(function () {
						if (!document.querySelector('.ekit-sidebar-group.ekit_isActive')) {
							categoryButton.setAttribute('aria-expanded', 'false');
						}
					}, 30);
				}
			});
		});
	</script>
	<?php
}
add_action( 'wp_footer', 'rhmbn_mobile_bottom_nav_script', 99 );

