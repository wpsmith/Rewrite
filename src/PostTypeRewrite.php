<?php
/**
 * Post Type Rewrite Endpoint Class
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

if ( ! class_exists( 'WPS\Rewrite\PostTypeRewrite' ) ) {
	/**
	 * Class PostTypeRewrite
	 *
	 * @package WPS\Rewrite
	 */
	class PostTypeRewrite extends RewriteEndpoint {

		/**
		 * Post Type.
		 *
		 * @var string
		 */
		protected $post_type;

		/**
		 * Rewrite Rules.
		 *
		 * @var array
		 */
		protected $rewrites = [
			'date'  => false,
			'feed'  => false,
			'page'  => true,
			'embed' => false,
		];

		/**
		 * Prefix of the URL base.
		 *
		 * @var string
		 */
		protected $prefix = '';

		/**
		 * PostTypeRewrite constructor.
		 *
		 * @throws \Exception When post_type and taxonomy are not set.
		 *
		 * @param array|string $slug Endpoint slug.
		 * @param array $args Array of class args.
		 */
		public function __construct( $slug, $args = array() ) {

			$args = $this->get_args( $slug, $args );

			if ( ! isset( $args['post_type'] ) ) {
				throw new \Exception( __( 'post_type are required to be set.', 'wps-rewrite' ) );
			}
			$this->post_type = $args['post_type'];

			// Construct.
			parent::__construct( $this->var, $args );

		}

		/** PUBLIC API */

		/**
		 * Prefix for the URL path.
		 *
		 * @param string $prefix Prefix.
		 */
		public function set_prefix( $prefix ) {

			$this->prefix = '' !== $prefix ? trailingslashit( $prefix ) : '';

		}

		/**
		 * Adds all the rewrite rules.
		 */
		public function add_all_rewrites() {

			$this->rewrites = [
				'date'  => true,
				'feed'  => true,
				'page'  => true,
				'embed' => true,
			];

		}

		/**
		 * Adds date rewrite rules.
		 */
		public function add_date_rewrites() {

			$this->rewrites['date'] = true;

		}

		/**
		 * Ensures that date rewrite rules are not added.
		 */
		public function remove_date_rewrites() {

			$this->rewrites['date'] = false;

		}

		/**
		 * Adds embed rewrite rules.
		 */
		public function add_embed_rewrites() {

			$this->rewrites['embed'] = true;

		}

		/**
		 * Ensures that embed rewrite rules are not added.
		 */
		public function remove_embed_rewrites() {

			$this->rewrites['embed'] = false;

		}

		/**
		 * Adds feed rewrite rules.
		 */
		public function add_feed_rewrites() {

			$this->rewrites['feed'] = true;

		}

		/**
		 * Ensures that feed rewrite rules are not added.
		 */
		public function remove_feed_rewrites() {

			$this->rewrites['feed'] = false;

		}

		/** PRIVATE */

		/**
		 * Gets the Custom Post Type rewrite rules.
		 *
		 * @access private
		 *
		 * @param string $path Path before the dynamic rules.
		 *
		 * @return array Rewrite rules.
		 */
		protected function get_cpt_rewrite_rules( $path ) {
			$rules = [];

			// {path}/ Archive URL.
			$rules[ $path . '/?$' ] = 'index.php?' . build_query( array(
					'post_type' => $this->post_type,
				) );

			// {path} Paginated Archive URLs.
			if ( $this->rewrites['page'] ) {
				$rules[ $path . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'paged'     => '$matches[1]',
					) );
			}

			if ( $this->rewrites['feed'] ) {
				// {path}/feed/rss/ Feed URLs.
				$rules[ $path . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'feed'      => '$matches[1]',
					) );

				// {path}/rss/ Feed URLs.
				$rules[ $path . '/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'feed'      => '$matches[1]',
					) );
			}

			// {path}/embed/ Embed URLs.
			if ( $this->rewrites['embed'] ) {
				$rules[ $path . '/embed/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'embed'     => 'true',
					) );
			}

			if ( $this->rewrites['date'] ) {

				// YEAR-MONTH-DAY Archives.
				// {path}/YYYY/MM/DD/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'year'      => '$matches[1]',
						'monthnum'  => '$matches[2]',
						'day'       => '$matches[3]',
					) );

				// {path}/YYYY/MM/DD/page/#/ Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'paged'     => '$matches[4]',
						) );
				}

				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/DD/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'feed'      => '$matches[4]',
						) );

					// {path}/YYYY/MM/DD/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'feed'      => '$matches[4]',
						) );
				}

				// {path}/YYYY/MM/DD/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'embed'     => 'true',
						) );
				}

				// YEAR-MONTH Archives.
				// {path}/YYYY/MM/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'year'      => '$matches[1]',
						'monthnum'  => '$matches[2]',
					) );

				// {path}/YYYY/MM/page/#/ Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'paged'     => '$matches[3]',
						) );
				}

				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'feed'      => '$matches[3]',
						) );

					// {path}/YYYY/MM/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'feed'      => '$matches[3]',
						) );
				}

				// {path}/YYYY/MM/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'embed'     => 'true',
						) );
				}

				// YEAR Archives.
				// {path}/YYYY/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/?$' ] = 'index.php?' . build_query( array(
						'post_type' => $this->post_type,
						'year'      => '$matches[1]',
					) );

				// {path}/YYYY/page/#/ Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'paged'     => '$matches[2]',
						) );
				}

				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'feed'      => '$matches[2]',
						) );

					// {path}/YYYY/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'feed'      => '$matches[2]',
						) );
				}

				// {path}/YYYY/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/embed/?$' ] = 'index.php?' . build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'embed'     => 'true',
						) );
				}
			}

			return $rules;
		}

		/**
		 * Adds rewrite rules.
		 *
		 * @access private
		 *
		 * @param \WP_Rewrite $wp_rewrite Current WP_Rewrite instance (passed by reference).
		 *
		 * @return array
		 */
		public function rewrite_rules( $wp_rewrite ) {

			// Get post type slug.
			$post_type_object = get_post_type_object( $this->post_type );
			$post_type_slug   = isset( $post_type_object->rewrite['slug'] ) ? $post_type_object->rewrite['slug'] : $this->post_type;

			$rules = [];

			// Add the custom post type archive rules.
			if ( $post_type_object->has_archive ) {
				$post_type_archive_slug = isset( $post_type_object->has_archive ) && is_string( $post_type_object->has_archive ) && '' !== $post_type_object->has_archive ? $post_type_object->has_archive : $post_type_slug;

				$rules = $this->get_cpt_rewrite_rules( $this->prefix . $post_type_archive_slug );
			}

			// Add rules to top; first match wins!
			$wp_rewrite->rules = $rules + $wp_rewrite->rules;

			return $wp_rewrite->rules;
		}

		/**
		 * Adjusts WP_Query as necessary.
		 *
		 * @access private
		 *
		 * @param \WP_Query $query Current Query.
		 */
		public function pre_get_posts( $query ) {

			// Make sure we are only dealing with our rewrites.
			if ( ! $this->has_query_var() || ! $query->is_main_query() ) {
				return;
			}

			// Make sure we have what we need!
			if (
				! isset( $query->query_vars['name'] ) ||
				! isset( $query->query_vars['post_type'] ) ||
				$query->query_vars['post_type'] !== $this->post_type
			) {
				return;
			}

			// Get the Post Object.
			$post = function_exists( 'wpcom_vip_get_page_by_path' ) ? wpcom_vip_get_page_by_path( $query->query_vars['name'], OBJECT, $query->query_vars['post_type'] ) : get_page_by_path( $query->query_vars['name'], OBJECT, $query->query_vars['post_type'] );

			// Set the value of the query.
			global $wp_the_query;

			if ( isset( $this->var ) ) {
				$wp_the_query->query[ $this->var ] = $post->ID;
				$wp_the_query->set( $this->var, $post->ID );
			}

		}

		/**
		 * Default args.
		 *
		 * @access private
		 * @return array Array of defaults.
		 */
		public function defaults() {

			$defaults              = parent::defaults();
			$defaults['post_type'] = '';

			return $defaults;

		}

		/**
		 * Whether the current query has this's query var.
		 *
		 * @access private
		 * @return bool
		 */
		protected function has_query_var() {
			global $wp_query;

			return (
				parent::has_query_var() ||
				(
					isset( $wp_query->query_vars[ $this->post_type ] ) &&
					'' !== $wp_query->query_vars[ $this->post_type ]
				) ||
				is_post_type_archive( $this->post_type )
			);

		}

	}
}