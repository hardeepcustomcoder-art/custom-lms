<?php
/**
 * WooCommerce integration for Custom LMS.
 * Handles product linking, dynamic pricing, and order triggers.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_WooCommerce
 */
class CLMS_WooCommerce {

	/**
	 * Check if WooCommerce is installed and active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get WooCommerce products suitable for course linking.
	 *
	 * @return array Array of [ 'id' => ..., 'name' => ..., 'price' => ... ]
	 */
	public static function get_available_products() {
		if ( ! self::is_active() ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'limit'   => -1,
				'status'  => array( 'publish', 'draft' ),
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		$options = array();
		foreach ( $products as $product ) {
			$options[] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'formatted'     => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			);
		}

		return $options;
	}

	/**
	 * Dynamically get course price.
	 * If linked to a WooCommerce product, fetches the live WooCommerce price.
	 * Otherwise falls back to manual _clms_price.
	 *
	 * @param int $course_id Course post ID.
	 * @return array Array containing 'price', 'regular_price', 'sale_price', 'is_on_sale', 'formatted', 'source'
	 */
	public static function get_course_price_data( $course_id ) {
		$wc_product_id = (int) get_post_meta( $course_id, '_clms_wc_product_id', true );

		if ( $wc_product_id && self::is_active() ) {
			$product = wc_get_product( $wc_product_id );
			if ( $product ) {
				$price         = $product->get_price();
				$regular_price = $product->get_regular_price();
				$sale_price    = $product->get_sale_price();
				$is_on_sale    = $product->is_on_sale();

				return array(
					'product_id'    => $wc_product_id,
					'price'         => '' !== $price ? (float) $price : 0.00,
					'regular_price' => '' !== $regular_price ? (float) $regular_price : null,
					'sale_price'    => '' !== $sale_price ? (float) $sale_price : null,
					'is_on_sale'    => $is_on_sale,
					'formatted'     => '' !== $price ? wp_strip_all_tags( wc_price( $price ) ) : __( 'Free', 'custom-lms' ),
					'raw_formatted' => wc_price( $price ),
					'source'        => 'woocommerce',
				);
			}
		}

		// Fallback to manual meta
		$manual_price = get_post_meta( $course_id, '_clms_price', true );
		$num_price    = '' !== $manual_price ? (float) $manual_price : 0.00;

		return array(
			'product_id'    => 0,
			'price'         => $num_price,
			'regular_price' => null,
			'sale_price'    => null,
			'is_on_sale'    => false,
			'formatted'     => $num_price > 0 ? '$' . number_format( $num_price, 2 ) : __( 'Free', 'custom-lms' ),
			'raw_formatted' => $num_price > 0 ? '$' . number_format( $num_price, 2 ) : __( 'Free', 'custom-lms' ),
			'source'        => 'manual',
		);
	}

	/**
	 * Hook registrations for order completions and refunds.
	 */
	public static function init() {
		// Auto-enroll on order completion
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_order_completed' ) );

		// Revoke on refund or cancellation
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_order_revoked' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_order_revoked' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'handle_order_revoked' ) );
	}

	/**
	 * Auto-enroll student into courses purchased in order.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 */
	public static function handle_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		global $wpdb;
		$table = CLMS_DB::get_enrollments_table();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			// Find courses linked to this product ID
			$courses = get_posts(
				array(
					'post_type'      => 'clms_course',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_key'       => '_clms_wc_product_id',
					'meta_value'     => $product_id,
				)
			);

			foreach ( $courses as $course ) {
				$wpdb->replace(
					$table,
					array(
						'user_id'     => $user_id,
						'course_id'   => $course->ID,
						'status'      => 'active',
						'source'      => 'woocommerce',
						'order_id'    => $order_id,
						'enrolled_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s', '%d', '%s' )
				);
			}
		}
	}

	/**
	 * Revoke course enrollment on refund/cancellation.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 */
	public static function handle_order_revoked( $order_id ) {
		global $wpdb;
		$table = CLMS_DB::get_enrollments_table();
		$wpdb->update(
			$table,
			array( 'status' => 'revoked' ),
			array( 'order_id' => $order_id, 'source' => 'woocommerce' ),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}
}
