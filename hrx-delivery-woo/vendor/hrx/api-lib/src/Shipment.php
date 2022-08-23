<?php
namespace HrxApi;

class Shipment
{
    /* Class variables */
    private $reference;
    private $comment;
    private $length;
    private $width;
    private $height;
    private $weight;
    
    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct()
    {

    }

    /**
     * Set reference
     * @since 1.0.0
     *
     * @param (string) $reference - Reference
     * @return (object) - Edited this class object
     */
    public function setReference( $reference )
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * Get reference
     * @since 1.0.0
     * 
     * @return (string) - Reference
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * Set comment
     * @since 1.0.0
     *
     * @param (string) $comment - Comment
     * @return (object) - Edited this class object
     */
    public function setComment( $comment )
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Get comment
     * @since 1.0.0
     * 
     * @return (string) - Comment
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set shipment length
     * @since 1.0.0
     *
     * @param (integer|float) $length - Shipment length in cm
     * @return (object) - Edited this class object
     */
    public function setLength( $length )
    {
        $this->length = $length;

        return $this;
    }

    /**
     * Get shipment length
     * @since 1.0.0
     * 
     * @return (integer|float) - Shipment length in cm
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Set shipment width
     * @since 1.0.0
     *
     * @param (integer|float) $width - Shipment width in cm
     * @return (object) - Edited this class object
     */
    public function setWidth( $width )
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Get shipment width
     * @since 1.0.0
     * 
     * @return (integer|float) - Shipment width in cm
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Set shipment height
     * @since 1.0.0
     *
     * @param (integer|float) $height - Shipment height in cm
     * @return (object) - Edited this class object
     */
    public function setHeight( $height )
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get shipment height
     * @since 1.0.0
     * 
     * @return (integer|float) - Shipment height in cm
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Set shipment weight
     * @since 1.0.0
     *
     * @param (integer|float) $weight - Shipment weight in kg
     * @return (object) - Edited this class object
     */
    public function setWeight( $weight )
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * Get shipment weight
     * @since 1.0.0
     * 
     * @return (integer|float) - Shipment weight in kg
     */
    public function getWeight()
    {
        return $this->weight;
    }
}
