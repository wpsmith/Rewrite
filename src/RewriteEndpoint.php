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

if ( ! class_exists( __NAMESPACE__ . '\RewriteEndpoint' ) ) {
	/**
	 * Class RewriteEndpoint.
	 *
	 * @package WPS\WP\Rewrite
	 */
	class RewriteEndpoint {

		/**
		 * Original args
		 *
		 * @var array
		 */
		protected $args;

		/**
		 * Endpoint mask describing the places the endpoint should be added.
		 *     Accepts a mask of:
		 *     - `EP_ALL`
		 *     - `EP_NONE`
		 *     - `EP_ALL_ARCHIVES`
		 *     - `EP_ATTACHMENT`
		 *     - `EP_AUTHORS`
		 *     - `EP_CATEGORIES`
		 *     - `EP_COMMENTS`
		 *     - `EP_DATE`
		 *     - `EP_DAY`
		 *     - `EP_MONTH`
		 *     - `EP_PAGES`
		 *     - `EP_PERMALINK`
		 *     - `EP_ROOT`
		 *     - `EP_SEARCH`
		 *     - `EP_TAGS`
		 *     - `EP_YEAR`
		 *
		 * See also `add_rewrite_endpoint()`
		 * @var int
		 */
		protected $places;

		/**
		 * Name of the endpoint.
		 *
		 * See also `add_rewrite_endpoint()`
		 *
		 * @var string
		 */
		protected $slug;

		/**
		 * Query var.
		 *
		 * @var string
		 */
		protected $var;

		/**
		 * Rules being added.
		 *
		 * @var array
		 */
		protected $rules;

		/**
		 * Template to include when query var has been detected.
		 *
		 * @var string
		 */
		protected $template;

		/**
		 * Array of new rewrite tags.
		 *
		 * Is an array of 'tag' => 'regex'.
		 * Regular expression to substitute the tag for in rewrite rules.
		 *
		 * @see add_rewrite_tags
		 * @var array
		 */
		protected $tags;

		/**
		 * Rewrite_Endpoint constructor.
		 *
		 * @param array|string $slug Endpoint slug.
		 * @param array $args Array of class args.
		 */
		public function __construct( $slug, array $args = array() ) {
			$this->args = $this->get_args( $slug, $args );
			$this->args = wp_parse_args( $this->args, $this->defaults() );

			$this->places   = $this->get_val( 'places', $this->args );
			$this->rules    = $this->get_val( 'rules', $this->args );
			$this->slug     = $this->get_val( 'slug', $this->args );
			$this->slug     = $this->slug ? $this->slug : $this->get_val( 'var', $this->args );
			$this->var      = $this->get_val( 'var', $this->args );
			$this->template = $this->get_val( 'template', $this->args );
			$this->tags     = $this->get_val( 'tags', $this->args );

			// Add Hooks.
			\add_filter( 'query_vars', array( $this, 'query_vars' ) );

			// Template Include Hook.
			if ( null !== $this->template ) {
				\add_action( 'template_include', array( $this, 'template_include' ), PHP_INT_MAX );
			}

			if ( null !== $this->tags ) {
				\add_action( 'init', array( $this, 'add_rewrite_tags' ), 10, 0 );
			}

			// Rewrite custom rules.
			if ( method_exists( $this, 'rewrite_rules' ) ) {
				\add_filter( 'generate_rewrite_rules', array( $this, 'rewrite_rules' ) );
			} elseif ( ! empty( $this->rules ) ) {
				$rules = $this->rules;
				\add_filter( 'generate_rewrite_rules', function ( $wp_rewrite ) use ( $rules ) {
					$wp_rewrite->rules = $rules + $wp_rewrite->rules;

					return $wp_rewrite->rules;
				} );
			} else {
				\add_action( 'init', array( $this, 'add_rewrite_endpoint' ) );
			}

			if ( method_exists( $this, 'pre_get_posts' ) ) {
				\add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
			}
		}

		/**
		 * Gets the value of a key from an array if key exists.
		 *
		 * @param string $key
		 * @param array $arr Associative array to get value if set.
		 *
		 * @return null|mixed Value if set or null if no value.
		 */
		protected function get_val( string $key, array $arr ) {
			if ( isset( $arr[ $key ] ) ) {
				return $arr[ $key ];
			}

			return null;
		}

		/**
		 * Gets the args from the constructor.
		 *
		 * @param array|string $slug Endpoint slug.
		 * @param array $args Associative array of class args.
		 *
		 * @return array
		 */
		protected function get_args( $slug, array $args ): array {
			if ( is_array( $slug ) ) {
				$args = $slug;
			} else {
				if ( ! isset( $args['slug'] ) ) {
					$args['slug'] = $slug;
				}
				if ( ! isset( $args['var'] ) ) {
					$args['var'] = $slug;
				}
			}

			return $args;
		}

		/**
		 * Adds rewrite tags.
		 */
		public function add_rewrite_tags() {
			foreach ( (array) $this->tags as $tag => $regex ) {
				$tag = '%' . trim( $tag, '%' ) . '%';
				add_rewrite_tag( $tag, $regex );
			}
		}

		/**
		 * Default args.
		 *
		 * @return array Array of defaults.
		 */
		public function defaults(): array {
			return array(
				'places'   => EP_PERMALINK | EP_PAGES,
				'rules'    => '',
				'slug'     => '',
				'template' => '',
				'var'      => '',
			);
		}

		/**
		 * Adds endpoint.
		 */
		public function add_rewrite_endpoint() {
			add_rewrite_endpoint( $this->slug, $this->places );
		}

		/**
		 * Whether the current query has this's query var.
		 *
		 * @return bool
		 */
		protected function has_query_var(): bool {
			global $wp_query;

			// Must be a request with our query var and a singular object.
			return ( isset( $this->var ) && isset( $wp_query->query_vars[ $this->var ] ) );
		}

		/**
		 * Add our query var to the query vars.
		 *
		 * @param []string $vars Array of query vars.
		 *
		 * @return array
		 */
		public function query_vars( $vars ): array {
			if ( isset( $this->var ) ) {
				$vars[] = $this->var;
			}

			return $vars;
		}

		/**
		 * Conditionally includes the template.
		 *
		 * @param string $template Template path.
		 *
		 * @return mixed|null|string
		 *
		 * @global \WP_Filesystem_Base $wp_filesystem Subclass
		 */
		public function template_include( string $template ) {
			if ( ! $this->has_query_var() || \is_admin() ) {
				return $template;
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			\WP_Filesystem();
			/**
			 * @var \WP_Filesystem_Base $wp_filesystem Filesystem.
			 */ global $wp_filesystem;

			// include custom template.
			if ( $wp_filesystem->is_file( $this->template ) ) {
				return $this->template;
			}

			return $template;
		}
	}
}
