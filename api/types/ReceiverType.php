<?php

require_once(dirname(__FILE__) . '/AddressType.php');

class ReceiverType
{
    /**
     * @param string
     */
    private $name = '';

    /**
     * @param string
     */
    private $companyName = '';

    /**
     * @param AddressType
     */
    private $address;

    /**
     * @param string
     */
    private $phone = '';

    /**
     * @param string
     */
    private $email = '';

    /**
     * @param string
     */
    private $terminalId = '';

    /**
     * @param string
     */
    private $postalCode = '';

    private $needsPostOfficeParam = false;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setCompanyName($companyName)
    {
        $this->companyName = $companyName;
    }

    public function setAddress(AddressType $addressType)
    {
        $this->address = $addressType;
    }

    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function setTerminalId($id)
    {
        $this->terminalId = $id;
    }

    public function setNeedOfficeParam($isNeeded)
    {
        $this->needsPostOfficeParam = $isNeeded;
    }

    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;
    }

    /**
     * Get Formed AddressType for LP API
     * 
     * @param string $addressType
     * 
     * @return array
     */
    public function getFormedReceiverType()
    {
        $receiver = [
            'name' => $this->name,
            'companyName' => $this->companyName,
            'address' => $this->address->getCurrentTypeAddress(),
            'phone' => $this->phone,
            'email' => $this->email
        ];

        if (!empty(trim($this->terminalId))) {
            $receiver['terminalId'] = $this->terminalId;
        } elseif ($this->needsPostOfficeParam) {
            $receiver['postOfficeAddress'] = $this->address->getPostOfficeAddress();
        }

        return $receiver;
    }
}
