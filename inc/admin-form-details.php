<?php

if (!defined( 'ABSPATH')) exit;

/**
* Details Page
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


        $result    = $cfdb->get_row( "SELECT * FROM $table_name WHERE form_post_id = $this->form_post_id AND form_id = $this->form_id LIMIT 1", OBJECT );
        

        if ( empty($result) ) {
            wp_die( 'Not valid contact form' );
        }
        ?>
        <div class="wrap">
            <div id="welcome-panel" class="cfdb7-panel">
                <div class="cfdb7-panel-content">
                    <div class="welcome-panel-column-container">
                        <?php do_action('cfdb7_before_formdetails_title',$this->form_post_id ); ?>

                        <h3><?php esc_html_e( get_the_title( $this->form_post_id ), 'contact-form-cfdb7' )?></h3>
                        
                        <?php do_action('cfdb7_after_formdetails_title', $this->form_post_id ); ?>
                        
                        <p></span><?php  esc_html_e( $result->form_date, 'contact-form-cfdb7' ) ?></p>
                        
                        <?php $form_data  = unserialize( $result->form_value, ['allowed_classes' => false] );

                        foreach ($form_data as $key => $data):

                            $matches = array();
                            $key     = esc_html( $key );

                            if ( $key == 'cfdb7_status' )  continue;
                            if( $rm_underscore ) preg_match('/^_.*$/m', $key, $matches);
                            if( ! empty($matches[0]) ) continue;

                            if ( strpos($key, 'cfdb7_file') !== false ){

                                $key_val = str_replace('cfdb7_file', '', $key);
                                $key_val = str_replace('your-', '', $key_val);
                                $key_val = str_replace( array('-','_'), ' ', $key_val);
                                $key_val = ucwords( $key_val );
                                echo '<p><b>'.esc_html($key_val).'</b>: <a href="'.esc_url($cfdb7_dir_url.'/'.$data).'">'
                                .esc_html($data).'</a></p>';
                            }else{


                                if ( is_array($data) ) {

                                    $key_val      = str_replace('your-', '', $key);
                                    $key_val      = str_replace( array('-','_'), ' ', $key_val);
                                    $key_val      = ucwords( $key_val );
                                    $arr_str_data =  implode(', ',$data);
                                    $arr_str_data =  esc_html( $arr_str_data );
                                    echo '<p><b>'.esc_html($key_val).'</b>: '. nl2br($arr_str_data) .'</p>';

                                }else{

                                    $key_val = str_replace('your-', '', $key);
                                    $key_val = str_replace( array('-','_'), ' ', $key_val);

                                    $key_val = ucwords( $key_val );
                                    $data    = esc_html( $data );
                                    echo '<p><b>'.esc_html($key_val).'</b>: '.nl2br($data).'</p>';
                                }
                            }

                        endforeach;

                        $form_data['cfdb7_status'] = 'read';
                        $form_data = serialize( $form_data );
                        $form_id = $result->form_id;
                        
                        $sql = $cfdb->prepare(
                            "UPDATE {$table_name} SET form_value = %s WHERE form_id = %d",
                            $form_data, 
                            $form_id  
                        );
                        $cfdb->query( $sql );
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        do_action('cfdb7_after_formdetails', $this->form_post_id );
    }

}
