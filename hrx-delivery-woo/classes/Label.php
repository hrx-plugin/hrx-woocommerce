<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Shipment;
use HrxDeliveryWoo\Pdf;
use HrxDeliveryWoo\Debug;

class Label
{
    public static function save_file( $file_name, $file_content )
    {
        $file_locations = self::get_file_locations($file_name);

        $result = file_put_contents($file_locations['path'], base64_decode($file_content));

        return ($result) ? $file_locations : false;
    }

    private static function get_file_locations( $file_name )
    {
        $core = Core::get_instance();
        
        $core->prepare_temp_dirs();
        
        return array(
            'name' => $file_name,
            'url' => $core->structure->url . $core->structure->temp . 'pdf/' . $file_name,
            'path' => $core->structure->path . $core->structure->temp . 'pdf/' . $file_name,
        );
    }

    public static function get_merged_file( $wc_orders_ids, $label_type, $output_file_name )
    {
        $status = array(
            'status' => 'error',
            'msg' => '',
            'file' => '',
            'data' => '',
        );

        $just_registered = array();
        $failed_labels = array();
        foreach ( $wc_orders_ids as $wc_order_id ) {
            $result = Shipment::register_order($wc_order_id);
            if ( $result['status'] == 'error' ) {
                $status['msg'] = $result['msg'];
                $status['data'] = array($wc_order_id);
                return $status;
            }
            
            $label = Shipment::get_label($wc_order_id, $label_type);
            if ( $label['status'] == 'error' ) {
                if ( $result['status_code'] == 'registered' ) {
                    $just_registered[] = $wc_order_id;
                } else {
                    $failed_labels[] = $wc_order_id;
                    Debug::to_log(array(
                        'action' => 'Merge labels file',
                        'label_status' => $label,
                        'order_status' => $result,
                    ), 'mass_action');
                }
                continue;
            }

            $received_files[] = $label['label_path'];
        }

        if ( ! empty($just_registered) ) {
            $status['msg'] = __('Some orders have just been registered and still dont have labels ready. You can repeat this action to try get labels again.', 'hrx-delivery');
            $status['data'] = $just_registered;
            return $status;
        }

        if ( ! empty($failed_labels) ) {
            $status['msg'] = __('Failed to retrieve some order labels. They may have not been generated yet. You can repeat this action to try get labels again.', 'hrx-delivery');
            $status['data'] = $failed_labels;
            return $status;
        }
        
        $merged_file_content = Pdf::merge_pdfs($received_files);
        $label = self::save_file($output_file_name . '.pdf', $merged_file_content);

        $status['status'] = 'OK';
        $status['file'] = $label;

        return $status;
    }
}
