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

if ( ! class_exists( __NAMESPACE__ . '\PostTypeByTaxonomy' ) ) {
	/**
	 * Class PostTypeByTaxonomy
	 *
	 * @package WPS\Rewrite
	 */
	class PostTypeByTaxonomy extends PostTypeRewrite {

		/**
		 * Taxonomy.
		 *
		 * @var string
		 */
		protected $taxonomy;

		/**
		 * The order of the slugs.
		 *
		 * @var []string
		 */
		protected $order = array(
			'%term%',
			'%post_type%',
		);

		/**
		 * RewriteEndpoint constructor.
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

			// Make sure we have what we need!
			if ( ! isset( $this->args['taxonomy'] ) ) {
				throw new \Exception( __( 'taxonomy is required to be set.', 'wps-rewrite' ) );
			}

			$this->taxonomy = $this->args['taxonomy'];

			// @todo Determine whether this is really needed. Could be needed because this taxonomy term could be part of another rewrite deal.
			add_filter( 'wp_unique_term_slug_is_bad_slug', array( $this, 'wp_unique_term_slug_is_bad_slug' ), 10, 3 );

			// Flush rewrite whenever a term is created, edited, or deleted within taxonomy.
			add_filter( "created_$this->taxonomy", 'flush_rewrite_rules' );
			add_filter( "edited_$this->taxonomy", 'flush_rewrite_rules' );
			add_filter( "deleted_$this->taxonomy", 'flush_rewrite_rules' );

		}

		/** PUBLIC API */

		/**
		 * Order for the URL Path.
		 *
		 * @param []string $order Order of slugs.
		 *
		 * @return []string The order.
		 */
		public function set_order( $order ) {

			$the_order = [];
			$defaults  = [
				'%term%',
				'%post_type%',
			];

			foreach ( $order as $o ) {
				if ( in_array( $o, $defaults, true ) ) {
					$the_order[] = $o;
				}
			}

			$c = count( $the_order );
			if ( 2 === $c || 1 === $c ) {
				$this->order = $the_order;
			}

			return $this->order;

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
		public function post_type_link( $post_link, $post ) {
			if ( $this->post_type !== $post->post_type ) {
				return $post_link;
			}

			$post_type_object = get_post_type_object( $this->post_type );

			// Remove the home URL base.
			$old_path = str_replace( trailingslashit( home_url() ), '/', $post_link );

			// Remove the post type object rewrite
			$old_path = str_replace( '/' . $post_type_object->rewrite['slug'] . '/', '/', $old_path );

			// Start building the new path.
			$path = $this->prefix . implode( '/', $this->order );

			// get the primary term.
			if ( in_array( '%term%', $this->order ) ) {
				$term = $this->get_the_first_term( $post, $this->taxonomy );
				$path = str_replace( '%term%', $term->slug, $path );
			}

			if ( in_array( '%post_type%', $this->order ) ) {
				$path = str_replace( '%post_type%', $post_type_object->rewrite['slug'], $path );
			}

			if ( ! is_wp_error( $term ) && ! empty( $term ) ) {

				$path .= $old_path;

				// Return re-adding home URL base.
				return home_url( $path );
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
		public function wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type = null, $post_parent = null ) {

			// Cycle through our terms and make sure this slug doesn't match any.
			$terms = $this->get_terms();
			foreach ( $terms as $term ) {
				if ( $term->slug === $slug ) {
					return true;
				}
			}

			return parent::wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type, $post_parent );

		}

		/**
		 * Determines whether the term slug is unique.
		 *
		 * @access private
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param \WP_Term $term Term Object.
		 *
		 * @return bool
		 */
		public function wp_unique_term_slug_is_bad_slug( $needs_suffix, $slug, $term ) {

			if ( $term->taxonomy === $this->taxonomy ) {
				// Cycle through our posts and make sure this slug doesn't match any.
				$posts = get_posts( array( 'post_type' => $this->post_type ) );
				foreach ( $posts as $post ) {
					if ( $post->post_name === $slug ) {
						return true;
					}
				}
			}

			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug );

		}

		/**
		 * Gets the first term attached to the post.
		 *
		 * Heavily borrowed from Bill Erickson.
		 *
		 * @access private
		 * @link https://github.com/billerickson/EA-Genesis-Child/
		 *
		 * @param \WP_Post|int $post_or_id The Post or the Post ID.
		 * @param string $taxonomy The taxonomy.
		 *
		 * @return array|bool|null|\WP_Error|\WP_Term
		 */
		protected function get_the_first_term( $post_or_id, $taxonomy = 'category' ) {

			if ( ! $post = get_post( $post_or_id ) ) {
				return false;
			}

			$term = false;

			// Use WP SEO Primary Term
			// from https://github.com/Yoast/wordpress-seo/issues/4038
			if ( class_exists( 'WPSEO_Primary_Term' ) ) {
				$term = get_term( ( new \WPSEO_Primary_Term( $taxonomy, $post->ID ) )->get_primary_term(), $taxonomy );
			}

			// Fallback on term with highest post count
			if ( ! $term || is_wp_error( $term ) ) {
				$terms = get_the_terms( $post->ID, $taxonomy );
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					return false;
				}

				// If there's only one term, use that
				if ( 1 == count( $terms ) ) {
					$term = array_shift( $terms );

					// If there's more than one...
				} else {

					// Sort by term order if available
					// @uses WP Term Order plugin
					if ( isset( $terms[0]->order ) ) {
						$list = array();
						foreach ( $terms as $term ) {
							$list[ $term->order ] = $term;
						}

						ksort( $list, SORT_NUMERIC );

						// Or sort by post count
					} else {
						$list = array();
						foreach ( $terms as $term ) {
							$list[ $term->count ] = $term;
						}

						ksort( $list, SORT_NUMERIC );
						$list = array_reverse( $list );
					}
					$term = array_shift( $list );
				}
			}

			return $term;

		}

		/**
		 * Gets the terms for this.
		 *
		 * @access private
		 * @return array|int|\WP_Error
		 */
		protected function get_terms() {

			return get_terms( array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			) );

		}

		/**
		 * Gets the Custom Post Type / Term rewrite rules.
		 *
		 * @access private
		 *
		 * @param string $path Path before the dynamic rules.
		 * @param \WP_Term $term The term object.
		 *
		 * @return array Rewrite rules.
		 */
		protected function get_term_rewrite_rules( $path, $term ) {
			$rules = [];

			// {path}/ Archive URL.
			$rules[ $path . '/?$' ] = 'index.php?' . build_query( array(
					'wps-post_type' => $this->post_type,
					'wps-taxonomy'  => $this->taxonomy,
					'wps-term'      => $term->slug,
					$this->taxonomy => $term->slug,
					'post_type'     => $this->post_type,
				) );

			// {path}/page/#/ Pagination Archive URLs.
			if ( $this->rewrites['page'] ) {
				$rules[ $path . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
						'wps-post_type' => $this->post_type,
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						$this->taxonomy => $term->slug,
						'post_type'     => $this->post_type,
						'paged'         => '$matches[1]',
					) );
			}

			if ( $this->rewrites['feed'] ) {
				// {path}/feed/rss/ Feed URLs.
				$rules[ $path . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
						'wps-post_type' => $this->post_type,
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						$this->taxonomy => $term->slug,
						'post_type'     => $this->post_type,
						'feed'          => '$matches[1]',
					) );

				// {path}/rss/ Feed URLs.
				$rules[ $path . '/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
						'wps-post_type' => $this->post_type,
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						$this->taxonomy => $term->slug,
						'post_type'     => $this->post_type,
						'feed'          => '$matches[1]',
					) );
			}

			// {path}/embed/ Embed URL.
			if ( $this->rewrites['embed'] ) {
				$rules[ $path . '/embed/?$' ] = 'index.php?' . build_query( array(
						$this->taxonomy => $term->slug,
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						'wps-post_type' => $this->post_type,
						'post_type'     => $this->post_type,
						'embed'         => 'true',
					) );
			}

			// Year, Month, Day Archives.
			if ( $this->rewrites['date'] ) {
				// {path}/YYYY/MM/DD/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' ] = 'index.php?' . build_query( array(
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						'wps-post_type' => $this->post_type,
						'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
						'year'          => '$matches[1]',
						'monthnum'      => '$matches[2]',
						'day'           => '$matches[3]',
					) );

				// {path}/YYYY/MM/DD/page/#/ Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'day'           => '$matches[3]',
							'paged'         => '$matches[4]',
						) );
				}

				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/DD/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'day'           => '$matches[3]',
							'feed'          => '$matches[4]',
						) );

					// {path}/YYYY/MM/DD/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'day'           => '$matches[3]',
							'feed'          => '$matches[4]',
						) );
				}

				// {path}/YYYY/MM/DD/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'day'           => '$matches[3]',
							'embed'         => 'true',
						) );
				}

				// Year, Month Archives.
				// {path}/YYYY/MM/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/?$' ] = 'index.php?' . build_query( array(
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						'wps-post_type' => $this->post_type,
						'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
						'year'          => '$matches[1]',
						'monthnum'      => '$matches[2]',
					) );

				// {path}/YYYY/MM/page/#/ Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'paged'         => '$matches[3]',
						) );
				}

				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/MM/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'feed'          => '$matches[3]',
						) );

					// {path}/YYYY/MM/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'feed'          => '$matches[3]',
						) );
				}

				// {path}/YYYY/MM/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/embed/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'monthnum'      => '$matches[2]',
							'embed'         => 'true',
						) );
				}

				// Year Archives.
				// {path}/YYYY/ Archive URLs.
				$rules[ $path . '/([0-9]{4})/?$' ] = 'index.php?' . build_query( array(
						'wps-taxonomy'  => $this->taxonomy,
						'wps-term'      => $term->slug,
						'wps-post_type' => $this->post_type,
						'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
						'year'          => '$matches[1]',
					) );

				// {path}/YYYY/page/#/ Archive URLs.
				if ( $this->rewrites['page'] ) {
					$rules[ $path . '/([0-9]{4})/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'paged'         => '$matches[2]',
						) );
				}

				if ( $this->rewrites['feed'] ) {
					// {path}/YYYY/feed/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'feed'          => '$matches[2]',
						) );

					// {path}/YYYY/rss/ Archive URLs.
					$rules[ $path . '/([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'feed'          => '$matches[2]',
						) );
				}

				// {path}/YYYY/embed/ Archive URLs.
				if ( $this->rewrites['embed'] ) {
					$rules[ $path . '/([0-9]{4})/embed/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'  => $this->taxonomy,
							'wps-term'      => $term->slug,
							'wps-post_type' => $this->post_type,
							'post_type'     => $this->post_type,
//							$this->taxonomy => $term->slug, // throws an error
							'year'          => '$matches[1]',
							'embed'         => 'true',
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

			// Cycle through our terms.
			foreach ( $this->get_terms() as $term ) {

				// Create path based on order.
				// {prefix}/{term}/{custom-post-type} or {prefix}/{custom-post-type}/{term}
				$path = str_replace( '%post_type%', $post_type_slug,
					str_replace( '%term%', $term->slug, $this->prefix . implode( '/', $this->order ) ) );

				// Archive URLs.
				if ( $post_type_object->has_archive ) {

					$rules = array_merge( $rules, $this->get_term_rewrite_rules( $path, $term ) );
					$rules = array_merge( $rules, $this->get_term_rewrite_rules( $this->prefix . $term->slug, $term ) );

				}

				// Singular URLs.
				if ( $post_type_object->public ) {

					// {path}/embed/ Embed URLs.
					if ( $this->rewrites['embed'] ) {
						$rules[ $path . '([^/]+)?(.?.+?)/embed/?$' ] = 'index.php?' . build_query( array(
								'wps-taxonomy'   => $this->taxonomy,
								'wps-term'       => $term->slug,
//							$this->taxonomy  => $term->slug, // throws an error
								'post_type'      => $this->post_type,
								$this->post_type => '$matches[2]',
								'page'           => '$matches[2]',
								'embed'          => 'true',
							) );
					}

					// {prefix}/{term}/{custom-post-type}/{postname} URLs.
					$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'   => $this->taxonomy,
							'wps-term'       => $term->slug,
//							$this->taxonomy  => $term->slug, // throws an error
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[2]',
							'paged'          => '$matches[4]',
						) );

					// {path}/embed/ Embed URLs.
					if ( $this->rewrites['embed'] ) {
						$rules[ $this->prefix . $term->slug . '([^/]+)?(.?.+?)/embed/?$' ] = 'index.php?' . build_query( array(
								'wps-taxonomy'   => $this->taxonomy,
								'wps-term'       => $term->slug,
//							$this->taxonomy  => $term->slug, // throws an error
								'post_type'      => $this->post_type,
								$this->post_type => '$matches[2]',
								'embed'          => 'true',
							) );
					}

					// {prefix}/{term}/{postname} URLs.
					$rules[ $this->prefix . $term->slug . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'   => $this->taxonomy,
							'wps-term'       => $term->slug,
//							$this->taxonomy  => $term->slug, // throws an error
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[2]',
							'paged'          => '$matches[4]',
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
		 */
		public function pre_get_posts( $query ) {

			// Make sure we are only dealing with our rewrites.
			if ( ! $this->has_query_var() || ! $query->is_main_query() ) {
				return;
			}

			// Make sure we have what we need!
			if ( ! isset( $query->query_vars['name'] ) || ! isset( $query->query_vars['post_type'] ) || //				! isset( $query->query_vars[ $this->taxonomy ] ) ||
			     $query->query_vars['post_type'] !== $this->post_type ) {
				return;
			}

			// Get the Post Object.
			$post = function_exists( 'wpcom_vip_get_page_by_path' ) ? wpcom_vip_get_page_by_path( $query->query_vars['name'], OBJECT,
				$query->query_vars['post_type'] ) : get_page_by_path( $query->query_vars['name'], OBJECT, $query->query_vars['post_type'] );

			// Set the value of the query.
			global $wp_the_query;

			// For single posts with a taxonomy slug prefix.
			if ( isset( $query->query_vars[ $this->taxonomy ] ) && is_singular( $this->post_type ) && ! has_term( $query->query_vars[ $this->taxonomy ],
					$this->taxonomy, $post ) ) {
				$wp_the_query->set_404();
				$query->set_404();
				set_query_var( $this->taxonomy . '-' . $this->post_type, 404 );

				// For anonymous functions.
				$self = $this;

				// Make sure everything bugs out!
				add_filter( 'redirect_canonical', function ( $redirect_url ) use ( $self ) {
					if ( 404 === get_query_var( $self->taxonomy . '-' . $self->post_type ) ) {
						return null;
					}

					return $redirect_url;
				} );
				add_filter( 'old_slug_redirect_url', function ( $link ) use ( $self ) {
					if ( 404 === get_query_var( $self->taxonomy . '-' . $self->post_type ) ) {
						return null;
					}

					return $link;
				} );
			}

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

			$defaults             = parent::defaults();
			$defaults['taxonomy'] = '';

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

			return ( parent::has_query_var() || // parent::has_query_var() && is_singular() ||
			         ( is_singular() && isset( $wp_query->query_vars[ $this->taxonomy ] ) && isset( $wp_query->query_vars['post_type'] ) && $this->post_type === $wp_query->query_vars['post_type'] ) || ( isset( $wp_query->query_vars[ $this->post_type ] ) && '' !== $wp_query->query_vars[ $this->post_type ] ) || is_post_type_archive( $this->post_type ) );

		}

	}
}
