<?php
/**
 * WCFMu plugin view
 *
 * Marketplace WC Vendors Support
 *
 * @author 		WC Lovers
 * @package 	wcfm/views
 * @version   1.0.1
 */
 
global $WCFM, $wpdb, $start_date, $end_date;

$user_id = get_current_user_id();

// Get products using a query - this is too advanced for get_posts :(
$stock          = absint( max( get_option( 'woocommerce_notify_low_stock_amount' ), 1 ) );
$nostock        = absint( max( get_option( 'woocommerce_notify_no_stock_amount' ), 0 ) );

$query_from = apply_filters( 'wcfm_report_low_in_stock_query_from', "FROM {$wpdb->posts} as posts
	INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
	INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
	WHERE 1=1
	AND posts.post_type IN ( 'product', 'product_variation' )
	AND posts.post_status = 'publish'
	AND posts.post_author = {$user_id}
	AND postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes'
	AND postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) <= '{$stock}'
	AND postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) > '{$nostock}'
", $stock, $nostock );
$lowinstock_count = absint( $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" ) );

$query_from = apply_filters( 'wcfm_report_out_of_stock_query_from', "FROM {$wpdb->posts} as posts
	INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
	INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
	WHERE 1=1
	AND posts.post_type IN ( 'product', 'product_variation' )
	AND posts.post_status = 'publish'
	AND postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes'
	AND postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) <= '{$nostock}'
", $nostock );

$outofstock_count = absint( $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" ) );

// Total Commission Earned
$sql = "SELECT SUM( commission.total_due ) AS total_due, SUM( commission.total_shipping ) AS total_shipping, SUM( commission.tax ) AS tax FROM {$wpdb->prefix}pv_commission AS commission";
$sql .= " WHERE 1=1";
$sql .= " AND commission.vendor_id = %d";
$sql .= " AND MONTH( commission.time ) = MONTH( NOW() )";

$earned = 0;
$total_earneds = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );
if( !empty($total_earneds) ) {
	foreach( $total_earneds as $total_earned ) {
		$earned = $total_earned->total_due;
		if ( WC_Vendors::$pv_options->get_option( 'give_tax' ) ) { $earned += $total_earned->total_shipping; } 
		if ( WC_Vendors::$pv_options->get_option( 'give_shipping' ) ) { $earned += $total_earned->tax; }
	}
}
if( !$earned ) $earned = 0;

// Total Paid Commission
$sql = "SELECT SUM( commission.total_due ) AS total_due, SUM( commission.total_shipping ) AS total_shipping, SUM( commission.tax ) AS tax FROM {$wpdb->prefix}pv_commission AS commission";
$sql .= " WHERE 1=1";
$sql .= " AND commission.vendor_id = %d";
$sql .= " AND commission.status = 'paid'";
$sql .= " AND MONTH( commission.time ) = MONTH( NOW() )";

$commission = 0;
$total_commissions = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );
if( !empty($total_commissions) ) {
	foreach( $total_commissions as $total_commission ) {
		$commission = $total_commission->total_due;
		if ( WC_Vendors::$pv_options->get_option( 'give_tax' ) ) { $commission += $total_commission->total_shipping; } 
		if ( WC_Vendors::$pv_options->get_option( 'give_shipping' ) ) { $commission += $total_commission->tax; }
	}
}
if( !$commission ) $commission = 0;

// Total item sold
$sql = "SELECT SUM( commission.qty ) FROM {$wpdb->prefix}pv_commission AS commission";
$sql .= " WHERE 1=1";
$sql .= " AND commission.vendor_id = %d";
$sql .= " AND MONTH( commission.time ) = MONTH( NOW() )";

$total_sell = $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
if( !$total_sell ) $total_sell = 0;

// Counts
$on_hold_count    = 0;
$processing_count = 0;

