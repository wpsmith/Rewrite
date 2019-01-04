<?php
/**
 * Rewrite Endpoint Class
 *
 * Creates additional endpoints off post type URLs.
 *
 * You may copy, distribute and modify the software as long as you track changes/dates in source files.
 * Any modifications to or software including (via compiler) GPL-licensed code must also be made
 * available under the GPL along with build & install instructions.
 *
 * @package    WPS\Rewrite
 * @author     Travis Smith <t@wpsmith.net>
 * @copyright  2015-2018 Travis Smith
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @link       https://github.com/wpsmith/WPS
 * @version    1.0.0
 * @since      0.1.0
 */

namespace WPS\Rewrite;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Rewrite_Endpoint' ) ) {
	/**
	 * Class Rewrite_Endpoint
	 *
	 * @deprecated
	 * @package WPS\Rewrite
	 */
	class Rewrite_Endpoint {

		/**
		 * Rewrite_Endpoint constructor.
		 *
		 * @param array $args Array of class args.
		 */
		public function __construct( $args ) {
			$args = wp_parse_args( $args, $this->defaults() );

			$this->places    = $args['places'];
			$this->template  = $args['template'];
			$this->var       = $args['var'];
			$this->post_type = $args['post_type'];
			$this->post_meta = $args['post_meta'];

			// Ensure required args.
			if ( '' === $this->template || '' === $this->var ) {
				return;
			}

			// Do Hooks.
			add_action( 'init', array( $this, 'add_rewrite_endpoint' ) );
			add_action( 'template_redirect', array( $this, 'template_redirect' ) );
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		}

		/**
		 * Default args.
		 *
		 * @return array Array of defaults.
		 */
		public function defaults() {
			return array(
				'places'    => EP_PERMALINK | EP_PAGES,
				'template'  => '',
				'var'       => '',
				'post_meta' => '',
				'post_type' => '',
			);
		}

		/**
		 * Adds endpoint.
		 */
		public function add_rewrite_endpoint() {
			add_rewrite_endpoint( $this->var, EP_PERMALINK | EP_PAGES );
		}

		/**
		 * Adjusts WP_Query as necessary.
		 *
		 * @param \WP_Query $query Current Query.
		 */
		public function pre_get_posts( $query ) {
			if ( ! $this->has_query_var() || ! $query->is_main_query() ) {
				return;
			}

			global $wp_query;

			// Make sure we have what we need!
			if (
				! isset( $wp_query->query_vars['name'] ) ||
				! isset( $wp_query->query_vars['post_type'] ) ||
				$wp_query->query_vars['post_type'] === $this->post_type
			) {
				return;
			}

			// Get the Post Object.
			$post = function_exists( 'wpcom_vip_get_page_by_path' ) ? wpcom_vip_get_page_by_path( $wp_query->query_vars['name'], OBJECT, $wp_query->query_vars['post_type'] ) : get_page_by_path( $wp_query->query_vars['name'], OBJECT, $wp_query->query_vars['post_type'] );

			// Set the value of the query.
			global $wp_the_query;

			$ids = get_post_meta( $post->ID, $this->post_meta, true );

			$wp_the_query->query[ $this->var ] = $ids;
			$wp_the_query->set( $this->var, $ids );

		}

		/**
		 * Whether the current query has this's query var.
		 *
		 * @return bool
		 */
		private function has_query_var() {
			global $wp_query;

			// Must be a request with our query var and a singular object.
			return ( isset( $wp_query->query_vars[ $this->var ] ) && is_singular() );
		}

		/**
		 * Conditionally includes the template.
		 */
		public function template_redirect() {
			if ( ! $this->has_query_var() || is_admin() ) {
				return;
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			global $wp_filesystem;

			// include custom template.
			if ( $wp_filesystem->is_file( $this->template ) ) {
				include $this->template;
			}

			exit;
		}

	}
}