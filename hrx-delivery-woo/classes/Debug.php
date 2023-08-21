<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use HrxDeliveryWoo\Core;

class Debug
{
    public static function develop( $variable, $echo = true )
    {
        $output = '<style>
            pre.hrx-dev {
                position: fixed;
                z-index: 9990;
                background: #fff;
                padding: 5px 10px;
                max-height: 60%;
                right: 10px;
                top: 10%;
                overflow: auto;
                max-width: 33%;
                box-shadow: 0 0 2px 2px #999;
                opacity: 0.7;
                margin: 0;
            }
            pre.hrx-dev:hover {
                opacity: 1;
                z-index: 9999;
            }
        </style>';
        
        $output .= '<pre class="hrx-dev">';
        $output .= print_r($variable, true);
        $output .= '</pre>';

        if ( ! $echo ) {
            return $output;
        }

        echo $output;
    }

    public static function status_keywords( $get_only = '' )
    {
        $keywords = array(
            'fail' => 'Error',
            'notice' => 'Notice',
            'empty' => 'Empty',
            'good' => 'OK',
            'not' => 'Not applicable',
        );

        if ( ! empty($get_only) && isset($keywords[$get_only]) ) {
            return $keywords[$get_only];
        }

        return $keywords;
    }

    public static function check_plugin()
    {
        $status = array();
        
        $core = Core::get_instance();
        if ( empty($core) ) {
            $status['core'] = $msg_fail;
            return $status;
        }

        $status['id'] = $core->id ?? self::status_keywords('fail');
        $status['title'] = $core->title ?? self::status_keywords('fail');
        $status['version'] = $core->version ?? self::status_keywords('fail');
        $status['changes'] = $core->custom_changes ?? self::status_keywords('fail');
        $status['methods'] = $core->methods ?? self::status_keywords('fail');

        $status['dirs'] = array();
        $path = $core->structure->path ?? '';
        $status['dirs']['js'] = (file_exists($path . $core->structure->js)) ? self::status_keywords('good') : self::status_keywords('fail');
        $status['dirs']['css'] = (file_exists($path . $core->structure->css)) ? self::status_keywords('good') : self::status_keywords('fail');
        $status['dirs']['img'] = (file_exists($path . $core->structure->img)) ? self::status_keywords('good') : self::status_keywords('fail');
        $status['dirs']['temp'] = (file_exists($path . $core->structure->temp)) ? self::status_keywords('good') : self::status_keywords('notice');

        return $status;
    }

    public static function to_log( $data, $log_file_name = 'debug', $debug_always = false )
    {
        $core = Core::get_instance();
        $core->prepare_temp_dirs();

        if ( ! $debug_always && (empty($core->settings['debug_enable']) || $core->settings['debug_enable'] != 'yes') ) {
            return;
        }

        $file_path = self::get_log_path($log_file_name);

        file_put_contents($file_path, self::build_log_text(print_r($data, true)), FILE_APPEND);
    }

    private static function build_log_text( $log_data )
    {
        $log_pref = '[' . current_time("Y-m-d H:i:s") . ']: ';
        return $log_pref . $log_data . PHP_EOL;
    }

    private static function get_log_path( $log_file_name = 'debug' )
    {
        $core = Core::get_instance();

        return $core->structure->path . $core->structure->temp . 'logs/' . esc_attr($log_file_name) . '.log';
    }
}
