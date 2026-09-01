<?php
/**
 * Plugin Name: RitaHost WooCommerce Order Invoice
 * Description: Adds configurable invoice-style WooCommerce thank-you and order-payment pages with a print-friendly invoice table.
 * Version: 3.2.0
 * Author: RitaHost
 * Text Domain: ritahost
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

function rhi_text( $fa, $en ) {
	return strpos( determine_locale(), 'fa' ) === 0 ? $fa : $en;
}

if ( ! class_exists( 'RHI_Woo_Invoice_MU' ) ) {

	final class RHI_Woo_Invoice_MU {

		const OPTION_KEY = 'rhi_woo_invoice_settings';
		const VERSION    = '3.2.0';

		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 99 );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

			add_action( 'template_redirect', array( __CLASS__, 'redirect_paid_order_pay' ), 2 );

			add_action( 'wp', array( __CLASS__, 'remove_default_order_details' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_styles' ), 100 );
			add_action( 'wp_footer', array( __CLASS__, 'print_scripts' ), 100 );

			add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_invoice' ), 5 );

			/*
			 * Runs for both WooCommerce order-pay modes:
			 * 1) the regular "Pay for order" form
			 * 2) the gateway receipt page used by off-site gateways such as Snapp
			 */
			add_action( 'before_woocommerce_pay', array( __CLASS__, 'render_order_pay_invoice' ), 5 );
		}

		/* ---------------------------------------------------------
		 * Page / order helpers
		 * ------------------------------------------------------ */

		private static function is_invoice_page() {
			$is_received = function_exists( 'is_order_received_page' ) && is_order_received_page();
			$is_order_pay = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' );

			return $is_received || $is_order_pay;
		}

		private static function get_order_pay_order() {
			if (
				! function_exists( 'is_wc_endpoint_url' ) ||
				! is_wc_endpoint_url( 'order-pay' ) ||
				! function_exists( 'wc_get_order' )
			) {
				return false;
			}

			$order_id = absint( get_query_var( 'order-pay' ) );

			if ( ! $order_id ) {
				return false;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return false;
			}

			$key = isset( $_GET['key'] )
				? wc_clean( wp_unslash( $_GET['key'] ) )
				: '';

			if (
				! $key ||
				! $order->get_order_key() ||
				! hash_equals( (string) $order->get_order_key(), (string) $key )
			) {
				return false;
			}

			return $order;
		}

		public static function redirect_paid_order_pay() {
			$order = self::get_order_pay_order();

			if ( ! $order ) {
				return;
			}

			/*
			 * If a gateway returns the shopper to /order-pay/ even though
			 * WooCommerce already knows the payment succeeded, send them to
			 * the canonical Thank You URL. Never fake success for Pending/Failed.
			 */
			$is_paid = $order->is_paid() || (bool) $order->get_date_paid();

			if ( ! $is_paid ) {
				return;
			}

			$return_url = $order->get_checkout_order_received_url();

			if ( $return_url ) {
				wp_safe_redirect( $return_url );
				exit;
			}
		}

		public static function render_order_pay_invoice() {
			$order = self::get_order_pay_order();

			if ( ! $order ) {
				return;
			}

			self::render_invoice( $order->get_id() );
		}

		/* ---------------------------------------------------------
		 * Settings
		 * ------------------------------------------------------ */

		public static function defaults() {
			return array(
				'primary'       => '#1E6CA8',
				'accent'        => '#22C55E',
				'background'    => '#F4FAFB',
				'surface'       => '#FFFFFF',
				'text'          => '#152033',
				'muted'         => '#718096',
				'border'        => '#D8E3EA',
				'success_title' => rhi_text( 'سفارش شما با موفقیت ثبت شد', 'Your order was placed successfully' ),
				'invoice_title' => rhi_text( 'جزئیات فاکتور', 'Invoice details' ),
				'footer_note'   => rhi_text( 'این فاکتور بر اساس اطلاعات ثبت‌شده در سفارش ایجاد شده است.', 'This invoice was generated from the information recorded with the order.' ),
			);
		}

		public static function settings() {
			$saved = get_option( self::OPTION_KEY, array() );

			return wp_parse_args(
				is_array( $saved ) ? $saved : array(),
				self::defaults()
			);
		}

		public static function register_settings() {
			register_setting(
				'rhi_woo_invoice_group',
				self::OPTION_KEY,
				array( __CLASS__, 'sanitize_settings' )
			);
		}

		public static function sanitize_settings( $input ) {
			$defaults = self::defaults();
			$output   = array();

			foreach ( array( 'primary', 'accent', 'background', 'surface', 'text', 'muted', 'border' ) as $key ) {
				$value          = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
				$output[ $key ] = $value ?: $defaults[ $key ];
			}

			foreach ( array( 'success_title', 'invoice_title', 'footer_note' ) as $key ) {
				$value          = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
				$output[ $key ] = $value !== '' ? $value : $defaults[ $key ];
			}

			return $output;
		}

		public static function admin_menu() {
			$parent = function_exists( 'ritahost_register_admin_tool' ) ? 'ritahost-panel' : 'woocommerce';
			$page_title = function_exists( 'ritahost_admin_text' ) ? ritahost_admin_text( 'تنظیمات فاکتور سفارش', 'Order Invoice Settings' ) : ( is_rtl() ? 'تنظیمات فاکتور سفارش' : 'Order Invoice Settings' );
			$menu_title = function_exists( 'ritahost_admin_text' ) ? ritahost_admin_text( 'فاکتور سفارش', 'Order Invoice' ) : ( is_rtl() ? 'فاکتور سفارش' : 'Order Invoice' );
			add_submenu_page(
				$parent,
				$page_title,
				$menu_title,
				'manage_woocommerce',
				'rhi-woo-invoice',
				array( __CLASS__, 'settings_page' )
			);
		}

		public static function settings_page() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$s = self::settings();

			$colors = array(
				'primary'    => rhi_text( 'رنگ اصلی', 'Primary color' ),
				'accent'     => rhi_text( 'رنگ موفقیت', 'Success color' ),
				'background' => rhi_text( 'پس‌زمینه فاکتور', 'Invoice background' ),
				'surface'    => rhi_text( 'رنگ کارت‌ها', 'Card color' ),
				'text'       => rhi_text( 'رنگ متن اصلی', 'Primary text color' ),
				'muted'      => rhi_text( 'رنگ متن ثانویه', 'Secondary text color' ),
				'border'     => rhi_text( 'رنگ خطوط و کادرها', 'Border color' ),
			);
			?>
			<div class="wrap">
				<h1><?php echo esc_html( rhi_text( 'تنظیمات فاکتور سفارش', 'Order Invoice Settings' ) ); ?></h1>
				<p><?php echo esc_html( rhi_text( 'این تنظیمات عمومی هستند و افزونه نام و لوگوی هر سایت را به‌صورت خودکار از همان سایت می‌خواند.', 'These settings are reusable; the plugin reads each site name and logo automatically.' ) ); ?></p>

				<form method="post" action="options.php">
					<?php settings_fields( 'rhi_woo_invoice_group' ); ?>

					<h2><?php echo esc_html( rhi_text( 'رنگ‌بندی', 'Colors' ) ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
						<?php foreach ( $colors as $key => $label ) : ?>
							<tr>
								<th scope="row">
									<label for="rhi-<?php echo esc_attr( $key ); ?>">
										<?php echo esc_html( $label ); ?>
									</label>
								</th>
								<td>
									<input
										type="color"
										id="rhi-<?php echo esc_attr( $key ); ?>"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
										value="<?php echo esc_attr( $s[ $key ] ); ?>"
										style="width:70px;height:42px;padding:3px;border:1px solid #ccd0d4;border-radius:7px;"
									>
									<code style="margin-right:10px;"><?php echo esc_html( $s[ $key ] ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<h2><?php echo esc_html( rhi_text( 'متن‌ها', 'Text' ) ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="rhi-success-title"><?php echo esc_html( rhi_text( 'عنوان موفقیت سفارش', 'Order success title' ) ); ?></label></th>
								<td>
									<input
										type="text"
										class="regular-text"
										id="rhi-success-title"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_title]"
										value="<?php echo esc_attr( $s['success_title'] ); ?>"
									>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rhi-invoice-title"><?php echo esc_html( rhi_text( 'عنوان فاکتور', 'Invoice title' ) ); ?></label></th>
								<td>
									<input
										type="text"
										class="regular-text"
										id="rhi-invoice-title"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[invoice_title]"
										value="<?php echo esc_attr( $s['invoice_title'] ); ?>"
									>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rhi-footer-note"><?php echo esc_html( rhi_text( 'متن پایین فاکتور', 'Invoice footer text' ) ); ?></label></th>
								<td>
									<input
										type="text"
										class="large-text"
										id="rhi-footer-note"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_note]"
										value="<?php echo esc_attr( $s['footer_note'] ); ?>"
									>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( rhi_text( 'ذخیره تنظیمات', 'Save settings' ) ); ?>
				</form>
			</div>
			<?php
		}

		/* ---------------------------------------------------------
		 * WooCommerce cleanup
		 * ------------------------------------------------------ */

		public static function remove_default_order_details() {
			if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
				return;
			}

			remove_action(
				'woocommerce_thankyou',
				'woocommerce_order_details_table',
				10
			);
		}

		/* ---------------------------------------------------------
		 * Logo helpers
		 * ------------------------------------------------------ */

		private static function get_logo_html() {
			/*
			 * Standard WordPress Custom Logo.
			 */
			$custom_logo_id = (int) get_theme_mod( 'custom_logo' );

			if ( $custom_logo_id ) {
				$html = wp_get_attachment_image(
					$custom_logo_id,
					'medium',
					false,
					array(
						'class'   => 'rhi-site-logo-img',
						'loading' => 'eager',
						'alt'     => get_bloginfo( 'name' ),
					)
				);

				if ( $html ) {
					return $html;
				}
			}

			/*
			 * If the theme stores its logo elsewhere (for example some header
			 * builders), JavaScript below will copy the visible header logo.
			 * Until then, show the current site name — never a hard-coded brand.
			 */
			return '<span class="rhi-site-name">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		}

		/* ---------------------------------------------------------
		 * Styles
		 * ------------------------------------------------------ */

		public static function print_styles() {
			if ( ! self::is_invoice_page() ) {
				return;
			}

			$s = self::settings();
			?>
			<style id="rhi-woo-invoice-css">
				body.woocommerce-order-received,
				body.woocommerce-order-pay{
					--rhi-primary:<?php echo esc_attr( $s['primary'] ); ?>;
					--rhi-accent:<?php echo esc_attr( $s['accent'] ); ?>;
					--rhi-bg:<?php echo esc_attr( $s['background'] ); ?>;
					--rhi-surface:<?php echo esc_attr( $s['surface'] ); ?>;
					--rhi-text:<?php echo esc_attr( $s['text'] ); ?>;
					--rhi-muted:<?php echo esc_attr( $s['muted'] ); ?>;
					--rhi-border:<?php echo esc_attr( $s['border'] ); ?>;
					--rhi-font:"IRANYekanX",Sans-serif;
				}

				body.woocommerce-order-received .woocommerce-order > .woocommerce-thankyou-order-received,
				body.woocommerce-order-received .woocommerce-order > .woocommerce-order-overview{
					display:none!important;
				}

				/*
				 * Order Pay can be rendered in two different ways by WooCommerce:
				 * - checkout/form-pay.php
				 * - checkout/order-receipt.php (common for off-site gateways)
				 *
				 * Hide only WooCommerce's duplicate summary. The actual gateway
				 * controls / Snapp receipt UI remain visible and functional.
				 */
				body.woocommerce-order-pay ul.order_details{
					display:none!important;
				}

				body.woocommerce-order-pay form#order_review > table.shop_table{
					display:none!important;
				}

				body.woocommerce-order-pay .rhi-invoice{
					margin-top:25px;
				}

				body.woocommerce-order-pay #order_review,
				body.woocommerce-order-pay #payment{
					font-family:var(--rhi-font)!important;
				}

				body.woocommerce-order-pay #payment{
					max-width:1180px;
					margin:0 auto 35px!important;
					border-radius:17px!important;
				}

				body.woocommerce-order-pay #place_order{
					font-family:var(--rhi-font)!important;
					border-radius:10px!important;
				}

				.rhi-invoice,
				.rhi-invoice *,
				.rhi-invoice table,
				.rhi-invoice th,
				.rhi-invoice td,
				.rhi-invoice button,
				.rhi-invoice a{
					box-sizing:border-box;
					font-family:var(--rhi-font)!important;
				}
				#snapppay-payment-button {
				  all: unset !important;
				  background: #1F6DA9 !important;
				  color: #fff !important;
				  border-radius: 5px !important; 
				  padding: 5px 30px !important;
				  font-family: iranyekan !important;
				}
				.rhi-invoice{
					direction:rtl;
					width:100%;
					max-width:1180px;
					margin:35px auto;
					padding:28px;
					color:var(--rhi-text);
					background:var(--rhi-bg);
					border:1px solid var(--rhi-border);
					border-radius:25px;
					box-shadow:0 20px 50px rgba(30,55,80,.07);
				}

				.rhi-top{
					display:flex;
					align-items:center;
					justify-content:space-between;
					gap:24px;
					padding-bottom:23px;
					margin-bottom:20px;
					border-bottom:1px dashed var(--rhi-border);
				}

				.rhi-top-main{
					display:flex;
					align-items:center;
					gap:15px;
					min-width:0;
				}

				.rhi-check{
					display:flex;
					align-items:center;
					justify-content:center;
					flex:0 0 58px;
					width:58px;
					height:58px;
					border-radius:17px;
					background:var(--rhi-accent);
					color:#fff;
					font-size:30px;
					font-weight:800;
					line-height:1;
				}

				.rhi-check.rhi-check-pending{
					background:#F59E0B;
				}

				.rhi-heading{
					min-width:0;
				}

				.rhi-heading h2{
					margin:0 0 5px!important;
					padding:0!important;
					color:var(--rhi-text)!important;
					font-size:25px!important;
					font-weight:800!important;
					line-height:1.6!important;
				}

				.rhi-heading p{
					margin:0!important;
					color:var(--rhi-muted)!important;
					font-size:12px!important;
					line-height:1.9!important;
				}

				.rhi-logo-box{
					display:flex;
					align-items:center;
					justify-content:center;
					flex:0 0 auto;
					min-width:170px;
					min-height:62px;
					padding:9px 18px;
					background:var(--rhi-surface);
					border:1px solid var(--rhi-border);
					border-radius:15px;
					overflow:hidden;
				}

				.rhi-logo-box img{
					display:block!important;
					width:auto!important;
					height:auto!important;
					max-width:170px!important;
					max-height:52px!important;
					object-fit:contain!important;
				}

				.rhi-site-name{
					color:var(--rhi-text);
					font-size:15px;
					font-weight:800;
					white-space:nowrap;
				}

				.rhi-meta{
					display:grid;
					grid-template-columns:repeat(5,minmax(0,1fr));
					gap:10px;
					margin-bottom:20px;
				}

				.rhi-meta-card{
					min-width:0;
					padding:14px;
					background:var(--rhi-surface);
					border:1px solid var(--rhi-border);
					border-radius:14px;
				}

				.rhi-meta-label{
					display:block;
					margin-bottom:5px;
					color:var(--rhi-muted);
					font-size:10px;
					font-weight:500;
				}

				.rhi-meta-value{
					display:block;
					color:var(--rhi-text);
					font-size:13px;
					font-weight:700;
					line-height:1.8;
					word-break:break-word;
				}

				.rhi-print-area{
					padding:21px;
					margin-bottom:20px;
					background:var(--rhi-surface);
					border:1px solid var(--rhi-border);
					border-radius:19px;
				}

				.rhi-section-head{
					display:flex;
					align-items:center;
					justify-content:space-between;
					gap:15px;
					margin-bottom:17px;
					padding-bottom:14px;
					border-bottom:1px solid rgba(100,120,140,.14);
				}

				.rhi-section-title{
					margin:0!important;
					padding:0!important;
					border:0!important;
					color:var(--rhi-text)!important;
					font-size:17px!important;
					font-weight:800!important;
					line-height:1.7!important;
				}

				.rhi-print-btn{
					display:inline-flex!important;
					align-items:center!important;
					justify-content:center!important;
					gap:7px!important;
					min-height:39px!important;
					padding:7px 14px!important;
					margin:0!important;
					border:1px solid var(--rhi-primary)!important;
					border-radius:9px!important;
					background:var(--rhi-primary)!important;
					color:#fff!important;
					font-size:11px!important;
					font-weight:700!important;
					line-height:1!important;
					box-shadow:none!important;
					cursor:pointer!important;
					transition:opacity .2s ease,transform .2s ease!important;
				}

				.rhi-print-btn:hover{
					color:#fff!important;
					opacity:.9;
					transform:translateY(-1px);
				}

				.rhi-print-btn svg{
					display:block;
					flex:0 0 auto;
				}

				.rhi-table{
					width:100%!important;
					margin:0!important;
					padding:0!important;
					table-layout:fixed!important;
					border-collapse:separate!important;
					border-spacing:0!important;
					overflow:hidden;
					background:var(--rhi-surface)!important;
					border:1px solid var(--rhi-border)!important;
					border-radius:13px!important;
				}

				.rhi-table thead,
				.rhi-table tbody,
				.rhi-table tr{
					border:0!important;
				}

				.rhi-table th{
					padding:13px 14px!important;
					background:#F5F7F9!important;
					border:0!important;
					border-bottom:1px solid var(--rhi-border)!important;
					border-left:1px solid var(--rhi-border)!important;
					color:var(--rhi-muted)!important;
					font-size:11px!important;
					font-weight:600!important;
					line-height:1.8!important;
					vertical-align:middle!important;
				}

				.rhi-table td{
					padding:15px 14px!important;
					background:var(--rhi-surface)!important;
					border:0!important;
					border-bottom:1px solid var(--rhi-border)!important;
					border-left:1px solid var(--rhi-border)!important;
					color:var(--rhi-text)!important;
					font-size:12px!important;
					line-height:1.9!important;
					vertical-align:middle!important;
				}

				.rhi-table th:last-child,
				.rhi-table td:last-child{
					border-left:0!important;
				}

				/* RTL visual order: row / description / qty / amount */
				.rhi-table th:nth-child(1),
				.rhi-table td:nth-child(1){
					width:7%;
					text-align:center!important;
				}

				.rhi-table th:nth-child(2),
				.rhi-table td:nth-child(2){
					width:55%;
					text-align:right!important;
				}

				.rhi-table th:nth-child(3),
				.rhi-table td:nth-child(3){
					width:13%;
					text-align:center!important;
				}

				/* Requested: amount heading centered */
				.rhi-table th:nth-child(4){
					width:25%;
					text-align:center!important;
				}

				/* Requested: amount values right aligned */
				.rhi-table td:nth-child(4){
					width:25%;
					text-align:right!important;
					direction:rtl!important;
					white-space:nowrap;
				}

				.rhi-product-name{
					display:block;
					color:var(--rhi-text);
					font-size:13px;
					font-weight:700;
					line-height:1.9;
				}

				.rhi-product-meta{
					display:block;
					margin-top:3px;
					color:var(--rhi-muted);
					font-size:10px;
					font-weight:400;
					line-height:1.8;
				}

				.rhi-product-meta p,
				.rhi-product-meta dl{
					margin:0!important;
				}

				.rhi-product-meta dt,
				.rhi-product-meta dd{
					display:inline!important;
					float:none!important;
					margin:0!important;
					padding:0!important;
				}

				.rhi-finance-row td{
					background:#F8FAFB!important;
				}

				.rhi-finance-row td:first-child,
				.rhi-shipping-row td:first-child,
				.rhi-total-row td:first-child{
					text-align:right!important;
				}

				.rhi-finance-label{
					color:var(--rhi-muted);
					font-weight:600;
				}

				.rhi-shipping-row td{
					background:#F5F9FC!important;
				}

				.rhi-shipping-label{
					color:var(--rhi-text);
					font-weight:700;
				}

				.rhi-shipping-method{
					display:block;
					margin-top:2px;
					color:var(--rhi-muted);
					font-size:10px;
					font-weight:400;
					line-height:1.8;
				}

				.rhi-finance-row td:last-child,
				.rhi-shipping-row td:last-child,
				.rhi-total-row td:last-child{
					text-align:right!important;
					direction:rtl!important;
					white-space:nowrap;
				}

				.rhi-total-row td{
					padding-top:18px!important;
					padding-bottom:18px!important;
					background:#EDF5FA!important;
					border-bottom:0!important;
				}

				.rhi-total-label,
				.rhi-total-price{
					color:var(--rhi-primary)!important;
					font-size:15px;
					font-weight:800;
				}

				.rhi-total-price{
					font-size:17px;
					font-weight:900;
				}

				.rhi-addresses{
					display:grid;
					grid-template-columns:repeat(2,minmax(0,1fr));
					gap:20px;
					margin-bottom:20px;
				}

				.rhi-address-card{
					min-width:0;
					padding:20px;
					background:var(--rhi-surface);
					border:1px solid var(--rhi-border);
					border-radius:18px;
				}

				.rhi-address-title{
					margin:0 0 13px!important;
					padding:0 0 11px!important;
					border-bottom:1px solid rgba(100,120,140,.14);
					color:var(--rhi-text)!important;
					font-size:15px!important;
					font-weight:800!important;
					line-height:1.7!important;
				}

				.rhi-address-content,
				.rhi-address-content address{
					margin:0!important;
					padding:0!important;
					border:0!important;
					color:var(--rhi-text)!important;
					font-style:normal!important;
					font-size:12px!important;
					line-height:2!important;
				}

				.rhi-address-extra{
					margin-top:10px;
					padding-top:10px;
					border-top:1px dashed var(--rhi-border);
					color:var(--rhi-muted);
					font-size:10px;
					line-height:2;
				}

				.rhi-footer{
					display:flex;
					align-items:center;
					justify-content:space-between;
					gap:20px;
					padding:17px 19px;
					background:var(--rhi-surface);
					border:1px solid var(--rhi-border);
					border-radius:17px;
				}

				.rhi-footer-note{
					color:var(--rhi-muted);
					font-size:11px;
					line-height:2;
				}

				.rhi-footer-note strong{
					display:block;
					color:var(--rhi-text);
					font-size:12px;
					font-weight:800;
				}

				.rhi-actions{
					display:flex;
					align-items:center;
					gap:8px;
					flex-wrap:wrap;
				}

				.rhi-button{
					display:inline-flex!important;
					align-items:center!important;
					justify-content:center!important;
					min-height:42px!important;
					padding:9px 17px!important;
					border:1px solid var(--rhi-border)!important;
					border-radius:10px!important;
					background:var(--rhi-surface)!important;
					color:var(--rhi-text)!important;
					text-decoration:none!important;
					font-size:11px!important;
					font-weight:700!important;
					line-height:1.5!important;
					box-shadow:none!important;
				}

				.rhi-button-primary{
					background:var(--rhi-primary)!important;
					border-color:var(--rhi-primary)!important;
					color:#fff!important;
				}

				@media (max-width:992px){
					.rhi-meta{
						grid-template-columns:repeat(2,minmax(0,1fr));
					}
					.rhi-addresses{
						grid-template-columns:1fr;
					}
				}

				@media (max-width:640px){
					.rhi-invoice{
						margin:15px auto;
						padding:13px;
						border-radius:17px;
					}

					.rhi-top{
						flex-direction:column;
						align-items:stretch;
					}

					.rhi-logo-box{
						width:100%;
						min-width:0;
					}

					.rhi-meta{
						grid-template-columns:repeat(2,minmax(0,1fr));
					}

					.rhi-print-area{
						padding:13px;
					}

					.rhi-section-head{
						align-items:center;
					}

					.rhi-table-wrap{
						width:100%;
						overflow-x:auto;
						-webkit-overflow-scrolling:touch;
					}

					.rhi-table{
						min-width:680px!important;
					}

					.rhi-footer{
						flex-direction:column;
						align-items:stretch;
					}

					.rhi-actions{
						display:grid;
						grid-template-columns:1fr 1fr;
					}

					.rhi-button-primary{
						grid-column:1/-1;
					}
				}

				@media print{
					@page{
						size:A4 portrait;
						margin:10mm;
					}

					html,
					body{
						margin:0!important;
						padding:0!important;
						background:#fff!important;
					}

					body.rhi-printing > *:not(.rhi-print-clone){
						display:none!important;
					}

					body.rhi-printing{
						background:#fff!important;
					}

					.rhi-print-clone{
						display:block!important;
						position:static!important;
						width:100%!important;
						max-width:none!important;
						margin:0!important;
						padding:0!important;
						background:#fff!important;
						border:0!important;
						border-radius:0!important;
						box-shadow:none!important;
						color:#000!important;
						direction:rtl!important;
						font-family:"IRANYekanX",Sans-serif!important;
					}

					.rhi-print-clone,
					.rhi-print-clone *{
						box-sizing:border-box!important;
						font-family:"IRANYekanX",Sans-serif!important;
					}

					.rhi-print-clone .rhi-print-btn{
						display:none!important;
					}

					.rhi-print-clone .rhi-section-head{
						margin-bottom:12px!important;
					}

					.rhi-print-clone .rhi-table-wrap{
						overflow:visible!important;
					}

					.rhi-print-clone .rhi-table{
						width:100%!important;
						min-width:0!important;
					}

					.rhi-print-clone .rhi-table thead{
						display:table-header-group!important;
					}

					.rhi-print-clone .rhi-table tr{
						break-inside:avoid!important;
						page-break-inside:avoid!important;
					}
				}
			</style>
			<?php
		}

		/* ---------------------------------------------------------
		 * Front scripts
		 * ------------------------------------------------------ */

		public static function print_scripts() {
			if ( ! self::is_invoice_page() ) {
				return;
			}
			?>
			<script>
			(function () {
				'use strict';

				function findHeaderLogo() {
					var selectors = [
						'.custom-logo-link img',
						'img.custom-logo',
						'.wd-logo img',
						'.whb-logo img',
						'.site-logo img',
						'.header-logo img',
						'.navbar-brand img',
						'header .logo img',
						'header img[alt*="logo" i]'
					];

					for (var i = 0; i < selectors.length; i++) {
						var img = document.querySelector(selectors[i]);

						if (
							img &&
							img.closest('.rhi-invoice') === null &&
							(img.currentSrc || img.src)
						) {
							return img;
						}
					}

					return null;
				}

				function syncSiteLogo() {
					var box = document.querySelector('.rhi-logo-box');

					if (!box || box.querySelector('img.rhi-site-logo-img')) {
						return;
					}

					var source = findHeaderLogo();

					if (!source) {
						return;
					}

					var img = document.createElement('img');

					img.className = 'rhi-site-logo-img';
					img.src = source.currentSrc || source.src;
					img.alt = source.alt || document.title || '';
					img.loading = 'eager';

					box.innerHTML = '';
					box.appendChild(img);
				}

				function removePrintClone() {
					document.body.classList.remove('rhi-printing');

					document.querySelectorAll('.rhi-print-clone').forEach(function (el) {
						el.remove();
					});
				}

				window.rhiPrintInvoice = function () {
					var source = document.querySelector('.rhi-print-area');

					if (!source) {
						return;
					}

					removePrintClone();

					var clone = source.cloneNode(true);

					clone.classList.add('rhi-print-clone');

					clone.querySelectorAll('.rhi-print-btn').forEach(function (button) {
						button.remove();
					});

					document.body.appendChild(clone);
					document.body.classList.add('rhi-printing');

					requestAnimationFrame(function () {
						setTimeout(function () {
							window.print();
						}, 100);
					});
				};

				window.addEventListener('afterprint', removePrintClone);

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', syncSiteLogo);
				} else {
					syncSiteLogo();
				}

				window.addEventListener('load', syncSiteLogo);
			})();
			</script>
			<?php
		}

		/* ---------------------------------------------------------
		 * Invoice output
		 * ------------------------------------------------------ */

		public static function render_invoice( $order_id ) {
			static $rendered = false;

			if ( $rendered || ! $order_id || ! function_exists( 'wc_get_order' ) ) {
				return;
			}

			$rendered = true;

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$s = self::settings();

			$is_order_pay = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' );
			$is_paid      = $order->is_paid() || (bool) $order->get_date_paid();

			if ( $is_order_pay && ! $is_paid ) {
				$top_title = $order->has_status( 'failed' )
					? rhi_text( 'پرداخت ناموفق بود؛ امکان پرداخت مجدد وجود دارد', 'Payment failed; you can try again' )
					: rhi_text( 'سفارش ثبت شد؛ در انتظار تکمیل پرداخت', 'Order placed; awaiting payment' );

				$top_text = sprintf(
					rhi_text( 'وضعیت فعلی سفارش «%s» است. اقلام سفارش را بررسی کنید و ادامه پرداخت را از همین صفحه انجام دهید.', 'The current order status is “%s”. Review the items and continue payment from this page.' ),
					wc_get_order_status_name( $order->get_status() )
				);

				$top_icon       = '!';
				$top_icon_class = ' rhi-check-pending';
			} else {
				$top_title      = $s['success_title'];
				$top_text       = '';
				$top_icon       = '✓';
				$top_icon_class = '';
			}

			$site_name = get_bloginfo( 'name' );

			$customer_name = trim(
				$order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
			);

			if ( '' === $customer_name ) {
				$customer_name = rhi_text( 'مشتری', 'Customer' );
			}

			$order_date = $order->get_date_created()
				? wc_format_datetime( $order->get_date_created() )
				: '—';

			$payment_title = $order->get_payment_method_title();

			if ( ! $payment_title ) {
				$payment_title = (float) $order->get_total() <= 0
					? rhi_text( 'بدون نیاز به پرداخت', 'No payment required' )
					: '—';
			}

			$shop_url = wc_get_page_permalink( 'shop' );
			$account_url = wc_get_page_permalink( 'myaccount' );

			$billing_address  = $order->get_formatted_billing_address();
			$shipping_address = $order->get_formatted_shipping_address();

			if ( ! $shipping_address ) {
				$shipping_address = $billing_address;
			}

			$show_shipping_row = (
				$order->get_shipping_method() ||
				(float) $order->get_shipping_total() > 0 ||
				(bool) $shipping_address
			);
			?>
			<section class="rhi-invoice" aria-label="<?php echo esc_attr( $s['invoice_title'] ); ?>">

				<div class="rhi-top">
					<div class="rhi-top-main">
						<div class="rhi-check<?php echo esc_attr( $top_icon_class ); ?>" aria-hidden="true">
							<?php echo esc_html( $top_icon ); ?>
						</div>

						<div class="rhi-heading">
							<h2><?php echo esc_html( $top_title ); ?></h2>
							<p>
								<?php
								if ( $top_text ) {
									echo esc_html( $top_text );
								} else {
									echo esc_html(
										sprintf(
											rhi_text( '%s عزیز، جزئیات سفارش شما در فاکتور زیر ثبت شده است.', 'Dear %s, your order details are shown in the invoice below.' ),
											$customer_name
										)
									);
								}
								?>
							</p>
						</div>
					</div>

					<div class="rhi-logo-box" aria-label="<?php echo esc_attr( $site_name ); ?>">
						<?php echo wp_kses_post( self::get_logo_html() ); ?>
					</div>
				</div>

				<div class="rhi-meta">
					<div class="rhi-meta-card">
						<span class="rhi-meta-label"><?php echo esc_html( rhi_text( 'شماره سفارش', 'Order number' ) ); ?></span>
						<span class="rhi-meta-value">#<?php echo esc_html( $order->get_order_number() ); ?></span>
					</div>

					<div class="rhi-meta-card">
						<span class="rhi-meta-label"><?php echo esc_html( rhi_text( 'تاریخ سفارش', 'Order date' ) ); ?></span>
						<span class="rhi-meta-value"><?php echo esc_html( $order_date ); ?></span>
					</div>

					<div class="rhi-meta-card">
						<span class="rhi-meta-label"><?php echo esc_html( rhi_text( 'وضعیت', 'Status' ) ); ?></span>
						<span class="rhi-meta-value"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
					</div>

					<div class="rhi-meta-card">
						<span class="rhi-meta-label"><?php echo esc_html( rhi_text( 'روش پرداخت', 'Payment method' ) ); ?></span>
						<span class="rhi-meta-value"><?php echo esc_html( $payment_title ); ?></span>
					</div>

					<div class="rhi-meta-card">
						<span class="rhi-meta-label"><?php echo esc_html( rhi_text( 'مبلغ نهایی', 'Total' ) ); ?></span>
						<span class="rhi-meta-value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
					</div>
				</div>

				<div class="rhi-print-area">
					<div class="rhi-section-head">
						<h3 class="rhi-section-title"><?php echo esc_html( $s['invoice_title'] ); ?></h3>

						<button
							type="button"
							class="rhi-print-btn"
							onclick="rhiPrintInvoice();"
							aria-label="<?php echo esc_attr( rhi_text( 'چاپ فاکتور', 'Print invoice' ) ); ?>"
						>
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<path d="M6 9V3H18V9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M6 18H4C2.9 18 2 17.1 2 16V11C2 9.9 2.9 9 4 9H20C21.1 9 22 9.9 22 11V16C22 17.1 21.1 18 20 18H18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M6 14H18V21H6V14Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
							</svg>
							<span><?php echo esc_html( rhi_text( 'چاپ فاکتور', 'Print invoice' ) ); ?></span>
						</button>
					</div>

					<div class="rhi-table-wrap">
						<table class="rhi-table">
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html( rhi_text( 'ردیف', 'No.' ) ); ?></th>
									<th scope="col"><?php echo esc_html( rhi_text( 'شرح', 'Description' ) ); ?></th>
									<th scope="col"><?php echo esc_html( rhi_text( 'تعداد', 'Quantity' ) ); ?></th>
									<th scope="col"><?php echo esc_html( rhi_text( 'مبلغ', 'Amount' ) ); ?></th>
								</tr>
							</thead>

							<tbody>
								<?php
								$row = 1;

								foreach ( $order->get_items( 'line_item' ) as $item ) :
									$product = $item->get_product();
									$sku     = ( $product && $product->get_sku() ) ? $product->get_sku() : '';
									?>
									<tr>
										<td><?php echo esc_html( $row ); ?></td>

										<td>
											<span class="rhi-product-name"><?php echo esc_html( $item->get_name() ); ?></span>

											<?php if ( $sku ) : ?>
												<span class="rhi-product-meta">
													<?php echo esc_html( rhi_text( 'کد محصول:', 'SKU:' ) ); ?> <?php echo esc_html( $sku ); ?>
												</span>
											<?php endif; ?>

											<div class="rhi-product-meta">
												<?php
												wc_display_item_meta(
													$item,
													array(
														'echo'         => true,
														'autop'        => false,
														'separator'    => '، ',
														'label_before' => '<strong>',
														'label_after'  => ':</strong> ',
													)
												);
												?>
											</div>
										</td>

										<td><?php echo esc_html( $item->get_quantity() ); ?></td>

										<td>
											<?php
											echo wp_kses_post(
												$order->get_formatted_line_subtotal( $item )
											);
											?>
										</td>
									</tr>
									<?php
									$row++;
								endforeach;
								?>

								<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
									<tr class="rhi-finance-row">
										<td colspan="3">
										<span class="rhi-finance-label"><?php echo esc_html( rhi_text( 'تخفیف', 'Discount' ) ); ?></span>
										</td>
										<td>
											-<?php
											echo wp_kses_post(
												wc_price(
													$order->get_discount_total(),
													array( 'currency' => $order->get_currency() )
												)
											);
											?>
										</td>
									</tr>
								<?php endif; ?>

								<?php foreach ( $order->get_fees() as $fee ) : ?>
									<tr class="rhi-finance-row">
										<td colspan="3">
											<span class="rhi-finance-label"><?php echo esc_html( $fee->get_name() ); ?></span>
										</td>
										<td>
											<?php
											echo wp_kses_post(
												wc_price(
													$fee->get_total(),
													array( 'currency' => $order->get_currency() )
												)
											);
											?>
										</td>
									</tr>
								<?php endforeach; ?>

								<?php if ( (float) $order->get_total_tax() > 0 ) : ?>
									<tr class="rhi-finance-row">
										<td colspan="3">
										<span class="rhi-finance-label"><?php echo esc_html( rhi_text( 'مالیات', 'Tax' ) ); ?></span>
										</td>
										<td>
											<?php
											echo wp_kses_post(
												wc_price(
													$order->get_total_tax(),
													array( 'currency' => $order->get_currency() )
												)
											);
											?>
										</td>
									</tr>
								<?php endif; ?>

								<?php if ( $show_shipping_row ) : ?>
									<tr class="rhi-shipping-row">
										<td colspan="3">
											<span class="rhi-shipping-label">
												<?php echo esc_html( rhi_text( 'حمل و نقل', 'Shipping' ) ); ?>

												<?php if ( $order->get_shipping_method() ) : ?>
													<span class="rhi-shipping-method">
														<?php echo esc_html( $order->get_shipping_method() ); ?>
													</span>
												<?php endif; ?>
											</span>
										</td>

										<td>
											<?php
											if ( (float) $order->get_shipping_total() > 0 ) {
												echo wp_kses_post(
													wc_price(
														$order->get_shipping_total(),
														array( 'currency' => $order->get_currency() )
													)
												);
											} else {
												echo esc_html( rhi_text( 'رایگان', 'Free' ) );
											}
											?>
										</td>
									</tr>
								<?php endif; ?>

								<tr class="rhi-total-row">
									<td colspan="3">
									<span class="rhi-total-label"><?php echo esc_html( rhi_text( 'جمع نهایی', 'Total' ) ); ?></span>
									</td>
									<td>
										<span class="rhi-total-price">
											<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
										</span>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="rhi-addresses">
					<div class="rhi-address-card">
						<h3 class="rhi-address-title"><?php echo esc_html( rhi_text( 'اطلاعات صورتحساب', 'Billing information' ) ); ?></h3>

						<div class="rhi-address-content">
							<address>
								<?php
								echo $billing_address
									? wp_kses_post( $billing_address )
									: esc_html( rhi_text( 'آدرسی ثبت نشده است.', 'No address was recorded.' ) );
								?>
							</address>

							<?php if ( $order->get_billing_phone() || $order->get_billing_email() ) : ?>
								<div class="rhi-address-extra">
									<?php if ( $order->get_billing_phone() ) : ?>
										<div>
											<?php echo esc_html( rhi_text( 'تلفن:', 'Phone:' ) ); ?>
											<strong><?php echo esc_html( $order->get_billing_phone() ); ?></strong>
										</div>
									<?php endif; ?>

									<?php if ( $order->get_billing_email() ) : ?>
										<div>
											<?php echo esc_html( rhi_text( 'ایمیل:', 'Email:' ) ); ?>
											<strong><?php echo esc_html( $order->get_billing_email() ); ?></strong>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="rhi-address-card">
						<h3 class="rhi-address-title"><?php echo esc_html( rhi_text( 'آدرس ارسال', 'Shipping address' ) ); ?></h3>

						<div class="rhi-address-content">
							<address>
								<?php
								echo $shipping_address
									? wp_kses_post( $shipping_address )
									: esc_html( rhi_text( 'آدرس ارسالی ثبت نشده است.', 'No shipping address was recorded.' ) );
								?>
							</address>
						</div>
					</div>
				</div>

				<div class="rhi-footer">
					<div class="rhi-footer-note">
						<strong><?php echo esc_html( $site_name ); ?></strong>
						<?php echo esc_html( $s['footer_note'] ); ?>
					</div>

					<div class="rhi-actions">
						<button
							type="button"
							class="rhi-button rhi-button-primary"
							onclick="rhiPrintInvoice();"
						>
							<?php echo esc_html( rhi_text( 'چاپ فاکتور', 'Print invoice' ) ); ?>
						</button>

						<a href="<?php echo esc_url( $account_url ); ?>" class="rhi-button">
							<?php echo esc_html( rhi_text( 'حساب کاربری', 'My account' ) ); ?>
						</a>

						<a href="<?php echo esc_url( $shop_url ); ?>" class="rhi-button">
							<?php echo esc_html( rhi_text( 'بازگشت به فروشگاه', 'Back to shop' ) ); ?>
						</a>
					</div>
				</div>

			</section>
			<?php
		}
	}

	RHI_Woo_Invoice_MU::init();
}

if ( function_exists( 'ritahost_register_admin_tool' ) ) {
	ritahost_register_admin_tool( 'rhi-woo-invoice', 'فاکتور سفارش', 'Order Invoice', 'ظاهر فاکتور، صفحه تشکر و پرداخت سفارش ووکامرس را تنظیم می‌کند.', 'Configures the WooCommerce invoice, thank-you, and order-payment presentation.', 'manage_woocommerce' );
}

