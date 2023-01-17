<?php

if (!defined( 'ABSPATH')) exit;

/**
*
*/
class CFDB7_Form_Details
{
    private $form_id;
    private $form_post_id;


    public function __construct()
    {
        $this->form_post_id = isset( $_GET['fid'] ) ? (int) $_GET['fid'] : 0;
        $this->form_id      = isset( $_GET['ufid'] ) ? (int) $_GET['ufid'] : 0;

        $this->form_details_page();
    }

    public function form_details_page(){
        global $wpdb;
        $cfdb          = apply_filters( 'cfdb7_database', $wpdb );
        $table_name    = $cfdb->prefix.'db7_forms';
        $upload_dir    = wp_upload_dir();
        $cfdb7_dir_url = $upload_dir['baseurl'].'/cfdb7_uploads';
        $rm_underscore = apply_filters('cfdb7_remove_underscore_data', true); 



        $results    = $cfdb->get_results( "SELECT * FROM $table_name WHERE form_post_id = $this->form_post_id AND form_id = $this->form_id LIMIT 1", OBJECT );
        

        if ( empty($results) ) {
            wp_die( $message = 'Not valid contact form' );
        }
        ?>
        <div class="wrap">
            <div id="welcome-panel" class="welcome-panel">
                <div class="welcome-panel-content">
                    <div class="welcome-panel-column-container">
                        <?php do_action('cfdb7_before_formdetails_title',$this->form_post_id ); ?>
                        <h3><?php echo get_the_title( $this->form_post_id ); ?></h3>
                        <?php do_action('cfdb7_after_formdetails_title', $this->form_post_id ); ?>
                        <p></span><?php echo $results[0]->form_date; ?></p>
                        <?php $form_data  = unserialize( $results[0]->form_value );

                        foreach ($form_data as $key => $data):

                            $matches = array();

                            if ( $key == 'cfdb7_status' )  continue;
                            if( $rm_underscore ) preg_match('/^_.*$/m', $key, $matches);
                            if( ! empty($matches[0]) ) continue;

                            if ( strpos($key, 'cfdb7_file') !== false ){
                                $key_val = str_replace('cfdb7_file', '', $key);
                                $key_val = str_replace('your-', '', $key_val);
                                $key_val = str_replace( array('-','_'), ' ', $key_val);
                                $key_val = ucwords( $key_val );
                                if (is_array($data)) {
                                    $file_links = [];
                                    foreach ($data as $inner_file) {
                                        array_push($file_links, '<a href="'.$cfdb7_dir_url.'/'.$inner_file.'">'.$inner_file.'</a>');
                                    }
                                    echo '<p><b>'.$key_val.'</b>: '.implode(', ', $file_links).'</p>';
                                } else {
                                    echo '<p><b>'.$key_val.'</b>: <a href="'.$cfdb7_dir_url.'/'.$data.'">'
                                    .$data.'</a></p>';
                                }
                            }else{


                                if ( is_array($data) ) {

                                    $key_val      = str_replace('your-', '', $key);
                                    $key_val      = str_replace( array('-','_'), ' ', $key_val);
                                    $key_val      = ucwords( $key_val );
                                    $arr_str_data =  implode(', ',$data);
                                    $arr_str_data =  esc_html( $arr_str_data );
                                    echo '<p><b>'.$key_val.'</b>: '. nl2br($arr_str_data) .'</p>';

                                }else{

                                    $key_val = str_replace('your-', '', $key);
                                    $key_val = str_replace( array('-','_'), ' ', $key_val);

                                    $key_val = ucwords( $key_val );
                                    $data    = esc_html( $data );
                                    echo '<p><b>'.$key_val.'</b>: '.nl2br($data).'</p>';
                                }
                            }

                        endforeach;

                        $form_data['cfdb7_status'] = 'read';
                        $form_data = serialize( $form_data );
                        $form_id = $results[0]->form_id;

                        $cfdb->query( "UPDATE $table_name SET form_value =
                            '$form_data' WHERE form_id = '$form_id' LIMIT 1"
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        do_action('cfdb7_after_formdetails', $this->form_post_id );
    }

}
