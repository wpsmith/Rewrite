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
		 * Post Type
		 *
		 * @var string
		 */
		protected $post_type;

		/**
		 * Post meta field.
		 *
		 * @var string
		 */
		protected $taxonomy;

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
			add_filter( 'wp_unique_post_slug_is_bad_attachment_slug', array(
				$this,
				'wp_unique_post_slug_is_bad_attachment_slug'
			), 10, 2 );
			add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', array(
				$this,
				'wp_unique_post_slug_is_bad_hierarchical_slug'
			), 10, 4 );
			add_filter( 'wp_unique_post_slug_is_bad_flat_slug', array(
				$this,
				'wp_unique_post_slug_is_bad_flat_slug'
			), 10, 3 );

			// Flush rewrite whenever a term is created, edited, or deleted within taxonomy.
			add_filter( "created_$this->taxonomy", 'flush_rewrite_rules' );
			add_filter( "edited_$this->taxonomy", 'flush_rewrite_rules' );
			add_filter( "deleted_$this->taxonomy", 'flush_rewrite_rules' );

			// Permalink preview.
			add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 5 );

			// Construct.
			parent::__construct( $this->var, $args );

		}

		/**
		 * Filters the permalink for a post of a custom post type.
		 *
		 * @param string  $post_link The post's permalink.
		 * @param \WP_Post $post      The post in question.
		 * @param bool    $leavename Whether to keep the post name.
		 * @param bool    $sample    Is it a sample permalink.
		 *
		 * @return string Post's permalink.
		 */
		public function post_type_link($post_link, $post, $leavename, $sample) {
			if ( $this->post_type !== $post->post_type ) {
				return $post_link;
			}

			$uri = str_replace( trailingslashit( home_url() ), '', $post_link );
			// get the primary term.
			$terms = get_the_terms( $post, $this->taxonomy );

			if ( !empty($terms ) ) {
				return home_url( trailingslashit( $terms[0]->slug ) . $uri );
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
		 * Adds rewrite rules.
		 */
		public function rewrite_rules( $wp_rewrite ) {

			$terms = $this->get_terms();

			$rules = [];

			foreach ( $terms as $term ) {
				$rules[ $term->slug . '/(.?.+?)/?$' ]                   = 'index.php?' . $this->taxonomy . '=' . $term->slug . '&landing_page=$matches[1]';
				$rules[ $term->slug . '/(.?.+?)/page/?([0-9]{1,})/?$' ] = 'index.php?' . $this->taxonomy . '=' . $term->slug . '&landing_page=$matches[1]&paged=$matches[2]';
			}

			// Add rules to top; first match wins!
			$wp_rewrite->rules = $rules + $wp_rewrite->rules;

			return $wp_rewrite->rules;
		}

		/**
		 * Adds endpoint.
		 */
		public function add_rewrite_endpoint() {

			$terms = get_terms( array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			) );

			foreach ( $terms as $term ) {
				add_rewrite_endpoint( $term->slug . $this->slug, $this->places );
			}

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
				! isset( $wp_query->query_vars[ $this->taxonomy ] ) ||
				$wp_query->query_vars['post_type'] !== $this->post_type
			) {
				return;
			}

			// Get the Post Object.
			$post = function_exists( 'wpcom_vip_get_page_by_path' ) ? wpcom_vip_get_page_by_path( $wp_query->query_vars['name'], OBJECT, $wp_query->query_vars['post_type'] ) : get_page_by_path( $wp_query->query_vars['name'], OBJECT, $wp_query->query_vars['post_type'] );

			// Set the value of the query.
			global $wp_the_query;

			if ( ! has_term( $wp_query->query_vars[ $this->taxonomy ], $this->taxonomy, $post ) ) {
				$wp_query->set_404();
				set_query_var( $this->taxonomy . '-' . $this->post_type, 404 );

				// Make sure everything bugs out!
				$self = $this;
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
				parent::has_query_var() && is_singular() ||
				is_singular() && isset( $wp_query->query_vars[ $this->taxonomy ] ) && isset( $wp_query->query_vars['post_type'] ) && $this->post_type === $wp_query->query_vars['post_type']
			);

		}

	}
}