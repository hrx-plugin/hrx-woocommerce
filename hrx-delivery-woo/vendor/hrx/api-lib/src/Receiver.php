<?php
namespace HrxApi;

use HrxApi\Helper;

class Receiver
{
    /* Class variables */
    private $name;
    private $email;
    private $phone;
    private $address;
    private $postcode;
    private $city;
    private $country;
    
    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct()
    {

    }

    /**
     * Set receiver name
     * @since 1.0.0
     *
     * @param (string) $name - Receiver name
     * @return (object) - Edited this class object
     */
    public function setName( $name )
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get receiver name
     * @since 1.0.0
     * 
     * @return (string) - Receiver name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set receiver email
     * @since 1.0.0
     *
     * @param (string) $email - Receiver email
     * @return (object) - Edited this class object
     */
    public function setEmail( $email )
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get receiver email
     * @since 1.0.0
     * 
     * @return (string) - Receiver email
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set receiver phone
     * @since 1.0.0
     *
     * @param (string) $phone - Receiver phone
     * @param (string) $regex - Regex value by which the phone need check
     * @return (object) - Edited this class object
     */
    public function setPhone( $phone, $regex = '' )
    {
        if ( ! empty($regex) && ! Helper::checkRegex( $phone, $regex ) ) {
            $error_message = 'Bad phone number format';
            if ( substr($phone, 0, 1) == '+' ) {
                $error_message .= '. Phone number must be without code';
            }
            Helper::throwError($error_message);
        }

        $this->phone = $phone;

        return $this;
    }

    /**
     * Get receiver phone
     * @since 1.0.0
     * 
     * @return (string) - Receiver phone
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set receiver address (street, house, apartment)
     * @since 1.0.2
     *
     * @param (string) $address - Receiver address
     * @return (object) - Edited this class object
     */
    public function setAddress( $address )
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get receiver address
     * @since 1.0.2
     * 
     * @return (string) - Receiver address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set receiver postcode (Zip code)
     * @since 1.0.2
     *
     * @param (string) $postcode - Receiver postcode
     * @return (object) - Edited this class object
     */
    public function setPostcode( $postcode )
    {
        $this->postcode = $postcode;

        return $this;
    }

    /**
     * Get receiver postcode
     * @since 1.0.2
     * 
     * @return (string) - Receiver postcode
     */
    public function getPostcode()
    {
        return $this->postcode;
    }

    /**
     * Set receiver city
     * @since 1.0.2
     *
     * @param (string) $city - Receiver city
     * @return (object) - Edited this class object
     */
    public function setCity( $city )
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get receiver city
     * @since 1.0.2
     * 
     * @return (string) - Receiver city
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set receiver country
     * @since 1.0.2
     *
     * @param (string) $country - Receiver country code
     * @return (object) - Edited this class object
     */
    public function setCountry( $country )
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get receiver country
     * @since 1.0.2
     * 
     * @return (string) - Receiver country code
     */
    public function getCountry()
    {
        return $this->country;
    }
}
