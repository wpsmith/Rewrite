<?php
/**
 * Post Type by Post Meta Key Rewrite Endpoint Class
 *
 * Creates additional rewrite URLs based on post type and post meta key.
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

if ( ! class_exists( __NAMESPACE__ . '\PostTypeByPostMetaKey' ) ) {
	/**
	 * Class PostTypeByPostMeta
	 *
	 * @package WPS\WP\Rewrite
	 */
	class PostTypeByPostMetaKey extends PostTypeBy {

		/**
		 * Post meta key.
		 *
		 * @var string
		 */
		public string $meta_key;

		/**
		 * The order of the slugs.
		 *
		 * @var string[]
		 */
		protected array $allowed_order_tokens = [
			'%meta_key%',
			'%post_type%',
		];

		/**
		 * The order of the slugs.
		 *
		 * @var string[]
		 */
		protected array $order = [
			'%meta_key%',
			'%post_type%',
		];

		/**
		 * PostTypeByPostMeta constructor.
		 *
		 * @param array|string $slug Endpoint slug.
		 * @param array $args Array of class args.
		 *
		 * @throws \Exception When post_type and taxonomy are not set.
		 *
		 */
		public function __construct( $slug, $args = array() ) {
			// Construct.
			parent::__construct( $slug, $args );

			$args = $this->get_args( $slug, $args );

			if ( ! isset( $args['post_type'] ) || ! isset( $args['taxonomy'] ) ) {
				throw new \Exception( \__( 'post_type and taxonomy are required to be set.', 'wps-rewrite' ) );
			}

			$this->meta_key = $args['meta_key'];
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

			$post_type_object = \get_post_type_object( $this->post_type );

			// Remove the home URL base.
			$old_path = str_replace( trailingslashit( \home_url() ), '/', $post_link );

			// Remove the post type object rewrite
			$old_path = str_replace( '/' . $post_type_object->rewrite['slug'] . '/', '/', $old_path );

			// Start building the new path.
			$path = $this->prefix . implode( '/', $this->order );

			// get the primary term.
			$meta_value = '';
			if ( in_array( '%meta_key%', $this->order ) ) {
				$meta_value = \get_post_meta( $post->ID, $this->meta_key, true );
				$path       = str_replace( '%meta_key%', $meta_value, $path );
			}

			if ( in_array( '%post_type%', $this->order ) ) {
				$path = str_replace( '%post_type%', $post_type_object->rewrite['slug'], $path );
			}

			if ( ! \is_wp_error( $meta_value ) && ! empty( $meta_value ) ) {
				$path .= $old_path;

				// Return re-adding home URL base.
				return \home_url( $path );
			}

			return $post_link;
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
			// Cycle through our terms and make sure this slug doesn't match any.
			$meta_values = $this->get_meta_values();
			foreach ( $meta_values as $meta_value ) {
				if ( $meta_value === $slug ) {
					return true;
				}
			}

			return parent::wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type, $post_parent );
		}

		/**
		 * Get all the values for a specific post meta key.
		 *
		 * @return array
		 * @global \wpdb $wpdb WordPress database object.
		 */
		protected function get_meta_values(): array {
			global $wpdb;

			$sql = "
		        SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
		        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		        WHERE pm.meta_key = '%s'
		        AND p.post_type = '%s'
		    ";

			return $wpdb->get_col( $wpdb->prepare( $sql, $this->meta_key, $this->post_type ) );
		}

		/**
		 * Gets the Custom Post Type / Term rewrite rules.
		 *
		 * @access private
		 *
		 * @param string $path Path before the dynamic rules.
		 * @param mixed $meta_value The meta key value.
		 *
		 * @return array Rewrite rules.
		 */
		protected function get_meta_rewrite_rules( string $path, $meta_value ): array {
			$rules = [];

			// {path}/ Archive URL.
			$rules[ $path . '/?$' ] = 'index.php?' . \build_query( array(
					'meta_key'   => $this->meta_key,
					'meta_value' => $meta_value,
				) );

			// {path}/page/#/ Pagination Archive URLs.
			if ( $this->rewrites['page'] ) {
				$rules[ $path . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'paged'      => '$matches[1]',
					) );
			}

			// {path}/page/#/ Feed Archive URLs.
			if ( $this->rewrites['feed'] ) {
				// {path}/feed/rss/ Feed URLs.
				$rules[ $path . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'feed'       => '$matches[1]',
					) );

				// {path}/rss/ Feed URLs.
				$rules[ $path . '/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'feed'       => '$matches[1]',
					) );
			}

			// {path}/embed/ Embed Archive URL.
			if ( $this->rewrites['embed'] ) {
				$rules[ $path . '/embed/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'embed'      => 'true',
					) );
			}

			// Year, Month, Day Archives.
			if ( $this->rewrites['date'] ) {
				// {path}/YYYY/MM/DD/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'year'       => '$matches[1]',
						'monthnum'   => '$matches[2]',
						'day'        => '$matches[3]',
					) );

				// {path}/YYYY/MM/DD/page/#/ Pagination Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'day'        => '$matches[3]',
							'paged'      => '$matches[4]',
						) );
				}

				// {path}/YYYY/MM/DD/page/#/ Feed Archive URLs.
				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/DD/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'day'        => '$matches[3]',
							'feed'       => '$matches[4]',
						) );

					// {path}/YYYY/MM/DD/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'day'        => '$matches[3]',
							'feed'       => '$matches[4]',
						) );
				}

				// {path}/YYYY/MM/DD/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'day'        => '$matches[3]',
							'embed'      => 'true',
						) );
				}

				// Year, Month Archives.
				// {path}/YYYY/MM/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'year'       => '$matches[1]',
						'monthnum'   => '$matches[2]',
					) );

				// {path}/YYYY/MM/page/#/ Pagination Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'paged'      => '$matches[3]',
						) );
				}

				// {path}/YYYY/MM/page/#/ Feed Archive URLs.
				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'feed'       => '$matches[3]',
						) );

					// {path}/YYYY/MM/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'feed'       => '$matches[3]',
						) );
				}

				// {path}/YYYY/MM/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'monthnum'   => '$matches[2]',
							'embed'      => 'true',
						) );
				}

				// Year Archives.
				// {path}/YYYY/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/?$' ] = 'index.php?' . \build_query( array(
						'meta_key'   => $this->meta_key,
						'meta_value' => $meta_value,
						'post_type'  => $this->post_type,
						'year'       => '$matches[1]',
					) );

				// {path}/YYYY/page/#/ Pagination Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'paged'      => '$matches[2]',
						) );
				}

				// {path}/YYYY/page/#/ Feed Archive URLs.
				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'feed'       => '$matches[2]',
						) );

					// {path}/YYYY/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'feed'       => '$matches[2]',
						) );
				}

				// {path}/YYYY/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/embed/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'   => $this->meta_key,
							'meta_value' => $meta_value,
							'post_type'  => $this->post_type,
							'year'       => '$matches[1]',
							'embed'      => 'true',
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
		public function rewrite_rules( $wp_rewrite ): array {
			// Get post type slug.
			$post_type_object = \get_post_type_object( $this->post_type );
			$post_type_slug   = $post_type_object->rewrite['slug'] ?? $this->post_type;

			$rules = [];

			// Add the custom post type archive rules.
			if ( $post_type_object->has_archive ) {
				$post_type_archive_slug = isset( $post_type_object->has_archive ) && is_string( $post_type_object->has_archive ) && '' !== $post_type_object->has_archive ? $post_type_object->has_archive : $post_type_slug;

				$rules = $this->get_cpt_rewrite_rules( $this->prefix . $post_type_archive_slug );
			}

			// Cycle through our terms.
			foreach ( $this->get_meta_values() as $meta_value ) {
				// Create path based on order.
				// {prefix}/{term}/{custom-post-type} or {prefix}/{custom-post-type}/{term}
				$path = str_replace( '%post_type%', $post_type_slug,
					str_replace( '%meta_key%', $meta_value, $this->prefix . implode( '/', $this->order ) ) );

				// Archive URLs.
				if ( $post_type_object->has_archive ) {
					$rules = array_merge( $rules, $this->get_meta_rewrite_rules( $path, $meta_value ) );
					$rules = array_merge( $rules, $this->get_meta_rewrite_rules( $this->prefix . $meta_value, $meta_value ) );
				}

				// Singular URLs.
				if ( $post_type_object->public ) {
					// {path}/embed/ Embed URLs.
					if ( $this->rewrites['embed'] ) {
						$rules[ $path . '([^/]+)?(.?.+?)/embed/?$' ] = 'index.php?' . \build_query( array(
								'meta_key'       => $this->meta_key,
								'meta_value'     => $meta_value,
								'post_type'      => $this->post_type,
								$this->post_type => '$matches[2]',
								'page'           => '$matches[2]',
								'embed'          => 'true',
							) );
					}

					// {prefix}/{term}/{custom-post-type}/{postname} URLs.
					$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'       => $this->meta_key,
							'meta_value'     => $meta_value,
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[2]',
							'page'           => '$matches[2]',
						) );

					// {path}/embed/ Embed URLs.
					if ( $this->rewrites['embed'] ) {
						$rules[ $this->prefix . $meta_value . '([^/]+)?(.?.+?)/embed/?$' ] = 'index.php?' . \build_query( array(
								'meta_key'       => $this->meta_key,
								'meta_value'     => $meta_value,
								'post_type'      => $this->post_type,
								$this->post_type => '$matches[2]',
								'page'           => '$matches[2]',
								'embed'          => 'true',
							) );
					}

					// {prefix}/{term}/{postname} URLs.
					$rules[ $this->prefix . $meta_value . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . \build_query( array(
							'meta_key'       => $this->meta_key,
							'meta_value'     => $meta_value,
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[2]',
							'page'           => '$matches[2]',
						) );
				}
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
		 *
		 * @global \WP_Query The Query object.
		 *
		 */
		public function pre_get_posts( \WP_Query $query ) {
			if ( ! $this->has_query_var() || ! $query->is_main_query() ) {
				return;
			}

			global $wp_query;

			// Make sure we have what we need!
			if ( ! isset( $wp_query->query_vars['name'] ) || ! isset( $wp_query->query_vars['post_type'] ) || $wp_query->query_vars['post_type'] === $this->post_type ) {
				return;
			}

			// Get the Post Object.
			$post = function_exists( 'wpcom_vip_get_page_by_path' ) ? \wpcom_vip_get_page_by_path( $wp_query->query_vars['name'], OBJECT,
				$wp_query->query_vars['post_type'] ) : \get_page_by_path( $wp_query->query_vars['name'], OBJECT, $wp_query->query_vars['post_type'] );

			// Set the value of the query.
			global $wp_the_query;

			$ids = \get_post_meta( $post->ID, $this->meta_key, true );

			$wp_the_query->query[ $this->var ] = $ids;
			$wp_the_query->set( $this->var, $ids );
		}

		/**
		 * Default args.
		 *
		 * @access private
		 * @return array Array of defaults.
		 */
		public function defaults(): array {
			$defaults             = parent::defaults();
			$defaults['meta_key'] = '';

			return $defaults;
		}

		/**
		 * Whether the current query has $this's query var.
		 *
		 * @return bool
		 * @global \WP_Query The Query object.
		 */
		protected function has_query_var(): bool {
			global $wp_query;

			// @formatter:off
			return (
				parent::has_query_var() ||
				(
					is_singular() &&
					isset( $wp_query->query_vars[ $this->meta_key ] ) &&
					isset( $wp_query->query_vars['wps-meta-key'] ) &&
					$this->post_type === $wp_query->query_vars['post_type']
				) ||
				(
					isset( $wp_query->query_vars[ $this->post_type ] ) &&
					'' !== $wp_query->query_vars[ $this->post_type ]
				) ||
				is_post_type_archive( $this->post_type )
			);
			// @formatter:on
		}
	}
}