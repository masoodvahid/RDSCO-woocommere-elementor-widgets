<?php
/**
 * Public, read-only REST endpoint used by the search widgets.
 *
 * GET /wp-json/rdsco-wew/v1/search?q=belt&limit=8&cats=12,15&content=1&group=1
 */

namespace RDSCO\WEW;

use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Search_API {

	public const REST_NS   = 'rdsco-wew/v1';
	public const ROUTE     = '/search';
	public const MIN_CHARS = 3;
	public const MAX_CHARS = 64;
	public const MAX_LIMIT = 20;
	private const MAX_WORDS = 5;
	private const MAX_CATS  = 50;
	private const CACHE_GRP = 'rdsco_wew';
	private const CACHE_TTL = 3600; // Object-cache TTL; keys also change on any post/term change.
	private const HTTP_TTL  = 300;  // Browser/CDN cache for the response.

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_route' ] );
	}

	public static function endpoint(): string {
		return rest_url( self::REST_NS . self::ROUTE );
	}

	public static function register_route(): void {
		register_rest_route(
			self::REST_NS,
			self::ROUTE,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'handle_request' ],
				'permission_callback' => '__return_true', // Public, read-only product data.
				'args'                => [
					'q'       => [
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && mb_strlen( self::normalize_term( $value ) ) >= self::MIN_CHARS,
						'sanitize_callback' => [ self::class, 'normalize_term' ],
					],
					'limit'   => [
						'type'              => 'integer',
						'default'           => 8,
						'minimum'           => 1,
						'maximum'           => self::MAX_LIMIT,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'absint',
					],
					'cats'    => [
						'type'              => 'string',
						'default'           => '',
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && 1 === preg_match( '/^[0-9,]{0,600}$/', $value ),
						'sanitize_callback' => [ self::class, 'parse_ids' ],
					],
					'content' => [
						'type'              => 'boolean',
						'default'           => true,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'group'   => [
						'type'              => 'boolean',
						'default'           => false,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);
	}

	public static function handle_request( WP_REST_Request $request ): WP_REST_Response {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_REST_Response( [ 'items' => [] ], 503 );
		}

		$args = [
			'term'    => (string) $request->get_param( 'q' ),
			'limit'   => max( 1, min( self::MAX_LIMIT, (int) $request->get_param( 'limit' ) ) ),
			'cats'    => self::parse_ids( $request->get_param( 'cats' ) ),
			'content' => (bool) $request->get_param( 'content' ),
			'group'   => (bool) $request->get_param( 'group' ),
		];

		$response = new WP_REST_Response( [ 'items' => self::cached_search( $args ) ] );

		// Visitors' requests carry no cookies, so their responses are shareable.
		// Logged-in requests keep core's no-cache headers.
		if ( ! is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'public, max-age=' . self::HTTP_TTL );
		}

		return $response;
	}

	public static function normalize_term( $value ): string {
		$value = is_string( $value ) ? wp_check_invalid_utf8( $value ) : '';
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? '';

		return mb_substr( trim( $value ), 0, self::MAX_CHARS );
	}

	/**
	 * @param mixed $value Comma-separated string or array of IDs.
	 * @return int[]
	 */
	public static function parse_ids( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
		sort( $ids );

		return array_slice( $ids, 0, self::MAX_CATS );
	}

	/* ---------------------------------------------------------------------
	 * Search
	 * ------------------------------------------------------------------ */

	/**
	 * Uses the persistent object cache when available. The key includes the
	 * core posts/terms last-changed stamps, so product or category edits
	 * invalidate it with no extra writes. Without a persistent cache we skip
	 * server-side caching on purpose (transients would write a DB row per
	 * typed term).
	 *
	 * @param array{term:string,limit:int,cats:int[],content:bool,group:bool} $args
	 * @return array<int, array<string, mixed>>
	 */
	private static function cached_search( array $args ): array {
		if ( ! wp_using_ext_object_cache() ) {
			return self::search( $args );
		}

		$fingerprint = $args;
		$fingerprint['term']   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $args['term'] ) : strtolower( $args['term'] );
		$fingerprint['locale'] = get_locale();

		$key = 'q_' . md5( (string) wp_json_encode( $fingerprint ) )
			. '_' . wp_cache_get_last_changed( 'posts' )
			. '_' . wp_cache_get_last_changed( 'terms' );

		$items = wp_cache_get( $key, self::CACHE_GRP );
		if ( ! is_array( $items ) ) {
			$items = self::search( $args );
			wp_cache_set( $key, $items, self::CACHE_GRP, self::CACHE_TTL );
		}

		return $items;
	}

	/**
	 * @param array{term:string,limit:int,cats:int[],content:bool,group:bool} $args
	 * @return array<int, array<string, mixed>>
	 */
	private static function search( array $args ): array {
		// Category scope includes sub-categories. term_id => term_taxonomy_id.
		$scope = $args['cats'] ? self::expand_categories( $args['cats'] ) : [];
		if ( $args['cats'] && ! $scope ) {
			return []; // Scoped to categories that no longer exist: never widen to all products.
		}

		$ids = self::query_ids( $args['term'], $args['limit'], array_values( $scope ), $args['content'] );
		if ( ! $ids ) {
			return [];
		}

		// One query for posts + meta + terms of the matched IDs only.
		$query = new WP_Query(
			[
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
			]
		);
		update_post_thumbnail_cache( $query );

		$items = [];
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post );
			if ( ! $product ) {
				continue;
			}

			$image_id = $product->get_image_id();

			$item = [
				'id'    => $product->get_id(),
				'title' => html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' ),
				'url'   => esc_url_raw( $product->get_permalink() ),
				'sku'   => (string) $product->get_sku(),
				'price' => wp_kses_post( $product->get_price_html() ),
				'image' => $image_id ? (string) wp_get_attachment_image_url( (int) $image_id, 'woocommerce_gallery_thumbnail' ) : '',
			];

			if ( $args['group'] ) {
				$item['cat'] = self::group_label( $product->get_id(), $scope );
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * @param int[] $term_ids
	 * @return array<int, int> term_id => term_taxonomy_id (existing product_cat terms only).
	 */
	private static function expand_categories( array $term_ids ): array {
		$all = [];
		foreach ( $term_ids as $id ) {
			$all[]    = $id;
			$children = get_term_children( $id, 'product_cat' );
			if ( is_array( $children ) ) {
				foreach ( $children as $child ) {
					$all[] = (int) $child;
				}
			}
		}

		$terms = get_terms(
			[
				'taxonomy'               => 'product_cat',
				'include'                => array_values( array_unique( $all ) ),
				'hide_empty'             => false,
				'update_term_meta_cache' => false,
			]
		);
		if ( ! is_array( $terms ) ) {
			return [];
		}

		$map = [];
		foreach ( $terms as $term ) {
			$map[ (int) $term->term_id ] = (int) $term->term_taxonomy_id;
		}

		return $map;
	}

	/**
	 * Category label used to group a result: Yoast's primary category when
	 * set, preferring categories inside the current search scope.
	 *
	 * @param array<int, int> $scope term_id => term_taxonomy_id
	 */
	private static function group_label( int $product_id, array $scope ): string {
		$terms = get_the_terms( $product_id, 'product_cat' ); // Primed by WP_Query.
		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}

		$primary  = (int) get_post_meta( $product_id, '_yoast_wpseo_primary_product_cat', true );
		$in_scope = static fn( $term ): bool => ! $scope || isset( $scope[ (int) $term->term_id ] );
		$pick     = null;

		foreach ( $terms as $term ) {
			if ( (int) $term->term_id === $primary && $in_scope( $term ) ) {
				$pick = $term;
				break;
			}
		}
		if ( ! $pick ) {
			foreach ( $terms as $term ) {
				if ( $in_scope( $term ) ) {
					$pick = $term;
					break;
				}
			}
		}
		$pick ??= reset( $terms );

		return html_entity_decode( (string) $pick->name, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Single SQL query returning ranked product IDs.
	 *
	 * - Name (and optionally short/long description): every word must match.
	 * - SKU: the full term, from the indexed wc_product_meta_lookup table,
	 *   including variation SKUs (mapped to the parent product).
	 * - Optional category scope (term_taxonomy_ids, sub-categories included).
	 * - Respects "Hidden from search" and "Hide out of stock items".
	 *
	 * @param int[] $tt_ids
	 * @return int[]
	 */
	private static function query_ids( string $term, int $limit, array $tt_ids, bool $content ): array {
		global $wpdb;

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		$args   = [];

		// Words for text matching (skip 1-char noise, cap the count).
		$words = preg_split( '/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$words = array_values( array_filter( array_unique( $words ), static fn( string $w ): bool => mb_strlen( $w ) >= 2 ) );
		$words = array_slice( $words ?: [ $term ], 0, self::MAX_WORDS );

		$text_parts = [];
		foreach ( $words as $word ) {
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			if ( $content ) {
				$text_parts[] = '(p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s)';
				array_push( $args, $like, $like, $like );
			} else {
				$text_parts[] = 'p.post_title LIKE %s';
				$args[]       = $like;
			}
		}

		$like_term = '%' . $wpdb->esc_like( $term ) . '%';
		array_push( $args, $like_term, $like_term );

		$sql = "SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$lookup} l ON l.product_id = p.ID
			WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND p.post_password = ''
				AND (
					( " . implode( ' AND ', $text_parts ) . " )
					OR l.sku LIKE %s
					OR p.ID IN (
						SELECT v.post_parent
						FROM {$wpdb->posts} v
						INNER JOIN {$lookup} lv ON lv.product_id = v.ID
						WHERE v.post_type = 'product_variation'
							AND v.post_status = 'publish'
							AND lv.sku LIKE %s
					)
				)";

		if ( $tt_ids ) {
			$sql .= " AND p.ID IN (
				SELECT tr_cat.object_id FROM {$wpdb->term_relationships} tr_cat
				WHERE tr_cat.term_taxonomy_id IN (" . implode( ',', array_fill( 0, count( $tt_ids ), '%d' ) ) . ')
			)';
			array_push( $args, ...$tt_ids );
		}

		$exclude = self::excluded_visibility_terms();
		if ( $exclude ) {
			$sql .= " AND p.ID NOT IN (
				SELECT tr_vis.object_id FROM {$wpdb->term_relationships} tr_vis
				WHERE tr_vis.term_taxonomy_id IN (" . implode( ',', array_fill( 0, count( $exclude ), '%d' ) ) . ')
			)';
			array_push( $args, ...$exclude );
		}

		// Ranking: exact SKU > name starts with > name contains > SKU contains > the rest.
		$sql .= ' ORDER BY CASE
				WHEN l.sku = %s THEN 0
				WHEN p.post_title LIKE %s THEN 1
				WHEN p.post_title LIKE %s THEN 2
				WHEN l.sku LIKE %s THEN 3
				ELSE 4
			END, p.post_title ASC
			LIMIT %d';
		array_push( $args, $term, $wpdb->esc_like( $term ) . '%', $like_term, $like_term, $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names come from $wpdb; all values are prepared.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );

		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * @return int[] term_taxonomy_ids to exclude.
	 */
	private static function excluded_visibility_terms(): array {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return [];
		}

		$terms   = wc_get_product_visibility_term_ids();
		$exclude = [ (int) ( $terms['exclude-from-search'] ?? 0 ) ];

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$exclude[] = (int) ( $terms['outofstock'] ?? 0 );
		}

		return array_values( array_filter( $exclude ) );
	}
}
