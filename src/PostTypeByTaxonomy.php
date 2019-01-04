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

if ( ! class_exists( 'WPS\Plugins\Rewrite\PostTypeTaxonomy' ) ) {
	/**
	 * Class RewriteEndpoint
	 *
	 * @package WPS\Rewrite
	 */
	class PostTypeByTaxonomy extends RewriteEndpoint {

		/**
		 * Post Type.
		 *
		 * @var string
		 */
		protected $post_type;

		/**
		 * Post Type Slug.
		 *
		 * @var string
		 */
		protected $post_type_slug;

		/**
		 * Taxonomy.
		 *
		 * @var string
		 */
		protected $taxonomy;

		/**
		 * Prefix of the URL base.
		 *
		 * @var string
		 */
		protected $prefix = '';

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
		 * Rewrite_Endpoint constructor.
		 *
		 * @throws \Exception When post_type and taxonomy are not set.
		 *
		 * @param array|string $slug Endpoint slug.
		 * @param array $args Array of class args.
		 */
		public function __construct( $slug, $args = array() ) {

			$args = $this->get_args( $slug, $args );

			if ( ! isset( $args['post_type'] ) || ! isset( $args['taxonomy'] ) ) {
				throw new \Exception( __( 'post_type and taxonomy are required to be set.', 'wps-rewrite' ) );
			}
			$this->post_type = $args['post_type'];
			$this->taxonomy  = $args['taxonomy'];

			// Make sure we check the slugs.
			add_filter( 'wp_unique_post_slug_is_bad_attachment_slug', array( $this, 'wp_unique_post_slug_is_bad_attachment_slug' ), 10, 2 );
			add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', array( $this, 'wp_unique_post_slug_is_bad_hierarchical_slug' ), 10, 4 );
			add_filter( 'wp_unique_post_slug_is_bad_flat_slug', array( $this, 'wp_unique_post_slug_is_bad_flat_slug' ), 10, 3 );

			// Flush rewrite whenever a term is created, edited, or deleted within taxonomy.
			add_filter( "created_$this->taxonomy", 'flush_rewrite_rules' );
			add_filter( "edited_$this->taxonomy", 'flush_rewrite_rules' );
			add_filter( "deleted_$this->taxonomy", 'flush_rewrite_rules' );

			// Permalink preview.
			add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 2 );

			// Construct.
			parent::__construct( $this->var, $args );

		}

		/**
		 * Filters the permalink for a post of a custom post type.
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

			// Remove the home URL base.
			$uri = str_replace( trailingslashit( home_url() ), '', $post_link );

			// get the primary term.
			$term = $this->get_the_first_term( $post, $this->taxonomy );

			if ( ! is_wp_error( $term ) && ! empty( $term ) ) {
				// Return re-adding home URL base.
				return home_url( trailingslashit( $term->slug ) . $uri );
			}

			return $post_link;
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param string $post_type The post type.
		 * @param int|null $post_parent Post Parent ID.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type, $post_parent = null ) {
			if ( $this->post_type !== $post_type ) {
				return $needs_suffix;
			}

			// Now cycle through our terms.
			$terms = $this->get_terms();
			foreach ( $terms as $term ) {
				if ( $term->slug === $slug ) {
					return true;
				}
			}

			return $needs_suffix;
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param string $post_type The post type.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_flat_slug( $needs_suffix, $slug, $post_type ) {
			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type );
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 * @param string $post_type The post type.
		 * @param int|null $post_parent Post Parent ID.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_hierarchical_slug( $needs_suffix, $slug, $post_type, $post_parent ) {
			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, $post_type, $post_parent );
		}

		/**
		 * Determines whether the post slug is unique.
		 *
		 * @param bool $needs_suffix Whether the slug needs a suffix added.
		 * @param string $slug The slug being checked.
		 *
		 * @return bool
		 */
		public function wp_unique_post_slug_is_bad_attachment_slug( $needs_suffix, $slug ) {
			return $this->wp_unique_post_slug_is_bad_slug( $needs_suffix, $slug, 'attachment' );
		}

		/**
		 * Gets the first term attached to the post.
		 *
		 * Heavily borrowed from Bill Erickson.
		 *
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
		 * @return array|int|\WP_Error
		 */
		protected function get_terms() {

			return get_terms( array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			) );

		}

		/**
		 * Prefix for the URL path.
		 *
		 * @param string $prefix Prefix.
		 */
		public function set_prefix( $prefix ) {

			$this->prefix = '' !== $prefix ? trailingslashit( $prefix ) : '';

		}

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

			if ( 2 === count( $the_order ) ) {
				$this->order = $the_order;
			}

			return $this->order;

		}

		/**
		 * Adds rewrite rules.
		 *
		 * @param \WP_Rewrite $this Current WP_Rewrite instance (passed by reference).
		 *
		 * @return array
		 */
		public function rewrite_rules( $wp_rewrite ) {

			// Get post type slug.
			$post_type_object = get_post_type_object( $this->post_type );
			$post_type_slug   = isset( $post_type_object->rewrite['slug'] ) ? $post_type_object->rewrite['slug'] : $this->post_type;

			$rules = [];
			foreach ( $this->get_terms() as $term ) {

				$path = str_replace( '%post_type%', $post_type_slug, str_replace( '%term%', $term->slug, $this->prefix . implode( '/', $this->order ) ) );

				if ( $post_type_object->has_archive ) {

					// {prefix}/{taxonomy}/{custom-post-type} Archive URL.
					$rules[ $path . '/?$' ] = 'index.php?' . build_query( array(
							$this->taxonomy => $term->slug,
							'post_type'     => $this->post_type,
						) );

					// Pagination. {prefix}/{taxonomy}/{custom-post-type} Archive URLs.
					$rules[ $path . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . build_query( array(
							$this->taxonomy => $term->slug,
							'post_type'     => $this->post_type,
							'paged'         => '$matches[1]',
						) );

				}

				if ( $post_type_object->public ) {

					// {prefix}/{taxonomy}/{custom-post-type}/{postname} URLs.
//					$rules[ $this->prefix . $term->slug . '/' . $post_type_slug . '/(.?.+?)/?$' ] = 'index.php?' . build_query( array(
//							$this->taxonomy  => $term->slug,
//							'post_type'      => $this->post_type,
//							$this->post_type => '$matches[1]',
//						) );

					// {prefix}/{taxonomy}/{custom-post-type}/{postname} URLs.
					$rules[ $path . '/([^/]+)(?:/([0-9]+))?/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'   => $this->taxonomy,
							'wps-term'       => $term->slug,
//							$this->taxonomy  => $term->slug, // throws an error
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[1]',
							'page'           => '$matches[2]',
						) );

					// {prefix}/{taxonomy}/{postname} URLs.
//					$rules[ $this->prefix . $term->slug . '/(.?.+?)/?$' ] = 'index.php?' . build_query( array(
//							$this->taxonomy  => $term->slug,
//							'post_type'      => $this->post_type,
//							$this->post_type => '$matches[1]',
//						) );

					// {prefix}/{taxonomy}/{postname} URLs.
					$rules[ $this->prefix . $term->slug . '/([^/]+)(?:/([0-9]+))?/?$' ] = 'index.php?' . build_query( array(
							'wps-taxonomy'   => $this->taxonomy,
							'wps-term'       => $term->slug,
//							$this->taxonomy  => $term->slug, // throws an error
							'post_type'      => $this->post_type,
							$this->post_type => '$matches[1]',
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
		 * @param \WP_Query $query Current Query.
		 */
		public function pre_get_posts( $query ) {

			if ( ! $this->has_query_var() || ! $query->is_main_query() ) {
				return;
			}

			// Make sure we have what we need!
			if (
				! isset( $query->query_vars['name'] ) ||
				! isset( $query->query_vars['post_type'] ) ||
				//				! isset( $query->query_vars[ $this->taxonomy ] ) ||
				$query->query_vars['post_type'] !== $this->post_type
			) {
				return;
			}

			// Get the Post Object.
			$post = function_exists( 'wpcom_vip_get_page_by_path' ) ? wpcom_vip_get_page_by_path( $query->query_vars['name'], OBJECT, $query->query_vars['post_type'] ) : get_page_by_path( $query->query_vars['name'], OBJECT, $query->query_vars['post_type'] );

			// Set the value of the query.
			global $wp_the_query;

			// For single posts with a taxonomy slug prefix.
			if (
				isset( $query->query_vars[ $this->taxonomy ] ) &&
				is_singular( $this->post_type ) &&
				! has_term( $query->query_vars[ $this->taxonomy ], $this->taxonomy, $post )
			) {
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
		 * @return array Array of defaults.
		 */
		public function defaults() {

			$defaults              = parent::defaults();
			$defaults['post_type'] = '';
			$defaults['taxonomy']  = '';

			return $defaults;

		}

		/**
		 * Whether the current query has this's query var.
		 *
		 * @return bool
		 */
		protected function has_query_var() {
			global $wp_query;

			return (
				parent::has_query_var() ||
				// parent::has_query_var() && is_singular() ||
				(
					is_singular() &&
					isset( $wp_query->query_vars[ $this->taxonomy ] ) &&
					isset( $wp_query->query_vars['post_type'] ) &&
					$this->post_type === $wp_query->query_vars['post_type']
				) ||
				(
					isset( $wp_query->query_vars[ $this->post_type ] ) &&
					'' !== $wp_query->query_vars[ $this->post_type ]
				) ||
				is_post_type_archive( $this->post_type )
			);

		}

	}
}