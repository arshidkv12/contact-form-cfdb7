<?php
/**
 * CFDB7 csv
 */

if (!defined( 'ABSPATH')) exit;

class Expoert_CSV{

    /**
     * Download csv file
     * @param  String $filename
     * @return file
     */
    public function download_send_headers( $filename ) {
        // disable caching
        $now = gmdate("D, d M Y H:i:s");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");

        // force download
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");

        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename={$filename}");
        header("Content-Transfer-Encoding: binary");

    }
    /**
     * Convert array to csv format
     * @param  array  &$array
     * @return file csv format
     */
    public function array2csv(array &$array){

        if (count($array) == 0) {
            return null;
        }
        ob_start();
        $df = fopen("php://output", 'w');
        $array_keys = array_keys(reset($array));
        $heading = array();
        $unwanted = array('cfdb7_', 'your-');
        foreach ($array_keys as $aKeys) {
            $tmp = str_replace($unwanted, '', $aKeys);
            $heading[] = ucfirst($tmp);
        }
        fputcsv($df, $heading);

        foreach ($array as $row) {
            fputcsv($df, $row);
        }
        fclose($df);
        return ob_get_clean();
    }
    /**
     * Download file
     * @return csv file
     */
    public function download_csv_file(){

        global $wpdb;
        $cfdb        = apply_filters( 'cfdb7_database', $wpdb );
        $table_name  = $cfdb->prefix.'db7_forms';

        if( isset($_REQUEST['csv']) && isset( $_REQUEST['nonce'] ) ){

            $nonce =  $_REQUEST['nonce'];
            if ( ! wp_verify_nonce( $nonce, 'dnonce')) {

                wp_die( 'Not Valid.. Download nonce..!! ' );
            }
            $fid = (int)$_REQUEST['fid'];
            $results = $cfdb->get_results("SELECT form_id, form_value, form_date FROM $table_name
                WHERE form_post_id = '$fid' ",OBJECT);
            $data = array();
            $i = 0;
            foreach ($results as $result) :
                $i++;
                $data[$i]['Id']         = $result->form_id;
                $data[$i]['Date']       = $result->form_date;
                $resultTmp              = unserialize( $result->form_value );
                $upload_dir             = wp_upload_dir();
                $cfdb7_dir_url          = $upload_dir['baseurl'].'/cfdb7_uploads';

                foreach ($resultTmp as $key => $value):

                    if (strpos($key, 'cfdb7_file') !== false ){
                        $data[$i][$key] = $cfdb7_dir_url.'/'.$value;
                        continue;
                    }
                    if ( is_array($value) ){

                        $data[$i][$key] = implode(', ', $value);
                        continue;
                    }

                   $data[$i][$key] = str_replace( array('&quot;','&#039;','&#047;','&#092;')
                    , array('"',"'",'/','\\'), $value );

                endforeach;

            endforeach;

            $this->download_send_headers( "cfdb7-" . date("Y-m-d") . ".csv" );
            echo $this->array2csv( $data );
            die();
        }
    }
}
