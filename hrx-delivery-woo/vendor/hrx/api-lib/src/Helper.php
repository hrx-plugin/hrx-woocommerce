<?php
namespace HrxApi;

class Helper
{
    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct()
    {

    }

    /**
     * Validate string by regex
     * @since 1.0.0
     *
     * @param (string) $string - Checking string
     * @param (string) $regex - Regex expression under which the check is performed
     * @return (boolean) - Result of check
     */
    public static function checkRegex( $string, $regex )
    {
        if ( empty($regex) ) {
            return true;
        }
        if ( substr($regex, 0, 1) != "/" ) {
            $regex = "/" . $regex;
        }

        if ( substr($regex, -1) != "/" ) {
            $regex .= "/";
        }

        return preg_match($regex, $string);
    }

    /**
     * Check if string is json
     * @since 1.0.0
     *
     * @param (string) $string - Checking string
     * @return (boolean) - Result of check
     */
    public static function isJson( $string )
    {
        json_decode($string);
        
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Throw exception
     * @since 1.0.0
     * 
     * @param (string) $error_msg - An error message in the exception
     */
    public static function throwError( $error_msg )
    {
        throw new \Exception($error_msg);
    }

    /**
     * Beautifully print an array of elements
     * @since 1.0.0
     * 
     * @param (array) $debug_rows - Elements array where key is title and value is element
     * @param (boolean) $echo - Print immediately or return
     * @return (string) - Formatted element array printing
     */
    public static function printDebug( $debug_rows = [], $echo = true )
    {
        $output = '<pre>';

        foreach ( $debug_rows as $title => $value ) {
            if ( is_string($title) ) {
                $output .= '<b>' . $title . ':</b><br/>';
            }
            $output .= $value . '<br/><br/>';
        }
        
        $output .= '</pre>';

        if ( $echo ) {
            echo $output;
        } else {
            return $output;
        }
    }
}
