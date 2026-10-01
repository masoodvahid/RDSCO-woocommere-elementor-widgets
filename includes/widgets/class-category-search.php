<?php
/**
 * Elementor widget: a search box that opens a popup where the visitor picks a
 * product category (or all products) and searches inside it.
 */

namespace RDSCO\WEW\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

final class Category_Search extends Widget_Base {

	use Search_Widget;

	public function get_name(): string {
		return 'rdsco-category-search';
	}

	public function get_title(): string {
		return esc_html__( 'Category Search Popup', 'rdsco-woocommerce-elementor-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-product-categories';
	}

	public function get_keywords(): array {
		return [ 'search', 'category', 'popup', 'modal', 'product', 'sku', 'woocommerce', 'rdsco' ];
	}

	protected function register_controls(): void {
		$this->register_trigger_controls();
		$this->register_popup_controls();
		$this->add_results_controls();

		$this->add_box_style_section(
			'style_trigger',
			esc_html__( 'Search box (opens popup)', 'rdsco-woocommerce-elementor-widgets' ),
			[
				'box'         => '{{WRAPPER}} .rdsco-csearch button.rdsco-csearch__trigger',
				'active'      => '{{WRAPPER}} .rdsco-csearch button.rdsco-csearch__trigger:hover, {{WRAPPER}} .rdsco-csearch button.rdsco-csearch__trigger:focus-visible',
				'placeholder' => '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__trigger-text',
				'icon'        => '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__icon',
			],
			esc_html__( 'Hover', 'rdsco-woocommerce-elementor-widgets' )
		);

		$this->register_popup_style_controls();

		$this->add_box_style_section(
			'style_input',
			esc_html__( 'Popup search field', 'rdsco-woocommerce-elementor-widgets' ),
			[
				'box'         => '{{WRAPPER}} .rdsco-search .rdsco-search__field input.rdsco-search__input',
				'active'      => '{{WRAPPER}} .rdsco-search .rdsco-search__field input.rdsco-search__input:focus',
				'placeholder' => '{{WRAPPER}} .rdsco-search .rdsco-search__field input.rdsco-search__input::placeholder',
				'icon'        => '{{WRAPPER}} .rdsco-search .rdsco-search__icon',
			],
			esc_html__( 'Focus', 'rdsco-woocommerce-elementor-widgets' )
		);

		$this->add_results_style_section();
	}

	private function register_trigger_controls(): void {
		$this->start_controls_section(
			'section_trigger',
			[
				'label' => esc_html__( 'Search box', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'trigger_style',
			[
				'label'   => esc_html__( 'Display', 'rdsco-woocommerce-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'box',
				'options' => [
					'box'  => esc_html__( 'Search box', 'rdsco-woocommerce-elementor-widgets' ),
					'icon' => esc_html__( 'Icon only', 'rdsco-woocommerce-elementor-widgets' ),
				],
			]
		);

		$this->add_control(
			'trigger_text',
			[
				'label'       => esc_html__( 'Text', 'rdsco-woocommerce-elementor-widgets' ),
				'description' => esc_html__( 'Shown in the box, or read by screen readers for the icon.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Search products…', 'rdsco-woocommerce-elementor-widgets' ),
				'label_block' => true,
			]
		);

		$this->end_controls_section();
	}

	private function register_popup_controls(): void {
		$this->start_controls_section(
			'section_popup',
			[
				'label' => esc_html__( 'Popup', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'popup_title',
			[
				'label'       => esc_html__( 'Title', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Search products', 'rdsco-woocommerce-elementor-widgets' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'placeholder',
			[
				'label'       => esc_html__( 'Search field placeholder', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Product name or SKU…', 'rdsco-woocommerce-elementor-widgets' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'categories_source',
			[
				'label'     => esc_html__( 'Categories to offer', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'top',
				'options'   => [
					'top'      => esc_html__( 'Top-level categories', 'rdsco-woocommerce-elementor-widgets' ),
					'all'      => esc_html__( 'All categories', 'rdsco-woocommerce-elementor-widgets' ),
					'selected' => esc_html__( 'Selected categories', 'rdsco-woocommerce-elementor-widgets' ),
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'categories',
			[
				'label'       => esc_html__( 'Categories', 'rdsco-woocommerce-elementor-widgets' ),
				'description' => esc_html__( 'Shown in this order. Searching a category includes its sub-categories.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::category_options(),
				'condition'   => [ 'categories_source' => 'selected' ],
			]
		);

		$this->add_control(
			'hide_empty',
			[
				'label'        => esc_html__( 'Hide empty categories', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'picker_style',
			[
				'label'   => esc_html__( 'Category picker', 'rdsco-woocommerce-elementor-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'buttons',
				'options' => [
					'buttons'  => esc_html__( 'Buttons', 'rdsco-woocommerce-elementor-widgets' ),
					'dropdown' => esc_html__( 'Dropdown', 'rdsco-woocommerce-elementor-widgets' ),
				],
			]
		);

		$this->add_control(
			'all_label',
			[
				'label'   => esc_html__( '"All products" label', 'rdsco-woocommerce-elementor-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'All products', 'rdsco-woocommerce-elementor-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function register_popup_style_controls(): void {
		$this->start_controls_section(
			'style_popup',
			[
				'label' => esc_html__( 'Popup', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'popup_width',
			[
				'label'      => esc_html__( 'Max width', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'vw' ],
				'range'      => [
					'px' => [
						'min' => 320,
						'max' => 1200,
					],
					'vw' => [
						'min' => 30,
						'max' => 100,
					],
				],
				'selectors'  => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__dialog' => 'max-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'popup_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__dialog' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'popup_overlay',
			[
				'label'     => esc_html__( 'Overlay color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__dialog::backdrop' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'popup_radius',
			[
				'label'      => esc_html__( 'Border radius', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors'  => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__dialog' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'popup_title_color',
			[
				'label'     => esc_html__( 'Title color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__title' => 'color: {{VALUE}};' ],
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'popup_title_typography',
				'selector' => '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__title',
			]
		);

		$this->add_control(
			'cats_heading',
			[
				'label'     => esc_html__( 'Category buttons', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'cats_typography',
				'selector' => '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat span, {{WRAPPER}} .rdsco-csearch .rdsco-csearch__select',
			]
		);

		$this->start_controls_tabs( 'cats_tabs' );

		$this->start_controls_tab( 'cats_tab_normal', [ 'label' => esc_html__( 'Normal', 'rdsco-woocommerce-elementor-widgets' ) ] );

		$this->add_control(
			'cats_color',
			[
				'label'     => esc_html__( 'Text color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat span' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'cats_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat span' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'cats_border',
			[
				'label'     => esc_html__( 'Border color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat span' => 'border-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'cats_tab_active', [ 'label' => esc_html__( 'Selected', 'rdsco-woocommerce-elementor-widgets' ) ] );

		$this->add_control(
			'cats_active_color',
			[
				'label'     => esc_html__( 'Text color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat input:checked + span' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'cats_active_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat input:checked + span' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			'cats_radius',
			[
				'label'      => esc_html__( 'Border radius', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 40,
					],
				],
				'selectors'  => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__cat span, {{WRAPPER}} .rdsco-csearch .rdsco-csearch__select' => 'border-radius: {{SIZE}}{{UNIT}};' ],
				'separator'  => 'before',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Categories offered in the popup, in display order.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<int, array{id:int, slug:string, name:string, depth:int}>
	 */
	private static function popup_categories( array $settings ): array {
		$source = (string) ( $settings['categories_source'] ?? 'top' );
		$query  = [
			'taxonomy'               => 'product_cat',
			'hide_empty'             => 'yes' === ( $settings['hide_empty'] ?? 'yes' ),
			'update_term_meta_cache' => false,
		];

		if ( 'selected' === $source ) {
			$ids = self::ids( $settings['categories'] ?? [] );
			if ( ! $ids ) {
				return [];
			}
			$query['include'] = $ids;
			$query['orderby'] = 'include';
		} else {
			// Store order (WooCommerce sorts product_cat by its own menu order by default).
			$query['exclude'] = array_filter( [ (int) get_option( 'default_product_cat' ) ] );
			if ( 'top' === $source ) {
				$query['parent'] = 0;
			}
		}

		$terms = get_terms( $query );
		if ( ! is_array( $terms ) || ! $terms ) {
			return [];
		}

		$items = [];
		$add   = static function ( $term, int $depth ) use ( &$items ): void {
			$items[] = [
				'id'    => (int) $term->term_id,
				'slug'  => (string) $term->slug,
				'name'  => html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' ),
				'depth' => $depth,
			];
		};

		if ( 'all' !== $source ) {
			foreach ( $terms as $term ) {
				$add( $term, 0 );
			}
			return $items;
		}

		// "All": parent → children order, with depth for indentation.
		$present  = [];
		$children = [];
		foreach ( $terms as $term ) {
			$present[ (int) $term->term_id ] = true;
		}
		foreach ( $terms as $term ) {
			$parent                = isset( $present[ (int) $term->parent ] ) ? (int) $term->parent : 0;
			$children[ $parent ][] = $term;
		}

		$walk = static function ( int $parent, int $depth ) use ( &$walk, &$children, $add ): void {
			foreach ( $children[ $parent ] ?? [] as $term ) {
				$add( $term, $depth );
				if ( $depth < 5 ) {
					$walk( (int) $term->term_id, $depth + 1 );
				}
			}
		};
		$walk( 0, 0 );

		return $items;
	}

	/**
	 * @param array<int, array{id:int, slug:string, name:string, depth:int}> $cats
	 * @param array<string, mixed> $settings
	 */
	private function render_picker( array $cats, array $settings, string $uid ): void {
		$all   = (string) ( $settings['all_label'] ?? '' );
		$all   = '' !== $all ? $all : __( 'All products', 'rdsco-woocommerce-elementor-widgets' );
		$label = __( 'Category', 'rdsco-woocommerce-elementor-widgets' );

		if ( 'dropdown' === ( $settings['picker_style'] ?? 'buttons' ) ) {
			?>
			<div class="rdsco-csearch__cats rdsco-csearch__cats--dropdown">
				<label class="rdsco-search__sr" for="<?php echo esc_attr( $uid ); ?>-cat"><?php echo esc_html( $label ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-cat" class="rdsco-csearch__select">
					<option value="" data-slug=""><?php echo esc_html( $all ); ?></option>
					<?php foreach ( $cats as $cat ) : ?>
						<option value="<?php echo esc_attr( (string) $cat['id'] ); ?>" data-slug="<?php echo esc_attr( $cat['slug'] ); ?>"><?php echo esc_html( str_repeat( '— ', $cat['depth'] ) . $cat['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
			return;
		}
		?>
		<fieldset class="rdsco-csearch__cats rdsco-csearch__cats--buttons">
			<legend class="rdsco-search__sr"><?php echo esc_html( $label ); ?></legend>
			<label class="rdsco-csearch__cat">
				<input type="radio" name="<?php echo esc_attr( $uid ); ?>-cat" value="" data-slug="" checked>
				<span><?php echo esc_html( $all ); ?></span>
			</label>
			<?php foreach ( $cats as $cat ) : ?>
				<label class="rdsco-csearch__cat">
					<input type="radio" name="<?php echo esc_attr( $uid ); ?>-cat" value="<?php echo esc_attr( (string) $cat['id'] ); ?>" data-slug="<?php echo esc_attr( $cat['slug'] ); ?>">
					<span><?php echo esc_html( $cat['name'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$uid      = 'rdsco-cs-' . $this->get_id() . '-' . wp_unique_id();
		$text     = (string) ( $settings['trigger_text'] ?? '' );
		$title    = (string) ( $settings['popup_title'] ?? '' );
		$icon     = 'icon' === ( $settings['trigger_style'] ?? 'box' );
		$cats     = self::popup_categories( $settings );
		?>
		<div class="rdsco-csearch">
			<button
				type="button"
				class="rdsco-csearch__trigger rdsco-csearch__trigger--<?php echo $icon ? 'icon' : 'box'; ?>"
				aria-haspopup="dialog"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $uid ); ?>-dialog"
				<?php if ( $icon ) : ?>
					aria-label="<?php echo esc_attr( '' !== $text ? $text : __( 'Search products', 'rdsco-woocommerce-elementor-widgets' ) ); ?>"
				<?php endif; ?>
			>
				<?php echo self::icon( 'search', 'rdsco-csearch__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
				<?php if ( ! $icon ) : ?>
					<span class="rdsco-csearch__trigger-text"><?php echo esc_html( $text ); ?></span>
				<?php endif; ?>
			</button>

			<dialog id="<?php echo esc_attr( $uid ); ?>-dialog" class="rdsco-csearch__dialog" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
				<div class="rdsco-csearch__inner">
					<div class="rdsco-csearch__head">
						<div id="<?php echo esc_attr( $uid ); ?>-title" class="rdsco-csearch__title"><?php echo esc_html( '' !== $title ? $title : __( 'Search products', 'rdsco-woocommerce-elementor-widgets' ) ); ?></div>
						<button type="button" class="rdsco-csearch__close" aria-label="<?php esc_attr_e( 'Close', 'rdsco-woocommerce-elementor-widgets' ); ?>">
							<?php echo self::icon( 'close', 'rdsco-csearch__close-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
						</button>
					</div>
					<?php
					if ( $cats ) {
						$this->render_picker( $cats, $settings, $uid );
					}

					$this->render_search_box(
						[
							'mode'        => 'inline',
							'placeholder' => (string) ( $settings['placeholder'] ?? '' ),
							'cats'        => [],
							'slugs'       => [],
							'icon'        => true,
							'autofocus'   => true,
						],
						$settings
					);
					?>
				</div>
			</dialog>
		</div>
		<?php
	}
}
