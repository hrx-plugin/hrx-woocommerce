<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Terminal;
use HrxDeliveryWoo\Debug;

class Cronjob
{
    public $cronjobs = array(
        'hrx_update_warehouses' => array(
            'func' => 'job_update_warehouses',
            'freq' => 'daily',
            'time' => '02:00:00',
        ),
        'hrx_update_delivery_locations' => array(
            'func' => 'job_update_delivery_locs',
            'freq' => 'monthly',
            'time' => '03:00:00',
        ),
        /*'hrx_test' => array( // Activate if you want to test (use function job_test)
            'func' => 'job_test',
            'freq' => 'for_test',
        ),*/
    );

    private $core;
    private $main_file;

    public function __construct()
    {
        $this->core = Core::get_instance();
        $this->main_file = $this->core->structure->path . $this->core->structure->filename;
    }

    public function init()
    {
        add_filter('cron_schedules', array($this, 'add_frequency'));

        foreach ( $this->cronjobs as $job_key => $job_data ) {
            add_action($job_key, array($this, $job_data['func']));
        }

        register_activation_hook($this->main_file, array($this, 'activation'));
        register_deactivation_hook($this->main_file, array($this, 'deactivation'));
    }

    public function add_frequency( $schedules )
    {
        $schedules['for_test'] = array(
            'interval' => 65,
            'display' => _x('65 seconds', 'Frequency', 'hrx-delivery'),
        );
        $schedules['daily'] = array(
            'interval' => 86400,
            'display' => _x('Once daily', 'Frequency', 'hrx-delivery'),
        );
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => _x('Once weekly', 'Frequency', 'hrx-delivery'),
        );
        $schedules['monthly'] = array(
            'interval' => 2592000,
            'display' => _x('Once monthly', 'Frequency', 'hrx-delivery'),
        );
        
        return $schedules;
    }

    public function activation()
    {
        foreach ( $this->cronjobs as $job_key => $job_data ) {
            $time = (! empty($job_data['time'])) ? strtotime(date('Y-m-d') . ' ' . $job_data['time'] . ' +1 day') : time();

            if ( ! as_next_scheduled_action($job_key) ) {
                $freq = $this->get_interval_time($job_data['freq']);
                as_schedule_recurring_action($time, $freq, $job_key);

                $msg = 'Cronjob activated: ' . $job_key . ' from ' . date('Y-m-d H:i:s', $time) . ' with ' . $job_data['freq'] . ' frequency and launch function ' . $job_data['func'];
                Debug::to_log($msg, 'cronjob', true);
            }
        }
    }

    public function deactivation()
    {
        foreach ( $this->cronjobs as $job_key => $job_data ) {
            as_unschedule_action($job_key);

            Debug::to_log('Cronjob deactivated: ' . $job_key, 'cronjob', true);
        }
    }

    private function get_interval_time( $interval_key )
    {
        $all_intervals = apply_filters('cron_schedules', array());

        if ( isset($all_intervals[$interval_key]) ) {
            return $all_intervals[$interval_key]['interval'];
        }

        return 2592000;
    }

    public function job_update_warehouses()
    {
        $status = Warehouse::update_pickup_locations();

        if ( $status['status'] == 'OK' ) {
            $debug_msg = 'Successfully updated pickup locations (warehouses). Added ' . $status['added'] . ', updated ' . $status['updated'] . ', failed ' . $status['failed'];
        } else {
            $debug_msg = 'Failed to update pickup locations (warehouses). Error: ' . $status['msg'];
        }
        Debug::to_log($debug_msg, 'cronjob', true);
    }

    public function job_update_delivery_locs()
    {
        $status = Terminal::update_delivery_locations();

        if ( $status['status'] == 'OK' ) {
            $debug_msg = 'Successfully updated delivery locations. Added ' . $status['added'] . ', updated ' . $status['updated'] . ', failed ' . $status['failed'];
        } else {
            $debug_msg = 'Failed to update delivery locations. Error: ' . $status['msg'];
        }
        Debug::to_log($debug_msg, 'cronjob', true);
    }

    public function job_test()
    {
        // Write to this function and enable it in public $cronjobs for testing cronjob
        $debug_msg = 'Testing...';

        Debug::to_log($debug_msg, 'cronjob', true);
    }
}
