<?php
/**
 * CFDB7 csv
 */

if (!defined( 'ABSPATH')) exit;

class CFDB7_Export_CSV{

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
		
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment;filename={$filename}");

        // disposition / encoding on response body
		header("Content-Disposition: attachment;filename={$filename}");
        header("Content-Transfer-Encoding: binary");

    }
    /**
     * Column titles for a list of keys.
     * @param  array $keys
     * @return array
     */
    public function csv_heading( array $keys ){

        $heading  = array();
        $unwanted = array('cfdb7_file', 'cfdb7_', 'your-');

        foreach ( $keys as $aKeys ) {
            if( $aKeys == 'form_date' ) $aKeys = 'Date';
            if( $aKeys == 'form_id' ) $aKeys = 'Id';
            $tmp       = str_replace( $unwanted, '', $aKeys );
            $tmp       = str_replace( array('-','_'), ' ', $tmp );
            $heading[] = ucwords( $tmp );
        }

        return $heading;
    }

    /**
     * Convert array to csv format
     * @param  array  &$array
     * @param  resource $df
     * @param  array|null $columns Keys to write, in this order. When given, the byte order mark and the
     *                             heading are NOT written here: the caller writes them once for the whole file.
     */
    public function array2csv(array &$array, $df, $columns = null){

        if (count($array) == 0) {
            return null;
        }

        $delimiter  = apply_filters('cfdb7_csv_delimiter', ',');

        if ( $columns === null ) {
            // Old behaviour, kept for add-ons that call this method themselves
            $array_keys = array_keys($array);
            fputs( $df, ( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );
            fputcsv( $df, $this->csv_heading( $array_keys ), $delimiter, '"', '' );
        } else {
            $array_keys = $columns;
        }

        foreach ( $array['form_id'] as $line => $form_id ) {
            $line_values = array();
            foreach($array_keys as $array_key ) {
                $val = isset( $array[ $array_key ][ $line ] ) ? $array[ $array_key ][ $line ] : '';
                $line_values[ $array_key ] = $val;
            }
            fputcsv($df, $line_values, $delimiter, '"', '');
        }
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
            $fid         = (int)$_REQUEST['fid'];
            $heading_row = $cfdb->get_results("SELECT form_id, form_value, form_date FROM $table_name
                WHERE form_post_id = '$fid' ORDER BY form_id DESC LIMIT 1",OBJECT);

            $heading_row    = reset( $heading_row );
            $heading_row    = unserialize( $heading_row->form_value, ['allowed_classes' => false] );
            $heading_key    = array_keys( $heading_row );
            $rm_underscore  = apply_filters('cfdb7_remove_underscore_data', true); 


            $total_rows  = $cfdb->get_var("SELECT COUNT(*) FROM $table_name WHERE form_post_id = '$fid' "); 
            $per_query    = 1000;
            $total_query  = ceil( $total_rows / $per_query );

            // The same columns, in the same order, for every block of 1000 rows
            $columns = array( 'form_id', 'form_date' );
            foreach ( $heading_key as $key ) {
                if ( $rm_underscore && preg_match('/^_.*$/m', $key) ) continue;
                $columns[] = $key;
            }

            $this->download_send_headers( "cfdb7-" . date("Y-m-d") . ".csv" );
            $df = fopen("php://output", 'w');
            ob_start();

            // Byte order mark and heading: once, at the top of the file
            fputs( $df, ( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );
            fputcsv( $df, $this->csv_heading( $columns ), apply_filters('cfdb7_csv_delimiter', ','), '"', '' );

            for( $p = 0; $p < $total_query; $p++ ){

                $offset  = $p * $per_query;
                $results = $cfdb->get_results("SELECT form_id, form_value, form_date FROM $table_name
                WHERE form_post_id = '$fid' ORDER BY form_id DESC  LIMIT $offset, $per_query",OBJECT);
                
                $data  = array();
                $i     = 0;
                foreach ($results as $result) :
                    
                    $i++;
                    $data['form_id'][$i]    = $result->form_id;
                    $data['form_date'][$i]  = $result->form_date;
                    $resultTmp              = unserialize( $result->form_value, ['allowed_classes' => false] );
                    $upload_dir             = wp_upload_dir();
                    $cfdb7_dir_url          = $upload_dir['baseurl'].'/cfdb7_uploads';

                    foreach ($resultTmp as $key => $value):
                        $matches = array();

                        if ( ! in_array( $key, $heading_key ) ) continue;
                        if( $rm_underscore ) preg_match('/^_.*$/m', $key, $matches);
                        if( ! empty($matches[0]) ) continue;

                        $value = str_replace( 
                                    array('&quot;','&#039;','&#047;','&#092;'),
                                    array('"',"'",'/','\\'), $value 
                                );

                        if (strpos($key, 'cfdb7_file') !== false ){
                            $data[$key][$i] = empty( $value ) ? '' : $cfdb7_dir_url.'/'.$value;
                            $data[$key][$i] = $this->escape_data( $data[$key][$i] );
                            continue;
                        }
                        if ( is_array($value) ){

                            $data[$key][$i] = implode(', ', $value);
                            $data[$key][$i] = $this->escape_data( $data[$key][$i] );
                            continue;
                        }
                        $data[$key][$i] = $value;
                        $data[$key][$i] = $this->escape_data( $data[$key][$i] );

                    endforeach;

                endforeach;

                $this->array2csv( $data, $df, $columns );

            }
            echo ob_get_clean();
            fclose( $df );
            die();
        }
    }

    /**
    * Escape a string to be used in a CSV context
    * @param string $data CSV field to escape.
    * @return string    
    */
    public function escape_data( $data ) {
		// A cell that starts with one of these is read as a formula by spreadsheets (CSV injection).
		// The usual protection is a leading apostrophe. Wrapping the value in double quotes does not work:
		// fputcsv() then quotes it again and the quotes end up inside the cell.
		$active_content_triggers = array( '=', '+', '-', '@', ';', "\t", "\r" );

		if ( in_array( mb_substr( (string) $data, 0, 1 ), $active_content_triggers, true ) ) {
			$data = "'" . $data;
		}

		return $data;
	}
}