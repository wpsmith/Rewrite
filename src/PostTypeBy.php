<?php
/**
 * Post Type by Taxonomy Rewrite Endpoint Class
 *
 * Creates additional rewrite URLs based on post type and terms.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    WPS\Rewrite
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015-2019 Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link       https://github.com/wpsmith/WPS
 * @version    1.0.0
 * @since      0.1.0
 */

namespace WPS\WP\Rewrite;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\PostTypeBy' ) ) {
	/**
	 * Class PostTypeBy.
	 *
	 * @package WPS\WP\Rewrite
	 */
	class PostTypeBy extends PostTypeRewrite {
		/**
		 * The default order of the slugs.
		 *
		 * @var string[]
		 */
		protected array $allowed_order_tokens = [
			'%post_type%',
		];

		/**
		 * The order of the slugs.
		 *
		 * @var string[]
		 */
		protected array $order = [
			'%post_type%',
		];

		/** PUBLIC API */

		/**
		 * Order for the URL Path.
		 *
		 * @param string[] $order Order of slugs.
		 *
		 * @return array The order.
		 */
		public function set_order( array $order ): array {
			$the_order = [];

			foreach ( $order as $o ) {
				if ( in_array( $o, $this->allowed_order_tokens, true ) ) {
					$the_order[] = $o;
				}
			}

			if ( count( $this->allowed_order_tokens ) >= count( $the_order ) ) {
				$this->order = $the_order;
			}

			return $this->order;
		}
	}
}
