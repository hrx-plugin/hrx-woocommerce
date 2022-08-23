<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

class Sql
{
    private static function get_table_name( $table )
    {
        $prefix = 'hrx_';
        $table_names = array(
            'delivery' => 'delivery_locations',
            'pickup' => 'pickup_locations',
        );

        return (isset($table_names[$table])) ? $prefix . $table_names[$table] : $prefix . $table;
    }

    public static function create_tables()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
    
        $table_name = $wpdb->prefix . self::get_table_name('delivery');
        if ( $wpdb->get_var("SHOW TABLES LIKE '" . $table_name . "'") != $table_name ) {
            $sql = "CREATE TABLE $table_name (
                id INT(11) NOT NULL auto_increment COMMENT 'Row ID',
                location_id VARCHAR(50) NOT NULL COMMENT 'Location ID in API',
                country VARCHAR(10) COMMENT 'Location country code',
                address VARCHAR(255) COMMENT 'Location address',
                city VARCHAR(255) COMMENT 'Location city',
                postcode VARCHAR(10) COMMENT 'Location postcode',
                latitude VARCHAR(20) COMMENT 'Location latitude',
                longitude VARCHAR(20) COMMENT 'Location longitude',
                active TINYINT(1) DEFAULT 1 NOT NULL COMMENT 'If in newest update of locations this value still exists',
                params TEXT COMMENT 'Other location parameters',
                PRIMARY KEY (id),
                UNIQUE KEY location_id (location_id)
            ) $charset_collate;";
            dbDelta($sql);
        }

        $table_name = $wpdb->prefix . self::get_table_name('pickup');
        if ( $wpdb->get_var("SHOW TABLES LIKE '" . $table_name . "'") != $table_name ) {
            $sql = "CREATE TABLE $table_name (
                id INT(11) NOT NULL auto_increment COMMENT 'Row ID',
                location_id VARCHAR(50) NOT NULL COMMENT 'Location ID in API',
                name VARCHAR(255) COMMENT 'Location name',
                country VARCHAR(10) COMMENT 'Location country code',
                address VARCHAR(255) COMMENT 'Location address',
                city VARCHAR(255) COMMENT 'Location city',
                postcode VARCHAR(10) COMMENT 'Location postcode',
                active TINYINT(1) DEFAULT 1 NOT NULL COMMENT 'If in newest update of locations this value still exists',
                params TEXT COMMENT 'Other location parameters',
                PRIMARY KEY (id),
                UNIQUE KEY location_id (location_id)
            ) $charset_collate;";
            dbDelta($sql);
        }
    }

    public static function insert_row( $table, $data )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::get_table_name($table);

        return $wpdb->insert($table_name, $data);
    }

    public static function update_row( $table, $data, $where )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::get_table_name($table);

        return $wpdb->update($table_name, $data, $where);
    }

    public static function delete_row( $table, $where )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::get_table_name($table);

        return $wpdb->delete($table_name, $where);
    }

    public static function get_row( $table, $where )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::get_table_name($table);
        $sql_where = self::prepare_where($where);

        return $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE " . $sql_where);
    }

    public static function get_multi_rows( $table, $where )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::get_table_name($table);
        $sql_where = (! empty($where)) ? " WHERE " . self::prepare_where($where) : '';

        return $wpdb->get_results("SELECT * FROM " . $table_name . $sql_where);
    }

    public static function update_multi_rows( $table, $data, $where )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::get_table_name($table);
        $sql_set = self::prepare_where($data);
        $sql_where = (! empty($where)) ? " WHERE " . self::prepare_where($where) : '';

        return $wpdb->query("UPDATE ". $table_name . " SET " . $sql_set . $sql_where);
    }

    private static function prepare_where( $values, $operation = 'AND' )
    {
        $where = "";
        if ( empty($values) ) {
            return $where;
        }
        
        foreach ( $values as $column => $value ) {
            if ( ! empty($where) ) {
                $where .= " " . $operation . " ";
            }
            $where .= esc_sql($column) . "='" . esc_sql($value) . "'";
        }

        return $where;
    }

    private static function prepare_set( $values )
    {
        $set = "";
        foreach ( $values as $column => $value ) {
            if ( ! empty($set) ) {
                $set .= ", ";
            }
            $set .= esc_sql($column) . "='" . esc_sql($value) . "'";
        }

        return $set;
    }
}