foreach ( wc_get_order_types( 'order-count' ) as $type ) {
	$counts           = (array) wp_count_posts( $type );
	$on_hold_count    += isset( $counts['wc-on-hold'] ) ? $counts['wc-on-hold'] : 0;
	$processing_count += isset( $counts['wc-processing'] ) ? $counts['wc-processing'] : 0;
}

// Awaiting shipping - wrongly showing
$sql = "SELECT SUM( commission.qty ) FROM {$wpdb->prefix}pv_commission AS commission";
$sql .= " WHERE 1=1";
$sql .= " AND commission.vendor_id = %d";
$sql .= " AND commission.status = 'due'";
$sql .= " AND MONTH( commission.time ) = MONTH( NOW() )";

$unfulfilled_products = $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
if( !$unfulfilled_products ) $unfulfilled_products = 0;

include_once( $WCFM->plugin_path . 'includes/reports/class-wcvendors-report-sales-by-date.php' );
$wcfm_report_sales_by_date = new WC_Vendors_Report_Sales_By_Date();
$wcfm_report_sales_by_date->chart_colors = array(
			'average'          => '#95a5a6',
			'order_count'      => '#dbe1e3',
			'item_count'       => '#ecf0f1',
			'shipping_amount'  => '#FF7400',
			'earned'           => '#4096EE',
			'commission'       => '#008C00',
		);

$wcfm_report_sales_by_date->calculate_current_range( '7day' );
$report_data   = $wcfm_report_sales_by_date->get_report_data();

$date_diff = date_diff( date_create(date('Ymd', $start_date)), date_create(date('Ymd', $end_date)) );

$can_view_orders = WC_Vendors::$pv_options->get_option( 'can_show_orders' );
$can_view_sales = WC_Vendors::$pv_options->get_option( 'can_view_frontend_reports' );

do_action( 'before_wcfm_dashboard' );
?>

