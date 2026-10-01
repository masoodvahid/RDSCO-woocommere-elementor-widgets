<?php
/**
 * Elementor widget: a search box that opens a popup with a category tree
 * (checkboxes) beside the search field. The visitor ticks any categories, or
 * none to search all products.
 */

namespace RDSCO\WEW\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

final class Category_Search extends Widget_Base {

	use Search_Widget;

	private const MAX_DEPTH = 5;

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
		return [ 'search', 'category', 'popup', 'modal', 'tree', 'product', 'sku', 'woocommerce', 'rdsco' ];
	}

	protected function register_controls(): void {
		$this->register_trigger_controls();
		$this->register_popup_controls();
		$this->register_tree_controls();
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
		$this->register_tree_style_controls();

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

	/* ---------------------------------------------------------------------
	 * Content controls
	 * ------------------------------------------------------------------ */

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

		$this->end_controls_section();
	}

	private function register_tree_controls(): void {
		$this->start_controls_section(
			'section_tree',
			[
				'label' => esc_html__( 'Category tree', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_tree',
			[
				'label'        => esc_html__( 'Show category tree', 'rdsco-woocommerce-elementor-widgets' ),
				'description'  => esc_html__( 'Visitors tick any categories; nothing ticked searches all products.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'tree_position',
			[
				'label'     => esc_html__( 'Position', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'start',
				'options'   => [
					'start' => esc_html__( 'Beside the search box (before)', 'rdsco-woocommerce-elementor-widgets' ),
					'end'   => esc_html__( 'Beside the search box (after)', 'rdsco-woocommerce-elementor-widgets' ),
					'top'   => esc_html__( 'Above the search box', 'rdsco-woocommerce-elementor-widgets' ),
				],
				'description' => esc_html__( 'On phones the tree is always shown above the search box.', 'rdsco-woocommerce-elementor-widgets' ),
				'condition' => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_control(
			'tree_title',
			[
				'label'     => esc_html__( 'Tree title', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Categories', 'rdsco-woocommerce-elementor-widgets' ),
				'condition' => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_control(
			'categories_source',
			[
				'label'     => esc_html__( 'Categories to show', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'all',
				'options'   => [
					'all'      => esc_html__( 'All categories', 'rdsco-woocommerce-elementor-widgets' ),
					'selected' => esc_html__( 'Selected categories (with their sub-categories)', 'rdsco-woocommerce-elementor-widgets' ),
				],
				'separator' => 'before',
				'condition' => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_control(
			'categories',
			[
				'label'       => esc_html__( 'Categories', 'rdsco-woocommerce-elementor-widgets' ),
				'description' => esc_html__( 'Shown in this order.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::category_options(),
				'condition'   => [
					'show_tree'         => 'yes',
					'categories_source' => 'selected',
				],
			]
		);

		$this->add_control(
			'tree_depth',
			[
				'label'       => esc_html__( 'Levels shown', 'rdsco-woocommerce-elementor-widgets' ),
				'description' => esc_html__( 'Ticking a category always includes all of its sub-categories, shown or not.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => self::MAX_DEPTH,
				'step'        => 1,
				'default'     => 3,
				'condition'   => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_control(
			'tree_expanded',
			[
				'label'        => esc_html__( 'Expand sub-categories', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_control(
			'hide_empty',
			[
				'label'        => esc_html__( 'Hide empty categories', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_control(
			'clear_label',
			[
				'label'     => esc_html__( '"Clear" label', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Clear', 'rdsco-woocommerce-elementor-widgets' ),
				'condition' => [ 'show_tree' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Style controls
	 * ------------------------------------------------------------------ */

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
						'max' => 1400,
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

		$this->end_controls_section();
	}

	private function register_tree_style_controls(): void {
		$this->start_controls_section(
			'style_tree',
			[
				'label'     => esc_html__( 'Category tree', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_tree' => 'yes' ],
			]
		);

		$this->add_responsive_control(
			'tree_width',
			[
				'label'      => esc_html__( 'Width (beside the search box)', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [
						'min' => 160,
						'max' => 480,
					],
					'%'  => [
						'min' => 15,
						'max' => 50,
					],
				],
				'selectors'  => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__layout' => '--rdsco-tree-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'tree_height',
			[
				'label'      => esc_html__( 'Max height', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'vh' ],
				'range'      => [
					'px' => [
						'min' => 120,
						'max' => 900,
					],
					'vh' => [
						'min' => 15,
						'max' => 90,
					],
				],
				'selectors'  => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__tree-wrap' => 'max-height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'tree_bg',
			[
				'label'     => esc_html__( 'Background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__tree-wrap' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'tree_divider',
			[
				'label'     => esc_html__( 'Divider color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__tree-wrap' => 'border-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'tree_heading_color',
			[
				'label'     => esc_html__( 'Title color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__tree-title' => 'color: {{VALUE}};' ],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tree_text_color',
			[
				'label'     => esc_html__( 'Category color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__check' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'tree_typography',
				'selector' => '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__check',
			]
		);

		$this->add_control(
			'tree_accent',
			[
				'label'     => esc_html__( 'Checkbox color', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__check input' => 'accent-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'tree_hover_bg',
			[
				'label'     => esc_html__( 'Row hover background', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__check:hover' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'tree_row_gap',
			[
				'label'      => esc_html__( 'Row spacing', 'rdsco-woocommerce-elementor-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 16,
					],
				],
				'selectors'  => [ '{{WRAPPER}} .rdsco-csearch .rdsco-csearch__check' => 'padding-block: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------ */

	/**
	 * Category tree for the popup, in store order.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<int, array{id:int, slug:string, name:string, children:array}>
	 */
	private static function category_tree( array $settings ): array {
		// One query for every category; WordPress caches it. With hide_empty,
		// core keeps empty parents whose sub-categories have products.
		$terms = get_terms(
			[
				'taxonomy'               => 'product_cat',
				'hide_empty'             => 'yes' === ( $settings['hide_empty'] ?? 'yes' ),
				'update_term_meta_cache' => false,
			]
		);
		if ( ! is_array( $terms ) || ! $terms ) {
			return [];
		}

		$uncategorized = (int) get_option( 'default_product_cat' );
		$by_id         = [];
		$children      = [];

		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}
		foreach ( $terms as $term ) {
			$id = (int) $term->term_id;
			if ( $id === $uncategorized ) {
				continue;
			}
			// A parent missing from the list (e.g. hidden) promotes the child to a root.
			$parent                = isset( $by_id[ (int) $term->parent ] ) ? (int) $term->parent : 0;
			$children[ $parent ][] = $id;
		}

		$roots = $children[0] ?? [];

		if ( 'selected' === ( $settings['categories_source'] ?? 'all' ) ) {
			// Keep the editor's order (no sorting here).
			$picked = array_values(
				array_unique(
					array_filter(
						array_map( 'absint', (array) ( $settings['categories'] ?? [] ) ),
						static fn( int $id ): bool => isset( $by_id[ $id ] ) && $id !== $uncategorized
					)
				)
			);
			$lookup = array_flip( $picked );

			// Skip a picked category that already sits under another picked one.
			$roots = array_values(
				array_filter(
					$picked,
					static function ( int $id ) use ( $by_id, $lookup ): bool {
						$guard  = 0;
						$parent = (int) $by_id[ $id ]->parent;
						while ( $parent && isset( $by_id[ $parent ] ) && $guard++ < 20 ) {
							if ( isset( $lookup[ $parent ] ) ) {
								return false;
							}
							$parent = (int) $by_id[ $parent ]->parent;
						}
						return true;
					}
				)
			);
		}

		$max_depth = max( 1, min( self::MAX_DEPTH, absint( $settings['tree_depth'] ?? 3 ) ?: 3 ) );

		$build = static function ( array $ids, int $depth ) use ( &$build, $by_id, $children, $max_depth ): array {
			$nodes = [];
			foreach ( $ids as $id ) {
				$term    = $by_id[ $id ];
				$nodes[] = [
					'id'       => $id,
					'slug'     => (string) $term->slug,
					'name'     => html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' ),
					'children' => $depth < $max_depth ? $build( $children[ $id ] ?? [], $depth + 1 ) : [],
				];
			}
			return $nodes;
		};

		return $build( $roots, 1 );
	}

	/* ---------------------------------------------------------------------
	 * Markup
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<int, array{id:int, slug:string, name:string, children:array}> $nodes
	 */
	private function render_nodes( array $nodes, string $uid, bool $expanded ): void {
		foreach ( $nodes as $node ) {
			$has_children = ! empty( $node['children'] );
			$list_id      = $uid . '-n-' . $node['id'];
			?>
			<li class="rdsco-csearch__node">
				<div class="rdsco-csearch__row">
					<?php if ( $has_children ) : ?>
						<button
							type="button"
							class="rdsco-csearch__toggle"
							aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $list_id ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: category name. */ __( 'Sub-categories of %s', 'rdsco-woocommerce-elementor-widgets' ), $node['name'] ) ); ?>"
						><?php echo self::icon( 'chevron', 'rdsco-csearch__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></button>
					<?php else : ?>
						<span class="rdsco-csearch__spacer" aria-hidden="true"></span>
					<?php endif; ?>
					<label class="rdsco-csearch__check">
						<input type="checkbox" value="<?php echo esc_attr( (string) $node['id'] ); ?>" data-slug="<?php echo esc_attr( $node['slug'] ); ?>">
						<span><?php echo esc_html( $node['name'] ); ?></span>
					</label>
				</div>
				<?php if ( $has_children ) : ?>
					<ul id="<?php echo esc_attr( $list_id ); ?>" class="rdsco-csearch__children"<?php echo $expanded ? '' : ' hidden'; ?>>
						<?php $this->render_nodes( $node['children'], $uid, $expanded ); ?>
					</ul>
				<?php endif; ?>
			</li>
			<?php
		}
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$uid      = 'rdsco-cs-' . $this->get_id() . '-' . wp_unique_id();
		$text     = (string) ( $settings['trigger_text'] ?? '' );
		$title    = (string) ( $settings['popup_title'] ?? '' );
		$icon     = 'icon' === ( $settings['trigger_style'] ?? 'box' );
		$tree     = 'yes' === ( $settings['show_tree'] ?? 'yes' ) ? self::category_tree( $settings ) : [];
		$position = in_array( $settings['tree_position'] ?? 'start', [ 'start', 'end', 'top' ], true ) ? $settings['tree_position'] : 'start';
		$layout   = $tree ? 'has-tree tree-' . $position : 'no-tree';
		$tree_ttl = (string) ( $settings['tree_title'] ?? '' );
		$clear    = (string) ( $settings['clear_label'] ?? '' );
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

			<dialog id="<?php echo esc_attr( $uid ); ?>-dialog" class="rdsco-csearch__dialog <?php echo esc_attr( $layout ); ?>" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
				<div class="rdsco-csearch__inner">
					<div class="rdsco-csearch__head">
						<div id="<?php echo esc_attr( $uid ); ?>-title" class="rdsco-csearch__title"><?php echo esc_html( '' !== $title ? $title : __( 'Search products', 'rdsco-woocommerce-elementor-widgets' ) ); ?></div>
						<button type="button" class="rdsco-csearch__close" aria-label="<?php esc_attr_e( 'Close', 'rdsco-woocommerce-elementor-widgets' ); ?>">
							<?php echo self::icon( 'close', 'rdsco-csearch__close-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
						</button>
					</div>

					<div class="rdsco-csearch__layout <?php echo esc_attr( $layout ); ?>">
						<?php if ( $tree ) : ?>
							<div class="rdsco-csearch__tree-wrap">
								<div class="rdsco-csearch__tree-head">
									<span id="<?php echo esc_attr( $uid ); ?>-tree" class="rdsco-csearch__tree-title"><?php echo esc_html( '' !== $tree_ttl ? $tree_ttl : __( 'Categories', 'rdsco-woocommerce-elementor-widgets' ) ); ?></span>
									<button type="button" class="rdsco-csearch__clear" hidden><?php echo esc_html( '' !== $clear ? $clear : __( 'Clear', 'rdsco-woocommerce-elementor-widgets' ) ); ?></button>
								</div>
								<ul class="rdsco-csearch__tree" aria-labelledby="<?php echo esc_attr( $uid ); ?>-tree">
									<?php $this->render_nodes( $tree, $uid, 'yes' === ( $settings['tree_expanded'] ?? '' ) ); ?>
								</ul>
							</div>
						<?php endif; ?>

						<div class="rdsco-csearch__main">
							<?php
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
					</div>
				</div>
			</dialog>
		</div>
		<?php
	}
}
