<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
  exit;
}

use HrxDeliveryWoo\Helper;
use HrxDeliveryWoo\Sql;
use HrxDeliveryWoo\PluginUpdate;

class Core
{
    public $id;
    public $title;
    public $version;
    public $option_prefix;
    public $api_url;
    public $update;
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
        $this->update = ($params['update']) ?? array();
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

        $classPluginUpdate = new PluginUpdate($this->version);
        $classPluginUpdate->exec_all();

        $this->set_methods(($params['methods']) ?? array());
        $this->pages = ($params['pages']) ?? array();
        $this->meta_keys = $this->get_meta_keys();
        $this->settings = $this->get_settings();

        $this->loaded = true;
        self::$instance = $this;
    }

    /**
     * Get this class for use in other classes or methods
     * @since 1.0.0
     *
     * @return (class) - This class
     */
    public static function get_instance() {
        return self::$instance;
    }

    /**
     * Get path or URL to plugin file
     * @since 1.0.0
     *
     * @param (string) $file - File name with extension
     * @param (string) $file_type - File type (key) from this class element "structure"
     * @param (boolean) $get_url - Specify to get URL or path
     * @return (string) - The full path or URL to the file
     */
    public function get_file_path( $file, $file_type, $get_url = false )
    {
        $path = ($get_url) ? $this->structure->url : $this->structure->path;

        if ( isset($this->structure->{$file_type}) ) {
            $path .= $this->structure->{$file_type};
        }

        return $path . $file;
    }

    /**
     * Create folders for temporary files
     * @since 1.0.0
     */
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

    /**
     * Get saved settings from "Woocommerce" > "Settings" > "Shipping" > "HRX delivery" page
     * @since 1.0.0
     *
     * @param (string) $get_option - Get specific setting
     * @return (mixed) - All settings or specific single setting
     */
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

    public function set_methods( $methods )
    {
        $this->methods = $methods;

        foreach ( $this->methods as $method_key => $method_data ) {
            $this->methods[$method_key]['countries'] = $this->get_available_countries($method_key);
        }
    }

    public function get_available_countries( $get_for_type )
    {
        $available_countries = array();
        $saved_countries = get_option($this->option_prefix . '_countries');
        
        if ( ! is_array($saved_countries) ) {
            $saved_countries = array();
        }

        if ( empty($saved_countries) || empty($saved_countries[$get_for_type]) ) {
            $available_countries = $this->refresh_available_countries($get_for_type);
            $saved_countries[$get_for_type] = $available_countries;
            update_option($this->option_prefix . '_countries', $saved_countries);
        } else {
            $available_countries = $saved_countries[$get_for_type];
        }

        return $available_countries;
    }

    public function refresh_available_countries( $get_for_type )
    {
        $available_countries = array();
        if ( ! Sql::if_table_exists('delivery') ) {
            return $available_countries;
        }
        $available_countries_sql = Sql::get_columns_unique_values('delivery', 'country', array('type' => $get_for_type, 'active' => 1));

        if ( empty($available_countries_sql) ) {
            return $available_countries;
        }

        foreach ( $available_countries_sql as $sql_result_row ) {
            if ( isset($sql_result_row->country) ) {
                $available_countries[] = $sql_result_row->country;
            } elseif ( isset($sql_result_row['country']) ) {
                $available_countries[] = $sql_result_row['country'];
            }
        }

        sort($available_countries);
        return $available_countries;
    }

    /**
     * Execute functions when plugin is activating
     * @since 1.0.0
     */
    public function plugin_activated()
    {
        Sql::create_tables();
    }

    /**
     * Execute functions when plugin is deactivating
     * @since 1.0.0
     */
    public function plugin_deactivated()
    {

    }

    /**
     * Show message in plugins list, when new plugin version is released
     * @since 1.0.0
     *
     * @param (string) $file - Plugin main file name
     * @param (array) $plugin - Plugin file data
     */
    public function update_message( $file, $plugin )
    {
        $check_update = $this->check_update($plugin['Version']);

        if ( $check_update ) {
            $txt_available = sprintf(__('A newer version of the plugin (%s) has been released.', 'hrx-delivery'), '<a href="' . $check_update['url'] . '" target="_blank">v' . $check_update['version'] . '</a>');
            
            $txt_download = '';
            if ( ! empty($this->update['download_url']) ) {
                $txt_download = sprintf(__('You can download it by pressing %s.', 'hrx-delivery'), '<a href="' . $this->update['download_url'] . '">' . _x('here', 'Press here', 'hrx-delivery') . '</a>');
            }

            $txt_custom_changes = '';
            if ( ! empty($this->custom_changes) ) {
                $txt_custom_changes = '<br/><strong style="color:red;">' . __('We do not recommend update the plugin, because your plugin have changes that is not included in the update', 'hrx-delivery') . ':</strong>';
                foreach ( $this->custom_changes as $change ) {
                    $txt_custom_changes .= '<br/>· ' . $change . '';
                }
            }

            ob_start();
            ?>
            <tr class="plugin-update-tr installer-plugin-update-tr js-otgs-plugin-tr active">
                <td class="plugin-update" colspan="100%">
                    <div class="update-message notice inline notice-warning notice-alt">
                        <p>
                            <?php echo $txt_available; ?>
                            <?php echo $txt_download; ?>
                            <?php echo $txt_custom_changes; ?>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
            $message_html = ob_get_contents();
            ob_end_clean();

            echo $message_html;
        }
    }

    /**
     * Check for a new version of the plugin
     * @since 1.0.0
     *
     * @param (string) $current_version - Current plugin version
     * @return (array|boolean) - New version information or false if new version could not be detected
     */
    private function check_update( $current_version = '' )
    {
        if ( empty($this->update['check_url']) ) {
            return false;
        }

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, esc_html($this->update['check_url']));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Awesome-Octocat-App');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        $response_data = json_decode(curl_exec($ch)); 
        curl_close($ch);

        if ( isset($response_data->tag_name) ) {
            $update_info = array(
                'version' => str_replace('v', '', $response_data->tag_name),
                'url' => (isset($response_data->html_url)) ? $response_data->html_url : '#',
            );
            if ( empty($current_version) ) {
                $main_file_path = $this->structure->path . $this->structure->filename;
                $plugin_data = get_file_data($main_file_path, array('Version' => 'Version'), false);
                $current_version = $plugin_data['Version'];
            }
            
            return (version_compare($current_version, $update_info['version'], '<')) ? $update_info : false;
        }

        return false;
    }

    /**
     * Build plugin files structure object to set for this class "structure" element
     * @since 1.0.0
     *
     * @param (string) $main_file - Full path to plugin main file
     * @return (object) - List of plugin directories and files
     */
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

    /**
     * Get the meta keys used by this plugin
     * @since 1.0.0
     * 
     * @return (object) - List of plugin meta keys
     */
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
            'dimensions' => $prefix . '_dimensions',
        );
    }
}