<div class="collapse wcfm-collapse" id="wcfm_order_details">

  <div class="wcfm-page-headig">
		<span class="fa fa-dashboard"></span>
		<span class="wcfm-page-heading-text"><?php _e( 'Dashboard', $WCFM->text_domain ); ?></span>
	</div>
	<div class="wcfm-collapse-content">

		<?php do_action( 'begin_wcfm_dashboard' ); ?>
		
		<div class="wcfm_dashboard_wc_status">
			<?php if( $can_view_orders ) { ?>
				<div class="wcfm_dashboard_wc_status_data">
					<div class="page_collapsible" id="wcfm_dashboard_wc_status">
						<span class="fa fa-line-chart"></span>
						<span class="dashboard_widget_head"><?php _e('Store Status', $WCFM->text_domain); ?></span>
					</div>
					<div class="wcfm-container">
						<div id="wcfm_dashboard_wc_status_expander" class="wcfm-content">
							<ul class="wc_status_list">
								<li class="sales-this-month">
									<span class="fa fa-shopping-cart"></span>
									<a href="<?php echo get_wcfm_reports_url( ); ?>">
										<?php printf( __( '<strong>%s</strong><br /> net commission in this month', $WCFM->text_domain ), wc_price( $earned ) ); ?>
									</a>
								</li>
								<li class="sales-this-month">
									<span class="fa fa-money"></span>
									<a href="<?php echo get_wcfm_reports_url( ); ?>">
										<?php printf( __( '<strong>%s</strong><br /> paid commission in this month', $WCFM->text_domain ), wc_price( $commission ) ); ?>
									</a>
								</li>
								<li class="sales-count-this-month">
									<span class="fa fa-cubes"></span>
									<a href="<?php echo apply_filters( 'sales_by_product_report_url', get_wcfm_reports_url( ), '' ); ?>">
										<?php printf( __( '<strong>%s items</strong><br /> net sales in this month', $WCFM->text_domain ), $total_sell ); ?>
									</a>
								</li>
								<?php
								if ( ( $top_seller = $this->get_top_seller() ) && $top_seller->qty ) {
								?>
									<li class="best-seller-this-month">
										<span class="fa fa-cube"></span>
										<a href="<?php echo apply_filters( 'sales_by_product_report_url',  get_wcfm_reports_url( ), $top_seller->product_id ); ?>">
											<?php printf( __( '%s top seller in last 7 days (sold %d)', $WCFM->text_domain ), '<strong>' . get_the_title( $top_seller->product_id ) . '</strong><br />', $top_seller->qty ); ?>
										</a>
									</li>
								<?php
								}
								?>
								<li class="processing-orders">
									<span class="fa fa-life-ring"></span>
									<a href="<?php echo get_wcfm_orders_url( ); ?>">
										<?php printf( _n( "<strong>%s order</strong><br /> processing", "<strong>%s orders</strong><br /> processing", $processing_count, $WCFM->text_domain ), $processing_count ); ?>
									</a>
								</li>
								<li class="on-hold-orders">
									<span class="fa fa-truck"></span>
									<a href="<?php echo get_wcfm_orders_url( ); ?>">
										<?php printf( _n( "<strong>%s product</strong><br /> awaiting fulfillment", "<strong>%s products</strong><br /> awaiting fulfillment", $unfulfilled_products, $WCFM->text_domain ), $unfulfilled_products ); ?>
									</a>
								</li>
								
								<li class="low-in-stock">
									<span class="fa fa-sort-amount-desc"></span>
									<a href="<?php echo apply_filters( 'low_in_stock_report_url',  get_wcfm_reports_url( ) ); ?>">
										<?php printf( _n( "<strong>%s product</strong><br /> low in stock", "<strong>%s products</strong><br /> low in stock", $lowinstock_count, $WCFM->text_domain ), $lowinstock_count ); ?>
									</a>
								</li>
								<li class="out-of-stock">
									<span class="fa fa-times-circle-o"></span>
									<a href="<?php echo get_wcfm_reports_url( '', 'wcfm-reports-out-of-stock' ); ?>">
										<?php printf( _n( "<strong>%s product</strong><br /> out of stock", "<strong>%s products</strong><br /> out of stock", $outofstock_count, $WCFM->text_domain ), $outofstock_count ); ?>
									</a>
								</li>
							</ul>
						</div>
					</div>
				</div>
			<?php } ?>
		
			<?php if( $can_view_sales ) { ?>
				<div class="wcfm_dashboard_wc_status_graph">
					<div class="wcfm_dashboard_wc_reports">
						<div class="page_collapsible" id="wcfm_dashboard_wc_reports"><span class="fa fa-pie-chart"></span><span class="dashboard_widget_head"><?php _e('Sales by Product', $WCFM->text_domain); ?></span></div>
						<div class="wcfm-container">
							<div id="wcfm_dashboard_wc_reports_expander" class="wcfm-content">
								<a href="<?php echo apply_filters( 'sales_by_product_report_url',  get_wcfm_reports_url( ), ( $top_seller ) ? $top_seller->product_id : '' ); ?>">
									<div id="sales-piechart"></div>
								</a>
							</div>
						</div>
					</div>
					
					<div class="wcfm_dashboard_wc_reports">
						<div class="page_collapsible" id="wcfm_dashboard_wc_reports"><span class="fa fa-bar-chart"></span><span class="dashboard_widget_head"><?php _e('Sales by Date', $WCFM->text_domain); ?></span></div>
						<div class="wcfm-container">
							<div id="wcfm_dashboard_wc_reports_expander" class="wcfm-content">
								<div id="poststuff" class="woocommerce-reports-wide">
									<div class="postbox">
										<div class="inside">
											<a href="<?php echo get_wcfm_reports_url( 'month' ); ?>">
												<?php $wcfm_report_sales_by_date->get_main_chart(); ?>
											</a>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
			
					<?php do_action('after_wcfm_dashboard_sales_report'); ?>
				</div>
			<?php } ?>
		</div>
	</div>
</div>