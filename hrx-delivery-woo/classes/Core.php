<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Sql;

class Core
{
    public $id;
    public $title;
    public $version;
    public $option_prefix;
    public $api_url;
    public $custom_changes;
    public $structure;
    public $methods;
    public $pages;
    public $meta_keys;
    public $settings;
    public $loaded = false;

    public static $instance; // The class is a singleton.

    public function __construct( $params )
    {
        $this->id = ($params['id']) ?? 'hrx';
        $this->title = ($params['title']) ?? '[hrx_plugin_title]';
        $this->version = ($params['version']) ?? '0.0.0';
        $this->option_prefix = ($params['option_prefix']) ?? 'hrx';
        $this->api_url = ($params['api_url']) ?? '';
        $this->custom_changes = ($params['custom_changes']) ?? array();

        try {
            $this->structure = (isset($params['main_file'])) ? $this->get_files_structure($params['main_file']) : array();

            if ( isset($params['main_file']) ) {
                register_activation_hook($params['main_file'], array($this, 'plugin_activated'));
                register_deactivation_hook($params['main_file'], array($this, 'plugin_deactivated'));
            }
        } catch (\Exception $e) {
            Helper::show_admin_message($e->getMessage(), 'error', $this->title);
            return;
        }

        $this->methods = ($params['methods']) ?? array();
        $this->pages = ($params['pages']) ?? array();
        $this->meta_keys = $this->get_meta_keys();
        $this->settings = $this->get_settings();

        $this->loaded = true;
        self::$instance = $this;
    }

    public static function get_instance() {
        return self::$instance;
    }

    public function get_file_path( $file, $file_type, $get_url = false )
    {
        $path = ($get_url) ? $this->structure->url : $this->structure->path;

        if ( isset($this->structure->{$file_type}) ) {
            $path .= $this->structure->{$file_type};
        }

        return $path . $file;
    }

    public function prepare_temp_dirs()
    {
        $directories = array('logs', 'pdf', 'debug');
        
        try {
            $temp_dir = $this->structure->path . $this->structure->temp;

            if ( ! file_exists($temp_dir) ) {
                mkdir($temp_dir, 0755, true);
            }

            foreach ( $directories as $dir ) {
                if ( ! file_exists($temp_dir . $dir) ) {
                    mkdir($temp_dir . $dir, 0755, true);
                }
            }
        } catch (\Exception $e) {
            $error_msg = __('Failed to create temporary files directories', 'hrx-delivery') . '. ' . __('Error', 'hrx-delivery') . ': ' . $e->getMessage();
            Helper::show_admin_message($error_msg, 'error', $this->title);
        }
    }

    public function get_settings( $get_option = false )
    {
        $options = get_option('woocommerce_' . $this->id . '_settings', false);

        if ( ! $options ) {
            return false;
        }

        if ( $get_option !== false ) {
            return $options[$get_option] ?? '';
        }

        return $options;
    }

    public function plugin_activated()
    {
        Sql::create_tables();
    }

    public function plugin_deactivated()
    {

    }

    private function get_files_structure( $main_file )
    {
        if ( empty($main_file) ) {
            throw new \Exception(__('Failed to get the path of the main file', 'hrx-delivery'));
        }

        return (object)array(
            'path' => plugin_dir_path($main_file),
            'url' => plugin_dir_url($main_file),
            'basename' => plugin_basename($main_file),
            'dirname' => dirname(plugin_basename($main_file)),
            'filename' => basename($main_file),
            'includes' => 'includes/',
            'js' => 'assets/js/',
            'css' => 'assets/css/',
            'img' => 'assets/img/',
            'temp' => 'var/',
        );
    }

    private function get_meta_keys()
    {
        $prefix = $this->id;

        return (object)array(
            'method' => $prefix . '_method',
            'terminal_id' => $prefix . '_terminal_id',
            'warehouse_id' => $prefix . '_warehouse_id',
            'order_id' => $prefix . '_order_id',
            'order_status' => $prefix . '_order_status',
            'track_number' => $prefix . '_track_number',
            'track_url' => $prefix . '_track_url',
            'error_msg' => $prefix . '_error_msg',
        );
    }
}
