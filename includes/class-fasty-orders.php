<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 *  Delivery Orders Class
 *
 * Adds order admin page customizations
 *
 * @since 1.0
 */
class WC_Fasty_Delivery_Orders
{
    /** @var \WC_Fasty_Delivery_Orders single instance of this class */
    private static $instance;

    /** @var array settings value for this plugin */
    public $settings;


    /**
     * Include various admin hooks and filters related to the Delivery integration.

     *
     * @since  1.0
     */
    public function __construct() {
        /** Order Hooks */
        $this->settings = maybe_unserialize(get_option('woocommerce_Fasty_delivery_settings'));

        if ($this->settings['enabled'] == 'yes') {
            // add bulk action to update order status for multiple orders from Fasty
            add_action('admin_footer-edit.php', array($this, 'create_order_bulk_actions'));
            add_action('admin_footer-edit.php', array($this, 'update_order_bulk_actions'));
            add_action('load-edit.php', array($this, 'process_order_bulk_actions'));

            // add 'Fasty Delivery Information' order meta box
            add_action('add_meta_boxes', array($this, 'add_order_meta_box'));

            // process order update action
            add_action('woocommerce_order_action_wc_Fasty_delivery_update_status', array($this, 'process_order_update_action'));

            // process order create action
            add_action('woocommerce_order_action_wc_Fasty_delivery_create', array($this, 'process_order_create_action'));

            // add 'Update Fasty Delivery Status' order meta box order actions
            add_action('woocommerce_order_actions', array($this, 'add_order_meta_box_actions'));
        }
    }

    /**
     * Add "Update Fasty Order Status" custom bulk action to the 'Orders' page bulk action drop-down
     *
     * @since 1.0
     */
    public function create_order_bulk_actions() {
        global $post_type, $post_status;

        if ($post_type == 'shop_order' && $post_status != 'trash' && $this->settings['shipping_is_scheduled_on'] == 'manual_submit') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('select[name^=action]').append(
                        $('<option>').val('create_order').text('<?php _e('Create Fasty Delivery Order'); ?>')
                    );
                });
            </script>
            <?php
        }
    }

    /**
     * Add "Update Fasty Order Status" custom bulk action to the 'Orders' page bulk action drop-down
     *
     * @since 1.0
     */
    public function update_order_bulk_actions() {
        global $post_type, $post_status;

        if ($post_type == 'shop_order' && $post_status != 'trash') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('select[name^=action]').append(
                        $('<option>').val('update_order_status').text('<?php _e('Update Fasty Delivery Order'); ?>')
                    );
                });
            </script>
            <?php
        }
    }

    /**
     * Processes the "Export to Fasty" & "Update Tracking" custom bulk actions on the 'Orders' page bulk action drop-down
     *
     * @since  1.0
     */
    public function process_order_bulk_actions()
    {
        global $typenow;

        if ('shop_order' == $typenow) {
            // get the action
            $wp_list_table = _get_list_table('WP_Posts_List_Table');
            $action        = $wp_list_table->current_action();
            // return if not processing our actions
            if (!in_array($action, array('update_order_status')) && !in_array($action, array('create_order'))) {
                return;
            }

            // security check
            check_admin_referer('bulk-posts');

            // make sure order IDs are submitted
            if (isset($_REQUEST['post'])) {
                $order_ids = array_map('absint', $_REQUEST['post']);
            }

            // return if there are no orders to export
            if (empty($order_ids)) {
                return;
            }

            // give ourselves an unlimited timeout if possible
            @set_time_limit(0);

            if (in_array($action, array('update_order_status'))) {
                foreach ($order_ids as $order_id) {
                    try {
                        $order = wc_get_order( $order_id );
                        if ($order->get_meta('Fasty_delivery_order_id')) {
                            wc_Fasty_delivery()->update_order_shipping_status($order_id);
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            else if (in_array($action, array('create_order'))) {
                foreach ($order_ids as $order_id) {
                    try {
                        $order = wc_get_order( $order_id );
                        if (!$order->get_meta('Fasty_delivery_order_id')) {
                            wc_Fasty_delivery()->create_order_shipping_task($order_id);
                        }
                    } catch (\Exception $e) {
                    }
                }
            }
            
        }
    }

    /**
     * Add 'Update Shipping Status' order actions to the 'Edit Order' page
     *
     * @since 1.0
     * @param array $actions
     * @return array
     */
    public function add_order_meta_box_actions($actions)
    {
        global $theorder;

        //create Fasty order
        if ($this->settings['shipping_is_scheduled_on'] == 'manual_submit' && !$theorder->get_meta('Fasty_delivery_order_id')) {
            $actions['wc_Fasty_delivery_create'] = __('Create Fasty Delivery order', 'my-textdomain');
        }

        // add update shipping status action
        else if ($theorder->get_meta('Fasty_delivery_order_id')) {
            $actions['wc_Fasty_delivery_update_status'] = __('Update order status (via Fasty Delivery)');
        }

        //check for Fasty order retries after failure
        else if ($theorder->get_meta('Fasty_delivery_failed')) {
            $actions['wc_Fasty_delivery_create'] = __('Retry Fasty Delivery order');
        }

        return $actions;
    }


  /**
   * Handle actions from the 'Update Shipping Status' order action select box.
   *
   * @param \WC_Order $order The order object.
   */
  public function process_order_update_action( $order ) {
    wc_Fasty_delivery()->update_order_shipping_status( $order );
  }

  /**
   * Handle actions from the 'Create Order' order action select box.
   *
   * @param \WC_Order $order The order object.
   */
  public function process_order_create_action( $order ) {
    wc_Fasty_delivery()->create_order_shipping_task( $order->get_id() );
  }


   /**
   * Add 'Fasty Delivery Information' meta-box to 'Edit Order' page.
   */
  public function add_order_meta_box() {
    add_meta_box(
      'wc_Fasty_delivery_order_meta_box',
      __( 'Fasty Delivery' ),
      array( $this, 'render_order_meta_box' ),
      'shop_order',
      'side'
    );
  }


  /**
   * Display the 'Fasty Delivery Information' meta-box on the 'Edit Order' page.
   */
  public function render_order_meta_box() {
    global $post;

    $order = wc_get_order( $post );

    $Fasty_order_id = $order->get_meta( 'Fasty_delivery_order_id' );

        if ($Fasty_order_id) {
            $this->show_Fasty_delivery_shipment_status($order);
        } else {
            $this->shipment_order_send_form($order);
        }
    }

    public function show_Fasty_delivery_shipment_status($order)
    {
        $Fasty_order_id = $order->get_meta('Fasty_delivery_order_id');
        ?>
    
            <table id="wc_Fasty_delivery_order_meta_box">
                <tr>
                    <th><strong><?php esc_html_e('Unique Order ID') ?> : </strong></th>
                    <td><?php echo esc_html((empty($Fasty_order_id)) ? __('N/A') : $Fasty_order_id); ?></td>
                </tr>

                <tr>
                    <th><strong><?php esc_html_e('Order Status') ?> : </strong></th>
                    <td>
                        <?php echo $order->get_meta('Fasty_order_status'); ?>
                    </td>
                </tr>
            </table>
        <?php
    }

    public function shipment_order_send_form($order)
    {
        ?> 
            <p> No scheduled task for this order</p>
        <?php
    }

    /**
     * Gets the main loader instance.
     *
     * Ensures only one instance can be loaded.
     *
     *
     * @return \WC_Fasty_Delivery_Loader
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}

return new WC_Fasty_Delivery_Orders;
