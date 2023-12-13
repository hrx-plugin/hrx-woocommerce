<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

class LocationsHelper
{
    public static function add_info_to_list_elems( $list, $add_info = array(), $allow_override = false )
    {
        $changed_list = array();
        foreach ( $list as $elem_key => $elem_data ) {
            $elem = (array)$elem_data;
            foreach ( $add_info as $info_key => $info_value ) {
                if ( ! $allow_override && isset($elem[$info_key]) ) {
                    continue;
                }
                $elem[$info_key] = $info_value;
            }
            $changed_list[$elem_key] = (object)$elem;
        }

        return $changed_list;
    }

    public static function calculate_distance_between_points( $latitude_from, $longitude_from, $latitude_to, $longitude_to, $unit = 'km' )
    {
        switch ($unit) {
            case 'km': //kilometers
                $earth_radius = 6371;
            case 'mi': //miles
                $earth_radius = 3959;
                break;
            default: //meters
                $earth_radius = 6371000;
                break;
        }

        $lat_from = deg2rad($latitude_from);
        $lon_from = deg2rad($longitude_from);
        $lat_to = deg2rad($latitude_to);
        $lon_to = deg2rad($longitude_to);

        $lat_delta = $lat_to - $lat_from;
        $lon_delta = $lon_to - $lon_from;

        $angle = 2 * asin(sqrt(pow(sin($lat_delta / 2), 2) + cos($lat_from) * cos($lat_to) * pow(sin($lon_delta / 2), 2)));

        return $angle * $earth_radius;
    }

    public static function get_coordinates_by_address( $address, $country )
    {
        $url = 'https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates';
        $query_array = array(
            'f' => 'pjson',
            'maxLocations' => 1,
            'forStorage' => 'false',
            'singleLine' => $address,
            'sourceCountry' => $country,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($query_array));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    
        $responseJson = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($responseJson, true);

        if ( empty($response['candidates']) ) {
            return false;
        }

        return array(
            'latitude' => $response['candidates'][0]['location']['y'],
            'longitude' => $response['candidates'][0]['location']['x'],
        );
    }
}
