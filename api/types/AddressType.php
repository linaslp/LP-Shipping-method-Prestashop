<?php 

class AddressType
{
    const UNSTRUCTURED_ADDRESS = 1;
    const STRUCTURED_ADDRESS = 2;
    const POST_OFFICE_ADDRESS = 3;
    const POST_OFFICE_BOX_ADDRESS = 4;
    const NOT_LITHUANIAN_ADDRESS = 5;

    /**
     * @param string
     */
    private $postOfficeId = '';

    /**
     * @param string
     */
    private $postOfficeBoxId = '';

    /**
     * @param string
     */
    private $locality = '';

    /**
     * @param string
     */
    private $street = '';

    /**
     * @param string
     */
    private $building = '';

    /**
     * @param string
     */
    private $postalCode = '';

    /**
     * @param string
     */
    private $country = '';

    /**
     * @param string
     */
    private $notLithuanianAddress;

    private $currentAddressType = null;

    public function __construct($address = null)
    {
        if (!$address) {

        }
    }

    public function setPostOfficeId($id)
    {
        $this->postOfficeId = $id;
    }

    public function setPostOfficeBoxId($id)
    {
        $this->postOfficeBoxId = $id;
    }

    public function setLocality($locality)
    {
        $this->locality = $locality;
    }

    public function setStreet($street)
    {
        $this->street = $street;
    }

    public function setBuilding($building)
    {
        $this->building = $building;
    }

    public function setPostalCode($code)
    {
        $this->postalCode = $code;
    }

    public function setCountry($country)
    {
        $this->country = $country;
    }

    public function setNotLithuanianAddress($address)
    {
        $this->notLithuanianAddress = $address;
    }

    public function setCurrentAddressType($addr)
    {
        $this->currentAddressType = $addr;
    }

    public function getCurrentType()
    {
        return $this->currentAddressType;
    }

    /**
     * @return string
     */
    public function getPostOfficeId()
    {
        return $this->postOfficeId;
    }

    /**
     * @return string
     */
    public function getPostOfficeBoxId()
    {
        return $this->postOfficeBoxId;
    }

    /**
     * @return string
     */
    public function getLocality()
    {
        return $this->locality;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getBuilding()
    {
        return $this->building;
    }

    /**
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return null
     */
    public function getCurrentAddressType()
    {
        return $this->currentAddressType;
    }

    public function getPostOfficeAddress()
    {
        if (empty(trim($this->postOfficeId))) {
            return new stdClass();
        }

        if ($this->postOfficeBoxId) {
            return [
                'postOfficeId' => $this->postOfficeId,
                'postOfficeBoxAddress' => $this->getPostOfficeBoxAddress(),
            ];
        }

        return [
            'postOfficeId' => $this->postOfficeId,
        ];
    }

        

    public function getPostOfficeBoxAddress()
    {
        if (empty(trim($this->postOfficeBoxId))) {
            return [];
        }

        return [
            'postOfficeBoxId' => $this->postOfficeBoxId
        ];
    }

    public function getStructuredAddress()
    {
        if (
            empty(trim($this->locality)) || 
            empty(trim($this->street)) || 
            empty(trim($this->building)) || 
            empty(trim($this->postalCode)) ||
            empty(trim($this->country)) 
        ) {
            return [];
        }

        return [
            'locality' => $this->locality,
            'street' => $this->street,
            'building' => $this->building,
            'postalCode' => $this->postalCode,
            'country' => $this->country
        ];
    }

    public function getUnstructuredAddress()
    {
        if (empty(trim($this->locality)) || empty(trim($this->postalCode)) || empty(trim($this->country)) ) {
            return [];
        }

        $addr = $this->street . isset($this->building) ? $this->building : '';
        return [
            'locality' => $this->locality,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'freeFormAddress' => $addr,
        ];
    }

    public function getNotLithuanianAddress()
    {
        if (empty(trim($this->locality)) || empty(trim($this->postalCode)) || empty(trim($this->country) || empty(trim($this->notLithuanianAddress))) ) {
            return [];
        }

        return [
            'locality' => $this->locality,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'address1' => $this->notLithuanianAddress
        ];
    }

    public function getCurrentTypeAddress()
    {
        if ($this->currentAddressType) {
            switch ($this->currentAddressType) {
                case self::UNSTRUCTURED_ADDRESS:
                    return $this->getUnstructuredAddress();
                    break;
                case self::STRUCTURED_ADDRESS:
                    return $this->getStructuredAddress();
                    break;
                case self::POST_OFFICE_ADDRESS:
                    return $this->getPostOfficeAddress();
                    break;
                case self::POST_OFFICE_BOX_ADDRESS:
                    return $this->getPostOfficeBoxAddress();
                    break;
                case self::NOT_LITHUANIAN_ADDRESS:
                    return $this->getNotLithuanianAddress();
                    break;
            }
        }
    }
}
