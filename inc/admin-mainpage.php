<?php
/**
 * CFDB7 Admin section
 */

if (!defined( 'ABSPATH')) exit;

/**
 * Cfdb7_Wp_List_Table class will create the page to load the table
 */
class Cfdb7_Wp_Main_Page
{
    /**
     * Constructor will create the menu item
     */
    public function __construct()
    {
        add_action( 'admin_menu', array($this, 'admin_list_table_page' ) );
    }


    /**
     * Menu item will allow us to load the page to display the table
     */
    public function admin_list_table_page()
    {
        wp_enqueue_style( 'cfdb7-admin-style', plugin_dir_url(dirname(__FILE__)).'css/admin-style.css' );

		// Fallback: Make sure admin always has access
		$cfdb7_cap = ( current_user_can( 'cfdb7_access') ) ? 'cfdb7_access' : 'manage_options';

        add_menu_page( __( 'Contact Forms', 'contact-form-cfdb7' ), __( 'Contact Forms', 'contact-form-cfdb7' ), $cfdb7_cap, 'cfdb7-list.php', array($this, 'list_table_page'), 'dashicons-list-view' );

         require_once 'add-ons.php';

    }
    /**
     * Display the list table page
     *
     * @return Void
     */
    public function list_table_page()
    {
        if ( ! class_exists('WPCF7_ContactForm') ) {

           wp_die( 'Please activate <a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">contact form 7</a> plugin.' );
        }

        $fid  = empty($_GET['fid']) ? 0 : (int) $_GET['fid'];
        $ufid = empty($_GET['ufid']) ? 0 : (int) $_GET['ufid'];

        if ( !empty($fid) && empty($_GET['ufid']) ) {

            new Cfdb7_Wp_Sub_Page();
            return;
        }

        if( !empty($ufid) && !empty($fid) ){

            new CFdb7_Form_Details();
            return;
        }

        $ListTable = new CFDB7_Main_List_Table();
        $ListTable->prepare_items();
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2><?php _e( 'Contact Forms List', 'contact-form-cfdb7' ); ?></h2>
                <?php $ListTable->display(); ?>
            </div>
        <?php
    }

}
// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
/**
 * Create a new table class that will extend the WP_List_Table
 */
class CFDB7_Main_List_Table extends WP_List_Table
{

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {

        global $wpdb;
        $cfdb        = apply_filters( 'cfdb7_database', $wpdb );
        $table_name  = $cfdb->prefix.'db7_forms';
        $columns     = $this->get_columns();
        $hidden      = $this->get_hidden_columns();
        $data        = $this->table_data();
        $perPage     = 10;
        $currentPage = $this->get_pagenum();
        $count_forms = wp_count_posts('wpcf7_contact_form');
        $totalItems  = $count_forms->publish;


        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $this->_column_headers = array($columns, $hidden );
        $this->items = $data;
    }
    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {


        $columns = array(
            'name' => __( 'Name', 'contact-form-cfdb7' ),
            'count'=> __( 'Count', 'contact-form-cfdb7' )
        );

        return $columns;
    }
    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data()
    {
        global $wpdb;

        $cfdb         = apply_filters( 'cfdb7_database', $wpdb );
        $data         = array();
        $table_name   = $cfdb->prefix.'db7_forms';
        $page         = $this->get_pagenum();
        $page         = $page - 1;
        $start        = $page * 10;

        $args = array(
            'post_type'=> 'wpcf7_contact_form',
            'order'    => 'ASC',
            'posts_per_page' => 10,
            'offset' => $start
        );

        $the_query = new WP_Query( $args );

        while ( $the_query->have_posts() ) : $the_query->the_post();
            $form_post_id = get_the_id();
            $totalItems   = $cfdb->get_var("SELECT COUNT(*) FROM $table_name WHERE form_post_id = $form_post_id");
            $title = get_the_title();
            $link  = "<a class='row-title' href=admin.php?page=cfdb7-list.php&fid=$form_post_id>%s</a>";
            $data_value['name']  = sprintf( $link, $title );
            $data_value['count'] = sprintf( $link, $totalItems );
            $data[] = $data_value;
        endwhile;

        return $data;
    }
    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        return $item[ $column_name ];

    }

}
