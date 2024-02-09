<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Core;
use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Warehouse;
use HrxDeliveryWoo\LocationsDelivery;
use HrxDeliveryWoo\Debug;

class Cronjob
{
    /**
     * List of cronjobs
     * 
     * Cronjob array params:
     * @param func - (Required) Job function
     * @param freq - (Required) How often the job is performed
     * @param time - (Optional) Static time of day when to activate the job. Use current time if not exists
     * @param random_time - (Optional) Interval with hours when to activate the job. A random value from interval is taken and written to the hours place of the static time
     **/
    public $cronjobs = array(
        'hrx_update_warehouses' => array(
            'func' => 'job_update_warehouses',
            'freq' => 'weekly',
            'time' => '02:00:00',
            'random_time' => [0, 1],
        ),
        'hrx_update_delivery_locations' => array(
            'func' => 'job_update_delivery_locs',
            'freq' => 'weekly',
            'time' => '04:00:00',
            'random_time' => [2, 6],
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

        $this->random_jobs_time();
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

    public function random_jobs_time()
    {
        foreach ( $this->cronjobs as $job_key => $job_data ) {
            if ( ! isset($job_data['random_time']) ) {
                continue;
            }
            if ( ! isset($job_data['time'])  ) {
                $job_data['time'] = '00:00:00';
            }

            $random_time = rand($job_data['random_time'][0], $job_data['random_time'][1]);
            $this->cronjobs[$job_key]['time'] = sprintf("%02d", $random_time) . substr($job_data['time'], 2);
        }
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
        Helper::update_hrx_option('cron_progress_delivery_locs', current_time("Y-m-d H:i:s"));
        try {
            $status = LocationsDelivery::update_couriers();
            if ( $status['status'] == 'OK' ) {
                LocationsDelivery::finish_locations_update($type);
                $debug_msg = 'Successfully updated courier delivery locations. Added ' . $status['added'] . ', updated ' . $status['updated'] . ', failed ' . $status['failed'];
            } else {
                $debug_msg = 'Failed to update courier delivery locations. Error: ' . $status['msg'];
            }
            Debug::to_log($debug_msg, 'cronjob', true);

            $status = LocationsDelivery::get_delivery_locations_countries();
            if ( $status['status'] == 'OK' ) {
                $debug_msg = 'Successfully received parcel terminals countries.';
                Debug::to_log($debug_msg, 'cronjob', true);
            } else {
                throw new \Exception('Failed to receive parcel terminals countries.');
            }

            $all_countries = Helper::get_hrx_option(LocationsDelivery::get_option_name('terminals_countries'));
            if ( empty($all_countries) ) {
                throw new \Exception('Received empty parcel terminals countries list.');
            }

            foreach ( $all_countries as $country => $endpoint ) {
                $status = LocationsDelivery::update(false, $country);
                if ( $status['status'] != 'OK' ) {
                    $debug_msg = 'Failed to update terminal delivery locations for country ' . $country . '. Error: ' . $status['msg'];
                }
            }

            $type = 'terminal';
            $total_downloaded_locations = LocationsDelivery::calc_downloaded_locations($type);
            $total_pages = ceil((int)$total_downloaded_locations / (int)LocationsDelivery::$save_per_page);
            $debug_msg = 'Successfully donwloaded terminal delivery locations. Total ' . $total_downloaded_locations . ' in ' . $total_pages . ' pages';
            Debug::to_log($debug_msg, 'cronjob', true);
            $total_added = 0;
            $total_updated = 0;
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                sleep(5);
                set_time_limit(30);
                $result = LocationsDelivery::save_downloaded_locations($type, $i);
                if ( $result['status'] == 'OK' ) {
                    $total_added += $result['added'];
                    $total_updated += $result['updated'];
                } else {
                    $debug_msg = 'Error when saving terminal delivery locations: ' . $result['msg'];
                    Debug::to_log($debug_msg, 'cronjob', true);
                }
            }
            LocationsDelivery::finish_locations_update($type);
            $debug_msg = 'Successfully updated terminal delivery locations. Total ' . $status['total'] . ', failed ' . $status['failed'] . ', added ' . $total_added . ', updated ' . $total_updated;
        } catch (\Exception $e) {
            $debug_msg = 'Exception has occurred. Error: ' . $e->getMessage();
        }
        Debug::to_log($debug_msg, 'cronjob', true);
        Helper::delete_hrx_option('cron_progress_delivery_locs');
    }

    public function job_test()
    {
        // Write to this function and enable it in public $cronjobs for testing cronjob
        $debug_msg = 'Testing...';

        Debug::to_log($debug_msg, 'cronjob', true);
    }
}
