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
	 * @package WPS\WP\Rewrite
	 */
	class PostTypeByTaxonomy extends PostTypeBy {
		/**
		 * Taxonomy.
		 *
		 * @var string
		 */
		protected string $taxonomy;

		/**
		 * Taxonomy object.
		 *
		 * @var \WP_Taxonomy
		 */
		protected \WP_Taxonomy $taxonomy_object;

		/**
		 * The default order of the slugs.
		 *
		 * @var string[]
		 */
		protected array $allowed_order_tokens = [
			'%term%',
			'%taxonomy%',
			'%post_type%',
		];

		/**
		 * The order of the slugs.
		 *
		 * @var string[]
		 */
		protected array $order = [
			'%term%',
			'%post_type%',
		];

		/**
		 * Whether to add singular rewrites.
		 *
		 * @var bool
		 */
		protected bool $add_singular_rewrites = true;

		/**
		 * Whether to add archive rewrites.
		 *
		 * @var bool
		 */
		protected bool $add_archive_rewrites = true;

		/**
		 * RewriteEndpoint constructor.
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
			if ( ! isset( $this->args['taxonomy'] ) ) {
				throw new \Exception( \__( 'taxonomy is required to be set.', 'wps-rewrite' ) );
			}

			$this->taxonomy = $this->args['taxonomy'];

			// @todo Determine whether this is really needed. Could be needed because this taxonomy term could be part of another rewrite deal.
			\add_filter( 'wp_unique_term_slug_is_bad_slug', array( $this, 'wp_unique_term_slug_is_bad_slug' ), 10, 3 );

			// Flush rewrite whenever a term is created, edited, or deleted within taxonomy.
			\add_filter( "created_$this->taxonomy", 'flush_rewrite_rules' );
			\add_filter( "edited_$this->taxonomy", 'flush_rewrite_rules' );
			\add_filter( "deleted_$this->taxonomy", 'flush_rewrite_rules' );

			// Fix term links.
			\add_filter( 'term_link', [ $this, 'term_link' ], 10, 3 );

			// Fix tax query
			\add_action( 'parse_tax_query', [ $this, 'parse_tax_query' ] );

			if ( did_action( 'registered_taxonomy' ) && false !== get_taxonomy( $this->taxonomy ) ) {
				$this->taxonomy_object = get_taxonomy( $this->taxonomy );
			} else {
				\add_action( 'registered_taxonomy', function ( $taxonomy ) {
					if ( $taxonomy === $this->taxonomy ) {
						$this->taxonomy_object = get_taxonomy( $taxonomy );
					}
				}, 10, 3 );
			}
//			\add_action( '', function() {
//				$this->taxonomy_object = \get_taxonomy( $this->taxonomy );
//			} );
//			add_action( 'all', function() {
//				global $wp_taxonomies;
//				if ( !empty( $wp_taxonomies ) && isset( $wp_taxonomies[$this->taxonomy] ) ) {
//					wp_die( current_action() );
//				}
//			} );
		}

		/** PUBLIC API */

		/**
		 * Adds singular rewrites.
		 */
		public function add_singular_rewrites(): void {
			$this->add_singular_rewrites = true;
		}

		/**
		 * Removes singular rewrites.
		 */
		public function remove_singular_rewrites(): void {
			$this->add_singular_rewrites = false;
		}

		/**
		 * Adds archive rewrites.
		 */
		public function add_archive_rewrites(): void {
			$this->add_archive_rewrites = true;
		}

		/**
		 * Removes archive rewrites.
		 */
		public function remove_archive_rewrites(): void {
			$this->add_archive_rewrites = false;
		}

		/** PRIVATE API */

		protected function get_taxonomy_object() {
			if ( $this->taxonomy_object ) {
				return $this->taxonomy_object;
			}

			$this->taxonomy_object = get_taxonomy( $this->taxonomy );
			return $this->taxonomy_object;
		}

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
			if ( $this->post_type !== $post->post_type || ! $this->add_singular_rewrites ) {
				return $post_link;
			}

			$post_type_object = \get_post_type_object( $this->post_type );

			// Remove the home URL base.
			$old_path = str_replace( \trailingslashit( \home_url() ), '/', $post_link );

			// Remove the post type object rewrite
			$old_path = str_replace( '/' . $post_type_object->rewrite['slug'] . '/', '/', $old_path );

			// Start building the new path.
			$path = $this->prefix . implode( '/', $this->order );
			$term = $this->get_the_first_term( $post, $this->taxonomy );
			if ( is_wp_error( $term ) ) {
				return $post_link;
			}
			$path = $this->replace_tokens( $path, 'singular', $term );

			if ( ! is_wp_error( $path ) && '' !== $path ) {
				$path .= $old_path;

				// Return re-adding home URL base.
				return \home_url( $path );
			}

			return $post_link;
		}

		protected function get_term_slug( \WP_Term $term ): string {
			$parts = [ $term->slug ];
			if ( 0 !== $term->parent && $this->get_taxonomy_object()->rewrite['hierarchical'] ) {
				$parts[] = $this->get_term_slug( get_term( $term->parent, $this->taxonomy ) );
			}

			return implode( '/', array_reverse( $parts ) );
		}

		/**
		 * Replaces tokens from those allowed.
		 *
		 * @param string $path Starting path.
		 * @param string $path_type Either `singular` or `archive`.
		 * @param \WP_Term|null $term The post or term object.
		 *
		 * @return string
		 * @see $allowed_order_tokens Allowed tokens for order.
		 *
		 */
		protected function replace_tokens( string $path, string $path_type, \WP_Term $term = null ): string {
			// Maybe replace with taxonomy.
			if ( in_array( '%taxonomy%', $this->order ) ) {
				$taxonomy = $this->get_taxonomy_object();
				if ( false !== $taxonomy ) {
					$path = str_replace( '%taxonomy%', $taxonomy->rewrite['slug'], $path );
				}
			}

			// Maybe replace with the primary term.
			if ( in_array( '%term%', $this->order ) ) {
				if ( ! empty( $term ) ) {
					$slug = $this->get_term_slug( $term );
					$path = str_replace( '%term%', $slug, $path );
				} else {
					$path = str_replace( '%term%', '(.+?)', $path );
				}
			}

			// Maybe replace with post type.
			if ( in_array( '%post_type%', $this->order ) ) {
				$post_type_object = \get_post_type_object( $this->post_type );
				if ( 'archive' === $path_type ) {
					$slug = is_string( $post_type_object->has_archive ) ? $post_type_object->has_archive : $post_type_object->rewrite['slug'];
				} else {
					$slug = $post_type_object->rewrite['slug'];
				}

				$path = str_replace( '%post_type%', $slug, $path );
			}

			return $path;
		}

		/**
		 * Filters the term link.
		 *
		 * @param string $termlink Term link URL.
		 * @param \WP_Term $term Term object.
		 * @param string $taxonomy Taxonomy slug.
		 */
		public function term_link( string $termlink, \WP_Term $term, string $taxonomy ) {
			if ( $this->taxonomy !== $taxonomy || ! in_array( '%term%', $this->order ) ) {
				return $termlink;
			}

			$path = implode( '/', $this->order );
			$path = $this->replace_tokens( $path, 'archive', $term );
			if ( is_wp_error( $path ) ) {
				return $termlink;
			}

			return \home_url( user_trailingslashit( $path, 'category' ) );
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
			// Don't do anything if singular rules are not added.
			if ( ! $this->add_singular_rewrites ) {
				return $needs_suffix;
			}

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
		 * @param \WP_Term|\stdClass $term Term Object.
		 *
		 * @return bool
		 */
		public function wp_unique_term_slug_is_bad_slug( bool $needs_suffix, string $slug, $term ): bool {
			// Don't do anything if archive rules are not added.
			if ( ! $this->add_archive_rewrites ) {
				return $needs_suffix;
			}

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
		 * @return bool|null|\WP_Error|\WP_Term
		 */
		protected function get_the_first_term( $post_or_id, string $taxonomy = 'category' ) {
			if ( ! $post = \get_post( $post_or_id ) ) {
				return new \WP_Error( 'no-post', \__( 'No post or post ID given.' ), $post );
			}

			$term = false;

			// Use WP SEO Primary Term
			// from https://github.com/Yoast/wordpress-seo/issues/4038
			if ( class_exists( 'WPSEO_Primary_Term' ) ) {
				return \get_term( ( new \WPSEO_Primary_Term( $taxonomy, $post->ID ) )->get_primary_term(), $taxonomy );
			}

			// Fallback on term with highest post count
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				return new \WP_Error( 'no-term', sprintf( \__( 'No terms associated to post (%d)' ), $post->ID ) );
			}

			// If there's only one term, use that
			if ( 1 == count( $terms ) ) {
				$term = array_shift( $terms );

				// If there's more than one...
			} else {

				// Sort by term order if available
				// @uses WP Term Order plugin
				$list = [];
				if ( isset( $terms[0]->order ) ) {
					foreach ( $terms as $term ) {
						$list[ $term->order ] = $term;
					}

					ksort( $list, SORT_NUMERIC );

					// Or sort by post count
				} else {
					foreach ( $terms as $term ) {
						$list[ $term->count ] = $term;
					}

					ksort( $list, SORT_NUMERIC );
					$list = array_reverse( $list );
				}
				$term = array_shift( $list );
			}

			return $term;
		}

		/**
		 * Gets the terms for this.
		 *
		 * @access private
		 * @return array|\WP_Error
		 */
		protected function get_terms() {
			return \get_terms( [
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			] );
		}

		/**
		 * Get default rewrite matches.
		 *
		 * @param \WP_Term|null $term Term object.
		 *
		 * @return array
		 */
		protected function get_default_rewrite_matches( \WP_Term $term = null ): array {
			$defaults = [
				'wps-taxonomy'  => $this->taxonomy,
				'wps-post_type' => $this->post_type,
				'post_type'     => $this->post_type,
				'taxonomy'      => $this->taxonomy,
			];

			if ( null === $term ) {
				return $defaults;
			}

			return $defaults + [
					'term'          => $term->slug,
					'wps-term'      => $term->slug,
					$this->taxonomy => $term->slug
				];
		}

		/**
		 * Get term rewrite matches.
		 *
		 * @param \WP_Term|null $term Term object.
		 * @param int $number Match number.
		 *
		 * @return array
		 */
		protected function get_term_rewrite_matches( int $number = 1 ): array {
			return [
				'wps-term'      => sprintf( '$matches[%d]', $number ),
				$this->taxonomy => sprintf( '$matches[%d]', $number ),
			];
		}

		/**
		 * Gets the date part matches.
		 *
		 * @param string[] $date_parts
		 * @param int $increment
		 *
		 * @return array
		 */
		protected function get_date_matches( array $date_parts, int $increment ): array {
			$matches = [];
			foreach ( $date_parts as $index => $part ) {
				$matches[ $part ] = sprintf( '$matches[%d]', $index + $increment );
			}

			return $matches;
		}

		/**
		 * Gets the date part pattern.
		 *
		 * @param string[] $date_parts Date parts.
		 *
		 * @return string
		 */
		protected function get_date_part_pattern( array $date_parts ): string {
			$pattern = '';
			foreach ( $date_parts as $date_part ) {
				if ( 'year' === $date_part ) {
					$pattern .= '/([0-9]{4})';
				} else {
					$pattern .= '/([0-9]{1,2})';
				}
			}

			return $pattern;
		}

		protected function get_date_part_rules( $date_parts, $path, $increment, $query_params ): array {
			$rules              = [];
			$defaults           = $this->get_default_rewrite_matches();
			$date_parts_matches = $this->get_date_matches( $date_parts, $increment );

			// {path}/YYYY/MM/DD/ Archive URLs.
			$rules[ $path . $this->get_date_part_pattern( $date_parts ) . '/?$' ] = 'index.php?' . \build_query( wp_parse_args( $date_parts_matches,
						$defaults ) + $query_params );

			// {path}/YYYY/MM/DD/page/#/ Pagination Archive URLs.
			if ( $this->rewrites['page'] ) {
				$matches = $this->get_date_matches( $date_parts + [ 'paged' ], $increment );

				$rules[ $path . $this->get_date_part_pattern( $date_parts ) . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( wp_parse_args( $matches,
							$defaults ) + $query_params );
			}

			// {path}/YYYY/MM/DD/page/#/ Feed Archive URLs.
			if ( $this->rewrites['feed'] ) {
				$matches = $this->get_date_matches( $date_parts + [ 'feed' ], $increment );

				// {path}/YYYY/MM/DD/feed/rss/ Archive URLs.
				$rules[ $path . $this->get_date_part_pattern( $date_parts ) . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( wp_parse_args( $matches,
							$defaults ) + $query_params );

				// {path}/YYYY/MM/DD/rss/ Archive URLs.
				$rules[ $path . $this->get_date_part_pattern( $date_parts ) . '/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( wp_parse_args( $matches,
							$defaults ) + $query_params );
			}

			// {path}/YYYY/MM/DD/embed/ Archive URLs.
			if ( $this->rewrites['embed'] ) {
				$rules[ $path . $this->get_date_part_pattern( $date_parts ) . '/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( $date_parts_matches + [ 'embed' => 'true', ],
							$defaults ) + $query_params );
			}

			return $rules;
		}

		protected function get_query_param_args( $increment ) {
			$m = sprintf( '$matches[%d]', $increment - 1 );

			return [
				'term'          => $m,
				'wps-term'      => $m,
				$this->taxonomy => $m,
			];
		}

//		protected function merge_query_param_args( $query_params, $increment ) {
//			return wp_parse_args( $this->get_query_param_args( $increment ), $query_params );
//		}

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
		protected function get_term_archive_rewrite_rules( string $path, \WP_Term $term = null ): array {
			$defaults = $this->get_default_rewrite_matches( $term );

			$rules = [];

			$increment = ( null === $term ? 2 : 1 );

			$query_params = [];
			if ( null === $term ) {
				$query_params = $this->get_query_param_args( $increment );
			}

			// {path}/page/#/ Pagination Archive URLs.
			if ( $this->rewrites['page'] ) {
				$qps = wp_parse_args( $query_params, [
					'paged' => sprintf( '$matches[%d]', $increment ),
				] );

				$rules[ $path . '/page/?([0-9]{1,})/?$' ] = 'index.php?' . \build_query( $defaults + $this->get_term_rewrite_matches( 1 ) + $qps );
			}

			// {path}/page/#/ Feed Archive URLs.
			if ( $this->rewrites['feed'] ) {
				$qps = wp_parse_args( $query_params, [
					'feed' => sprintf( '$matches[%d]', $increment ),
				] );

				// {path}/feed/{feed}/ Feed URLs.
				$rules[ $path . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( $defaults + $this->get_term_rewrite_matches( 1 ) + $qps );

				// {path}/{feed}/ Feed URLs.
				$rules[ $path . '/(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?' . \build_query( $defaults + $this->get_term_rewrite_matches( 1 ) + $qps );
			}

			// {path}/embed/ Embed Archive URL.
			if ( $this->rewrites['embed'] ) {
				$qps = wp_parse_args( $query_params, [
					'embed' => 'true',
				] );

				// {path}/embed/ Feed URLs.
				$rules[ $path . '/embed/?$' ] = 'index.php?' . \build_query( $defaults + $this->get_term_rewrite_matches( 1 ) + $qps );
			}

			// Date Archives.
			if ( $this->rewrites['date'] ) {
				// Year, Month, Day Archives.
				$rules += $this->get_date_part_rules( [ 'year', 'monthnum', 'day' ], $path, $increment, $query_params );

				// Year, Month Archives.
				$rules += $this->get_date_part_rules( [ 'year', 'monthnum' ], $path, $increment, $query_params );

				// Year Archives.
				$rules += $this->get_date_part_rules( [ 'year' ], $path, $increment, $query_params );
			}

			// {path}/ Archive URL.
			$rules[ $path . '/?$' ] = 'index.php?' . \build_query( wp_parse_args( [ $this->taxonomy => '$matches[1]' ], $defaults ) );

			return $rules;
		}

		protected function get_term_singular_rewrite_rules( string $path, \WP_Term $term = null ): array {
			$defaults = $this->get_default_rewrite_matches( $term );

			// {path}/embed/ Embed Singular URLs.
			if ( $this->rewrites['embed'] ) {
				// Paginated embed Singular URLs.
				$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
						$this->post_type => '$matches[2]',
						'page'           => '$matches[4]',
						'embed'          => 'true',
					], $defaults ) );

				// Default embed Singular URLs.
				$rules[ $path . '/(.?.+?)/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
						$this->post_type => '$matches[1]',
						'embed'          => 'true',
					], $defaults ) );
			}

			// {prefix}/{term}/{custom-post-type}/{postname} URLs.
			$rules[ $path . '/(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
					$this->post_type => '$matches[1]',
					'page'           => '$matches[2]',
				], $defaults ) );

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
			if ( $post_type_object->has_archive && $this->add_archive_rewrites ) {
				$post_type_archive_slug = isset( $post_type_object->has_archive ) && is_string( $post_type_object->has_archive ) && '' !== $post_type_object->has_archive ? $post_type_object->has_archive : $post_type_slug;

				$rules = $this->get_cpt_rewrite_rules( $this->prefix . $post_type_archive_slug );
			}

			// Default path.
			$path = $this->prefix . implode( '/', $this->order );

			// {path}/ Basic Archive URL.
			if ( $post_type_object->has_archive && $this->add_archive_rewrites ) {
				$archive_path = $this->replace_tokens( $path, 'archive' );
				$rules        = array_merge( $rules, $this->get_term_archive_rewrite_rules( $archive_path ) );
//				$rules[ $archive_path . '/?$' ] = 'index.php?' . \build_query( $this->get_default_rewrite_matches() + [ $this->taxonomy => '$matches[1]' ] );
			}

			// Cycle through our terms.
			foreach ( $this->get_terms() as $term ) {
				// Archive URLs.
				if ( $post_type_object->has_archive && $this->add_archive_rewrites ) {
					$archive_path = $this->replace_tokens( $path, 'archive', $term );
					$rules        = array_merge( $rules, $this->get_term_archive_rewrite_rules( $archive_path, $term ) );
				}

				// Singular URLs.
				if ( $post_type_object->public && $this->add_singular_rewrites ) {
					$singular_path = $this->replace_tokens( $path, 'singular', $term );
					$rules         = array_merge( $rules, $this->get_term_singular_rewrite_rules( $singular_path, $term ) );
				}
			}

			// Add rules to top; first match wins!
			$wp_rewrite->rules = $rules + $wp_rewrite->rules;

			return $wp_rewrite->rules;
		}

		protected function get_the_rewrite_rules_with_term( $path, $term ) {
			$rules = [];

			// Get post type object.
			$post_type_object = \get_post_type_object( $this->post_type );

			// Archive URLs.
			if ( $post_type_object->has_archive && $this->add_archive_rewrites ) {
				$archive_path = $this->replace_tokens( $path, 'archive' );
				$rules        = array_merge( $rules, $this->get_term_archive_rewrite_rules( $archive_path, $term ) );
			}

			// Singular URLs.
			if ( $post_type_object->public && $this->add_singular_rewrites ) {
				$defaults = $this->get_default_rewrite_matches( $term );
				$path     = $this->replace_tokens( $path, 'singular', $term );

				// {path}/embed/ Embed Singular URLs.
				if ( $this->rewrites['embed'] ) {
					// @todo Fix me.
					// [^/]+/([^/]+)
					// Default embed Singular URLs.
					$rules[ $path . '/([^/]+)/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
							$this->post_type => '$matches[2]',
							'embed'          => 'true',
						], $defaults ) );

//						$rules[ $path . '[^/]+?(.?.+?)/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
//								$this->post_type => '$matches[2]',
//								'embed'          => 'true',
//							], $defaults ) );

//						// Paginated embed Singular URLs.
//						$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
//								$this->post_type => '$matches[2]',
//								'page'           => '$matches[4]',
//								'embed'          => 'true',
//							], $defaults ) );
				}

				// {prefix}/{term}/{custom-post-type}/{postname} URLs.
				$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
						$this->post_type => '$matches[2]',
						'page'           => '$matches[4]',
					], $defaults ) );
			}
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
		public function rewrite_rules_working( $wp_rewrite ): array {
			// Get post type slug.
			$post_type_object = \get_post_type_object( $this->post_type );
			$post_type_slug   = $post_type_object->rewrite['slug'] ?? $this->post_type;

			$rules = [];

			// Add the custom post type archive rules.
			if ( $post_type_object->has_archive && $this->add_archive_rewrites ) {
				$post_type_archive_slug = isset( $post_type_object->has_archive ) && is_string( $post_type_object->has_archive ) && '' !== $post_type_object->has_archive ? $post_type_object->has_archive : $post_type_slug;

				$rules = $this->get_cpt_rewrite_rules( $this->prefix . $post_type_archive_slug );
			}

			// Cycle through our terms.
			foreach ( $this->get_terms() as $term ) {
				// Default path.
				$path = $this->prefix . implode( '/', $this->order );

				// Archive URLs.
				if ( $post_type_object->has_archive && $this->add_archive_rewrites ) {
					$archive_path = $this->replace_tokens( $path, 'archive', $term );
					$rules        = array_merge( $rules, $this->get_term_archive_rewrite_rules( $archive_path, $term ) );
				}

				// Singular URLs.
				if ( $post_type_object->public && $this->add_singular_rewrites ) {
					$defaults = $this->get_default_rewrite_matches( $term );
					$path     = $this->replace_tokens( $path, 'singular', $term );

					// {path}/embed/ Embed Singular URLs.
					if ( $this->rewrites['embed'] ) {
						// @todo Fix me.
						// [^/]+/([^/]+)
						// Default embed Singular URLs.
						$rules[ $path . '/([^/]+)/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
								$this->post_type => '$matches[2]',
								'embed'          => 'true',
							], $defaults ) );

//						$rules[ $path . '[^/]+?(.?.+?)/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
//								$this->post_type => '$matches[2]',
//								'embed'          => 'true',
//							], $defaults ) );

//						// Paginated embed Singular URLs.
//						$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))/embed/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
//								$this->post_type => '$matches[2]',
//								'page'           => '$matches[4]',
//								'embed'          => 'true',
//							], $defaults ) );
					}

					// {prefix}/{term}/{custom-post-type}/{postname} URLs.
					$rules[ $path . '([^/]+)?(.?.+?)(?:/([0-9]+))?/?$' ] = 'index.php?' . \build_query( wp_parse_args( [
							$this->post_type => '$matches[2]',
							'page'           => '$matches[4]',
						], $defaults ) );
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
		public function pre_get_posts( \WP_Query $query ) {
			parent::pre_get_posts( $query );

			// Make sure we are only dealing with our rewrites.
			if ( $this->is_not_this_main_query( $query ) ) {
				return;
			}

			// Set the value of the query.
			global $wp_the_query;

			// Get the Post Object.
			$post = $this->get_post_from_query( $query );

			// For single posts with a taxonomy slug prefix.
			if ( isset( $query->query_vars[ $this->taxonomy ] ) ) {
				// Set singular to 404 if we do not have the term associated with the post.
				if ( \is_singular( $this->post_type ) &&
				     ! \has_term( $query->query_vars[ $this->taxonomy ], $this->taxonomy, $post )
				) {
					$wp_the_query->set_404();
					$query->set_404();
					\set_query_var( $this->taxonomy . '-' . $this->post_type, 404 );

					// For anonymous functions.
					$self = $this;

					// Make sure everything bugs out!
					$fn = function ( $redirect_url ) use ( $self ) {
						if ( 404 === get_query_var( $self->taxonomy . '-' . $self->post_type ) ) {
							return null;
						}

						return $redirect_url;
					};
					\add_filter( 'redirect_canonical', $fn );
					\add_filter( 'old_slug_redirect_url', $fn );
				}
			}
		}

		/**
		 * Default args.
		 *
		 * @access private
		 * @return array Array of defaults.
		 */
		public function defaults(): array {
			$defaults             = parent::defaults();
			$defaults['taxonomy'] = '';

			return $defaults;
		}

		/**
		 * Whether the current query has $this's query var.
		 *
		 * @access private
		 * @return bool
		 */
		protected function has_query_var(): bool {
			global $wp_query;

			// @formatter:off
			return (
				parent::has_query_var() || // parent::has_query_var() && is_singular() ||
				(
					\is_singular() &&
					isset( $wp_query->query_vars[ $this->taxonomy ] ) &&
					isset( $wp_query->query_vars['post_type'] ) &&
					$this->post_type === $wp_query->query_vars['post_type']
				) ||
				(
					isset( $wp_query->query_vars[ $this->post_type ] ) &&
					'' !== $wp_query->query_vars[ $this->post_type ]
				) ||
				\is_post_type_archive( $this->post_type )
			);
			// @formatter:on
		}
	}
}
