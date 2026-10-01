<?php
/**
 * Elementor widget: search bar with live product results (name, SKU, description).
 */

namespace RDSCO\WEW\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

final class Live_Search extends Widget_Base {

	use Search_Widget;

	public function get_name(): string {
		return 'rdsco-live-search';
	}

	public function get_title(): string {
		return esc_html__( 'Live Product Search', 'rdsco-woocommerce-elementor-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-search';
	}

	public function get_keywords(): array {
		return [ 'search', 'product', 'sku', 'live', 'ajax', 'woocommerce', 'rdsco' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_search',
			[
				'label' => esc_html__( 'Search', 'rdsco-woocommerce-elementor-widgets' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'placeholder',
			[
				'label'       => esc_html__( 'Placeholder', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Search by product name or SKU…', 'rdsco-woocommerce-elementor-widgets' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'show_icon',
			[
				'label'        => esc_html__( 'Search icon', 'rdsco-woocommerce-elementor-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'scope',
			[
				'label'     => esc_html__( 'Search in', 'rdsco-woocommerce-elementor-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'all',
				'options'   => [
					'all'        => esc_html__( 'All products', 'rdsco-woocommerce-elementor-widgets' ),
					'categories' => esc_html__( 'Selected categories', 'rdsco-woocommerce-elementor-widgets' ),
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'categories',
			[
				'label'       => esc_html__( 'Categories', 'rdsco-woocommerce-elementor-widgets' ),
				'description' => esc_html__( 'Sub-categories are included automatically.', 'rdsco-woocommerce-elementor-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::category_options(),
				'condition'   => [ 'scope' => 'categories' ],
			]
		);

		$this->end_controls_section();

		$this->add_results_controls();

		$this->add_box_style_section(
			'style_input',
			esc_html__( 'Search box', 'rdsco-woocommerce-elementor-widgets' ),
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

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$cats     = 'categories' === ( $settings['scope'] ?? 'all' ) ? self::ids( $settings['categories'] ?? [] ) : [];

		$this->render_search_box(
			[
				'mode'        => 'dropdown',
				'placeholder' => (string) ( $settings['placeholder'] ?? '' ),
				'cats'        => $cats,
				'slugs'       => self::term_slugs( $cats ),
				'icon'        => 'yes' === ( $settings['show_icon'] ?? 'yes' ),
				'autofocus'   => false,
			],
			$settings
		);
	}
}
