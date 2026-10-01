<?php
/**
 * Shared controls and markup for the RDSCO search widgets.
 */

namespace RDSCO\WEW\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use RDSCO\WEW\Plugin;
use RDSCO\WEW\Search_API;

defined( 'ABSPATH' ) || exit;

trait Search_Widget {

	public function get_categories(): array {
		return [ Plugin::WIDGET_GROUP ];
	}

	public function get_script_depends(): array {
		return [ Plugin::ASSET_HANDLE ];
	}

	public function get_style_depends(): array {
		return [ Plugin::ASSET_HANDLE ];
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Product categories for SELECT2 controls, labelled "Parent › Child".
	 *
	 * @return array<string, string>
	 */
	protected static function category_options(): array {
		static $options = null;
		if ( null !== $options ) {
			return $options;
		}

		$options = [];
		$terms   = get_terms(
			[
				'taxonomy'               => 'product_cat',
				'hide_empty'             => false,
				'update_term_meta_cache' => false,
			]
		);
		if ( ! is_array( $terms ) ) {
			return $options;
		}

		$by_id = [];
		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		foreach ( $terms as $term ) {
			$label  = $term->name;
			$parent = (int) $term->parent;
			$depth  = 0;
			while ( $parent && isset( $by_id[ $parent ] ) && $depth++ < 10 ) {
				$label  = $by_id[ $parent ]->name . ' › ' . $label;
				$parent = (int) $by_id[ $parent ]->parent;
			}
			$options[ (string) $term->term_id ] = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' );
		}

		natcasesort( $options );

		return $options;
	}

	/**
	 * @param mixed $value
	 * @return int[]
	 */
	protected static function ids( $value ): array {
		return Search_API::parse_ids( is_array( $value ) ? $value : (string) $value );
	}

	/**
	 * @param int[] $ids
	 * @return string[]
	 */
	protected static function term_slugs( array $ids ): array {
		if ( ! $ids ) {
			return [];
		}

		$slugs = get_terms(
			[
				'taxonomy'               => 'product_cat',
				'include'                => $ids,
				'hide_empty'             => false,
				'fields'                 => 'slugs',
				'update_term_meta_cache' => false,
			]
		);

		return is_array( $slugs ) ? array_values( array_map( 'strval', $slugs ) ) : [];
	}

	protected static function icon( string $name, string $class_name ): string {
		$paths = [
			'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'close'  => '<path d="M18 6 6 18M6 6l12 12"/>',
		];

		return '<svg class="' . esc_attr( $class_name ) . '" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. ( $paths[ $name ] ?? '' )
			. '</svg>';
	}

	/* ---------------------------------------------------------------------
	 * Content controls
	 * ------------------------------------------------------------------ */

	protected function add_results_controls(): void {
		$this->start_controls_section(
			'section_results',
			[
				'label' => esc_html__( 'Results', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'limit',
			[
				'label'   => esc_html__( 'Number of results', 'rdsco-woocommerce-elementor-widgets' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => Search_API::MAX_LIMIT,
				'step'    => 1,
				'default' => 8,
			]
		);

		$this->add_control(
			'group_by_category',
			[
				'label'        => esc_html__( 'Group by category', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->add_control(
			'search_description',
			[
				'label'        => esc_html__( 'Search in description', 'rdsco-woocommerce-elementor-widgets' ),
				'description'  => esc_html__( 'Name and SKU are always searched. Turn this off for the fastest search.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'show_image',
			[
				'label'        => esc_html__( 'Show image', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'show_price',
			[
				'label'        => esc_html__( 'Show price', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'show_sku',
			[
				'label'        => esc_html__( 'Show SKU', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'show_view_all',
			[
				'label'        => esc_html__( '"View all results" link', 'rdsco-woocommerce-elementor-widgets' ),
				'description'  => esc_html__( 'Shown when there may be more results than listed.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'no_results_text',
			[
				'label'       => esc_html__( 'No results text', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'No products found.', 'rdsco-woocommerce-elementor-widgets' ),
				'label_block' => true,
				'separator'   => 'before',
			]
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Style controls
	 * ------------------------------------------------------------------ */

	/**
	 * Style section for an input-like box (search field or popup trigger).
	 *
	 * @param array{box:string, active:string, placeholder:string, icon:string} $sel
	 */
	protected function add_box_style_section( string $id, string $label, array $sel, string $active_label ): void {
		$this->start_controls_section(
			$id,
			[
				'label' => $label,
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			$id . '_height',
			[
				'label'      => esc_html__( 'Height', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 28,
						'max' => 90,
					],
				],
				'selectors'  => [ $sel['box'] => 'height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => $id . '_typography',
				'selector' => $sel['box'],
			]
		);

		$this->start_controls_tabs( $id . '_tabs' );

		$this->start_controls_tab( $id . '_tab_normal', [ 'label' => esc_html__( 'Normal', 'rdsco-woocommerce-elementor-widgets' ) ] );

		$this->add_control(
			$id . '_color',
			[
				'label'     => esc_html__( 'Text color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ $sel['box'] => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			$id . '_placeholder_color',
			[
				'label'     => esc_html__( 'Placeholder color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ $sel['placeholder'] => 'color: {{VALUE}}; opacity: 1;' ],
			]
		);

		$this->add_control(
			$id . '_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ $sel['box'] => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			$id . '_icon_color',
			[
				'label'     => esc_html__( 'Icon color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ $sel['icon'] => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( $id . '_tab_active', [ 'label' => $active_label ] );

		$this->add_control(
			$id . '_active_border',
			[
				'label'     => esc_html__( 'Border color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ $sel['active'] => 'border-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			$id . '_active_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ $sel['active'] => 'background-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'      => $id . '_border',
				'selector'  => $sel['box'],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			$id . '_radius',
			[
				'label'      => esc_html__( 'Border radius', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [ $sel['box'] => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			$id . '_icon_size',
			[
				'label'      => esc_html__( 'Icon size', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 10,
						'max' => 40,
					],
				],
				'selectors'  => [ $sel['icon'] => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	protected function add_results_style_section(): void {
		$this->start_controls_section(
			'style_results',
			[
				'label' => esc_html__( 'Results', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'results_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-search .rdsco-search__panel' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'results_border',
				'selector' => '{{WRAPPER}} .rdsco-search .rdsco-search__panel',
			]
		);

		$this->add_responsive_control(
			'results_radius',
			[
				'label'      => esc_html__( 'Border radius', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors'  => [ '{{WRAPPER}} .rdsco-search .rdsco-search__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'results_shadow',
				'selector' => '{{WRAPPER}} .rdsco-search .rdsco-search__panel',
			]
		);

		$this->add_control(
			'results_hover_bg',
			[
				'label'     => esc_html__( 'Item hover background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .rdsco-search a.rdsco-search__link:hover, {{WRAPPER}} .rdsco-search .rdsco-search__item.is-active a.rdsco-search__link' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'results_thumb_size',
			[
				'label'      => esc_html__( 'Image size', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 24,
						'max' => 120,
					],
				],
				'selectors'  => [ '{{WRAPPER}} .rdsco-search .rdsco-search__thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'results_title_heading',
			[
				'label'     => esc_html__( 'Product name', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'results_title_color',
			[
				'label'     => esc_html__( 'Color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-search .rdsco-search__title' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'results_title_typography',
				'selector' => '{{WRAPPER}} .rdsco-search .rdsco-search__title',
			]
		);

		$this->add_control(
			'results_price_color',
			[
				'label'     => esc_html__( 'Price color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-search .rdsco-search__price' => 'color: {{VALUE}};' ],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'results_meta_color',
			[
				'label'     => esc_html__( 'SKU color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-search .rdsco-search__meta' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'results_group_color',
			[
				'label'     => esc_html__( 'Category heading color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-search .rdsco-search__group-label' => 'color: {{VALUE}};' ],
				'condition' => [ 'group_by_category' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Markup
	 * ------------------------------------------------------------------ */

	/**
	 * Search form shared by both widgets.
	 *
	 * @param array{mode:string, placeholder:string, cats:int[], slugs:string[], icon:bool, autofocus:bool} $args
	 * @param array<string, mixed> $settings
	 */
	protected function render_search_box( array $args, array $settings ): void {
		$uid   = 'rdsco-' . $this->get_id() . '-' . wp_unique_id();
		$label = __( 'Search products', 'rdsco-woocommerce-elementor-widgets' );

		$options = [
			'limit'     => max( 1, min( Search_API::MAX_LIMIT, absint( $settings['limit'] ?? 8 ) ?: 8 ) ),
			'cats'      => $args['cats'],
			'slugs'     => $args['slugs'],
			'content'   => 'yes' === ( $settings['search_description'] ?? 'yes' ),
			'group'     => 'yes' === ( $settings['group_by_category'] ?? '' ),
			'image'     => 'yes' === ( $settings['show_image'] ?? 'yes' ),
			'price'     => 'yes' === ( $settings['show_price'] ?? 'yes' ),
			'sku'       => 'yes' === ( $settings['show_sku'] ?? 'yes' ),
			'viewAll'   => 'yes' === ( $settings['show_view_all'] ?? 'yes' ),
			'noResults' => sanitize_text_field( (string) ( $settings['no_results_text'] ?? '' ) ),
		];
		?>
		<div class="rdsco-search rdsco-search--<?php echo esc_attr( $args['mode'] ); ?>" data-rdsco-search="<?php echo esc_attr( (string) wp_json_encode( $options ) ); ?>">
			<form class="rdsco-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="rdsco-search__sr" for="<?php echo esc_attr( $uid ); ?>-input"><?php echo esc_html( $label ); ?></label>
				<div class="rdsco-search__field<?php echo $args['icon'] ? ' has-icon' : ''; ?>">
					<?php
					if ( $args['icon'] ) {
						echo self::icon( 'search', 'rdsco-search__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
					}
					?>
					<input
						id="<?php echo esc_attr( $uid ); ?>-input"
						class="rdsco-search__input"
						type="search"
						name="s"
						placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
						maxlength="<?php echo esc_attr( (string) Search_API::MAX_CHARS ); ?>"
						autocomplete="off"
						autocapitalize="off"
						spellcheck="false"
						enterkeyhint="search"
						role="combobox"
						aria-autocomplete="list"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $uid ); ?>-list"
						<?php echo $args['autofocus'] ? 'autofocus' : ''; ?>
					>
					<input type="hidden" name="post_type" value="product">
					<input type="hidden" class="rdsco-search__cat-field" name="product_cat" value="<?php echo esc_attr( implode( ',', $args['slugs'] ) ); ?>"<?php disabled( empty( $args['slugs'] ) ); ?>>
				</div>
				<div class="rdsco-search__panel" hidden>
					<ul id="<?php echo esc_attr( $uid ); ?>-list" class="rdsco-search__list" role="listbox" aria-label="<?php echo esc_attr( $label ); ?>"></ul>
					<p class="rdsco-search__message" hidden></p>
				</div>
				<div class="rdsco-search__sr rdsco-search__status" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
	}
}
