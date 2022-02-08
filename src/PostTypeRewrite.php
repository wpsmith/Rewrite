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

if ( ! class_exists( __NAMESPACE__ . '\PostTypeRewrite' ) ) {
	/**
	 * Class PostTypeRewrite.
	 *
	 * @package WPS\WP\Rewrite
	 */
	class PostTypeRewrite extends RewriteEndpoint {

		/**
		 * Post Type.
		 *
		 * @var string
		 */
		protected string $post_type;

		/**
		 * Rewrite Rules.
		 *
		 * @var array
		 */
		protected array $rewrites = [
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
		protected string $prefix = '';

		/**
		 * PostTypeRewrite constructor.
		 *
		 * @param array|string $slug Endpoint slug.
		 * @param array $args Array of class args.
		 *
		 * @throws \Exception When post_type and taxonomy are not set.
		 *
		 */
		public function __construct( $slug, array $args = array() ) {
			// Construct.
			parent::__construct( $slug, $args );

			// Make sure we have what we need!
			if ( ! isset( $this->args['post_type'] ) ) {
				throw new \Exception( \__( 'post_type are required to be set.', 'wps-rewrite' ) );
			}

			$this->post_type = $this->args['post_type'];
			$this->prefix    = $this->args['prefix'] ?? '';

			// Make sure we check the slugs.
			\add_filter( 'wp_unique_post_slug_is_bad_attachment_slug', array( $this, 'wp_unique_post_slug_is_bad_attachment_slug' ), 10, 2 );
			\add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', array( $this, 'wp_unique_post_slug_is_bad_hierarchical_slug' ), 10, 4 );
			\add_filter( 'wp_unique_post_slug_is_bad_flat_slug', array( $this, 'wp_unique_post_slug_is_bad_flat_slug' ), 10, 3 );

			// Permalink preview.
			\add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 2 );
		}

		/** PUBLIC API */

		/**
		 * Prefix for the URL path.
		 *
		 * @param string $prefix Prefix.
		 */
		public function set_prefix( string $prefix ) {
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
		 * Adds all the rewrite rules.
		 */
		public function remove_all_rewrites() {
			$this->rewrites = [
				'date'  => false,
				'feed'  => false,
				'page'  => false,
				'embed' => false,
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

		/**
		 * Adds page rewrite rules.
		 */
		public function add_page_rewrites() {
			$this->rewrites['page'] = true;
		}

		/**
		 * Ensures that page rewrite rules are not added.
		 */
		public function remove_page_rewrites() {
			$this->rewrites['page'] = false;
		}

		/** PRIVATE */

		/**
		 * Filters the permalink for a post of a custom post type.
		 *
		 * @access private
		 *
		 * @param string $post_link The post's permalink.
		 * @param \WP_Post $post The post in question.
		 *
		 * @return string Post's permalink.
		 */
		public function post_type_link( string $post_link, \WP_Post $post ): string {
			if ( $this->post_type !== $post->post_type ) {
				return $post_link;
			}

			// Add the prefix after home URL base.
			return \home_url( $this->prefix . str_replace( \trailingslashit( \home_url() ), '', $post_link ) );
		}

		/**
		 * Gets the Custom Post Type rewrite rules.
		 *
		 * @access private
		 *
		 * @param string $path Path before the dynamic rules.
		 *
		 * @return array Rewrite rules.
		 */
		protected function get_cpt_rewrite_rules( string $path ): array {
			$rules = [];

			// {path}/ Archive URL.
			$rules[ $path . '/?$' ] = 'index.php?' . \build_query( array(
					'post_type' => $this->post_type,
				) );

			// {path} Paginated Archive URLs.
			if ( $this->rewrites['page'] ) {
				$rules[ $path . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'paged'     => '$matches[1]',
					) );
			}

			// {path}/* Feed URLs.
			if ( $this->rewrites['feed'] ) {
				// {path}/feed/rss/ Feed URLs.
				$rules[ $path . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'feed'      => '$matches[1]',
					) );

				// {path}/rss/ Feed URLs.
				$rules[ $path . '/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'feed'      => '$matches[1]',
					) );
			}

			// {path}/embed/ Embed URLs.
			if ( $this->rewrites['embed'] ) {
				$rules[ $path . '/embed/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'embed'     => 'true',
					) );
			}

			// {path}/* Date URLs.
			if ( $this->rewrites['date'] ) {
				// YEAR-MONTH-DAY Archives.
				// {path}/YYYY/MM/DD/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'year'      => '$matches[1]',
						'monthnum'  => '$matches[2]',
						'day'       => '$matches[3]',
					) );

				// {path}/YYYY/MM/DD/page/#/ Paginated Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'paged'     => '$matches[4]',
						) );
				}

				// {path}/YYYY/MM/DD/feed/ Feed Archive Urls.
				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/DD/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'feed'      => '$matches[4]',
						) );

					// {path}/YYYY/MM/DD/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'feed'      => '$matches[4]',
						) );
				}

				// {path}/YYYY/MM/DD/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'day'       => '$matches[3]',
							'embed'     => 'true',
						) );
				}

				// YEAR-MONTH Archives.
				// {path}/YYYY/MM/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'year'      => '$matches[1]',
						'monthnum'  => '$matches[2]',
					) );

				// {path}/YYYY/MM/page/#/ Paginated Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'paged'     => '$matches[3]',
						) );
				}

				// {path}/YYYY/MM/DD/feed/ Feed Archive Urls.
				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'feed'      => '$matches[3]',
						) );

					// {path}/YYYY/MM/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'feed'      => '$matches[3]',
						) );
				}

				// {path}/YYYY/MM/embed/ Embed Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'monthnum'  => '$matches[2]',
							'embed'     => 'true',
						) );
				}

				// YEAR Archives.
				// {path}/YYYY/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/?$' ] = 'index.php?' . \build_query( array(
						'post_type' => $this->post_type,
						'year'      => '$matches[1]',
					) );

				// {path}/YYYY/page/#/ Paginated Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'paged'     => '$matches[2]',
						) );
				}

				// {path}/YYYY/MM/feed/ Feed Archive Urls.
				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'feed'      => '$matches[2]',
						) );

					// {path}/YYYY/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'feed'      => '$matches[2]',
						) );
				}

				// {path}/YYYY/embed/ Embed Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/embed/?$' ] = 'index.php?' . \build_query( array(
							'post_type' => $this->post_type,
							'year'      => '$matches[1]',
							'embed'     => 'true',
						) );
				}
			}

			return $rules;
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @access private
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param string $post_type The post type.
		 * @param int|null $post_parent Post Parent ID.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type = null, $post_parent = null ): bool {
			if ( $this->prefix === $slug ) {
				return true;
			}

			return $needs_suffix;
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @access private
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param string $post_type The post type.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_flat_slug( $needs_suffix, $slug, $post_type ): bool {
			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type );
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @access private
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param string $post_type The post type.
		 * @param int|null $post_parent Post Parent ID.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_hierarchical_slug( $needs_suffix, $slug, $post_type, $post_parent ): bool {
			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type, $post_parent );
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @access private
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_attachment_slug( $needs_suffix, $slug ): bool {
			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, 'attachment' );
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
		public function rewrite_rules( $wp_rewrite ): array {
			// Get post type slug.
			$post_type_object = get_post_type_object( $this->post_type );
			$post_type_slug   = $post_type_object->rewrite['slug'] ?? $this->post_type;

			$rules = [];

			// Add the custom post type archive rules.
			if ( $post_type_object->has_archive ) {
				$post_type_archive_slug = isset( $post_type_object->has_archive ) && is_string( $post_type_object->has_archive ) && '' !== $post_type_object->has_archive ? $post_type_object->has_archive : $post_type_slug;

				$rules = $this->get_cpt_rewrite_rules( $this->prefix . $post_type_archive_slug );
			}

			// Singular URLs.
			if ( $post_type_object->public ) {
				// {path}/embed/ Embed URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $this->prefix . $post_type_slug . '([^/]+)?(.?.+?)/embed/?$' ] = 'index.php?' . \build_query( array(
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[1]',
							'name'           => '$matches[2]',
							'embed'          => 'true',
						) );
				}

				// {prefix}/{custom-post-type}/{postname} URLs.
				$rules[ $this->prefix . $post_type_slug . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . \build_query( array(
						'post_type'      => $this->post_type,
						$this->post_type => '$matches[2]',
						'page'           => '$matches[3]',
					) );
			}

			// Add rules to top; first match wins!
			$wp_rewrite->rules = $rules + $wp_rewrite->rules;

			return $wp_rewrite->rules;
		}

		protected function get_post_from_query( \WP_Query $query ) {
			return function_exists( 'wpcom_vip_get_page_by_path' ) ? \wpcom_vip_get_page_by_path( $query->query_vars['name'], OBJECT,
				$query->query_vars['post_type'] ) : \get_page_by_path( $query->query_vars['name'], OBJECT, $query->query_vars['post_type'] );
		}

		/**
		 * Adjusts WP_Query as necessary.
		 *
		 * @access private
		 *
		 * @param \WP_Query $query Current Query.
		 */
		public function pre_get_posts( \WP_Query $query ) {
			// Make sure we are only dealing with our rewrites.
			if ( $this->is_not_this_main_query( $query ) ) {
				return;
			}

			// Make sure we have what we need!
			if ( ! isset( $query->query_vars['name'] ) || ! isset( $query->query_vars['post_type'] ) || $query->query_vars['post_type'] !== $this->post_type ) {
				return;
			}

			// Get the Post Object.
			$post = $this->get_post_from_query( $query );

			// Set the value of the query.
			global $wp_the_query;

			if ( isset( $wp_the_query->query[ $this->post_type ] ) && '' !== $wp_the_query->query[ $this->post_type ] ) {
				$wp_the_query->query[ $this->post_type ] = ltrim( $wp_the_query->query[ $this->post_type ], '/' );
			}

			if ( '' !== $this->var ) {
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
		public function defaults(): array {
			$defaults              = parent::defaults();
			$defaults['post_type'] = '';

			return $defaults;
		}

		/**
		 * Whether the current query has $this's query var.
		 *
		 * @access private
		 * @global \WP_Query $wp_query Query object.
		 *
		 * @return bool
		 */
		protected function has_query_var(): bool {
			global $wp_query;

			// @formatter:off
			return ( parent::has_query_var() || ( isset( $wp_query->query_vars[ $this->post_type ] ) && '' !== $wp_query->query_vars[ $this->post_type ] ) || \is_post_type_archive( $this->post_type ) );
			// @formatter:on
		}
	}
}
