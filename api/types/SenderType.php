<?php

require_once(dirname(__FILE__) . '/AddressType.php');

class SenderType
{
    /**
     * @param AddressType
     */
    private $address = null;

    /**
     * @param string
     */
    private $name = '';
    
    /**
     * @param string
     */
    private $phone = '';

    /**
     * @param string
     */
    private $email = '';

    public function __construct()
    {
        
    }

    public function setAddress(AddressType $address)
    {
        $this->address = $address;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getFormedSenderType()
    {
        return [
            'address' => $this->address->getCurrentTypeAddress(),
            'name' => $this->name,
            'phone' => $this->phone,
            'Email' => $this->email,
        ];
    }
}
