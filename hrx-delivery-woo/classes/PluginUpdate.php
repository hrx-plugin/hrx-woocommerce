<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use HrxDeliveryWoo\Sql;
use \Exception;

class PluginUpdate
{
    private $last_version;
    private $current_version;
    private $all_updates = array();
    private $need_updates = array();
    private $option_names = array(
        'version' => 'hrx_version',
    );

    public function __construct( $current_version )
    {
        $this->current_version = $current_version;

        $this->set_last_version();
        $this->set_all_updates_list();
        $this->set_required_updates_list();
    }

    public function exec_single( $version )
    {
        if ( ! isset($this->get_required_updates_list()[$version]) ) {
            return;
        }

        return call_user_func(array($this, $this->get_required_updates_list()[$version]));
    }

    public function exec_all()
    {
        foreach ( $this->get_required_updates_list() as $version => $method ) {
            $result = $this->exec_single($version);
            if ( ! $result ) {
                throw new Exception();
            }
        }
    }

    private function set_last_version()
    {
        $this->last_version = get_option($this->option_names['version']);
        if ( empty($this->last_version) ) {
            $this->last_version = '1.1.0.0'; // This class created from version 1.1.0
        }
    }

    private function set_required_updates_list()
    {
        if ( ! version_compare($this->last_version, $this->current_version, '<') ) {
            return;
        }

        foreach ( $this->get_all_updates_list() as $version => $method ) {
            if ( version_compare($this->last_version, $version, '<=') ) {
                $this->need_updates[$version] = $method;
            }
        }
    }

    public function get_required_updates_list()
    {
        return $this->need_updates;
    }

    private function set_all_updates_list()
    {
        $all_updates_methods = preg_grep('/^update_/', get_class_methods($this));
        $all_versions = array();
        $temp_updates_array = array();
        foreach ( $all_updates_methods as $method ) {
            $version = $this->get_version_from_method_name($method);
            if ( ! empty($version) ) {
                $temp_updates_array[$version] = $method;
                $all_versions[] = $version;
            }
        }

        usort($all_versions, 'version_compare');
        foreach ( $all_versions as $version ) {
            $this->all_updates[$version] = $temp_updates_array[$version];
        }
    }

    public function get_all_updates_list()
    {
        return $this->all_updates;
    }

    private function get_version_from_method_name( $method_name )
    {
        $version = str_replace('update_', '', $method_name);
        $readable_version = str_replace('_', '.', $version);

        return $readable_version;
    }

    private function mark_version_updated( $version )
    {
        update_option($this->option_names['version'], $version);
    }

    private function update_1_1_1_0()
    {
        $version = $this->get_version_from_method_name(__FUNCTION__);
        try {
            $result = Sql::add_new_column('delivery', 'type', "VARCHAR(20) COMMENT 'Location type'");
            if ( ! $result ) {
                throw new Exception(__('Failed to add SQL table column','hrx-delivery'));
            }
        } catch( Exception $e ) {
            throw new Exception(sprintf(__('Error when executing update %1$s. Error: %2$s.','hrx-delivery'), $version, $e->getMessage()));
        }

        $this->mark_version_updated($version);
        return true;
    }
}
