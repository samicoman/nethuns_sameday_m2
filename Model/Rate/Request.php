<?php

namespace Nethuns\Sameday\Model\Rate;

use \Magento\Directory\Model\Region;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Quote\Model\Quote\Address\RateRequest;
use \Magento\Store\Model\ScopeInterface;
use \Nethuns\Sameday\Model\Api;
use \Nethuns\Sameday\Model\Carrier;

/**
 * @method int getPickupPoint()
 * @method Request setPickupPoint(int $value)
 * @method int getContactPerson()
 * @method Request setContactPerson(int $value)
 * @method int getPackageType()
 * @method Request setPackageType(int $value)
 * @method int getPackageNumber()
 * @method Request setPackageNumber(int $value)
 * @method float getPackageWeight()
 * @method Request setPackageWeight(float $value)
 * @method int getService()
 * @method Request setService(int $value)
 * @method int getAwbPayment()
 * @method Request setAwbPayment(int $value)
 * @method float getCashOnDelivery()
 * @method Request setCashOnDelivery(float $value)
 * @method float getInsuredValue()
 * @method Request setInsuredValue(float $value)
 * @method int getThirdPartyPickup()
 * @method Request setThirdPartyPickup(int $value)
 * @method int getDeliveryInterval()
 * @method Request setDeliveryInterval(int $value)
 * @method string getClientObservation()
 * @method Request setClientObservation(string $value)
 * @method array getAwbRecipient()
 * @method array getParcels()
 * @method array getServiceTaxes()
 *
 */
class Request extends \Magento\Framework\DataObject
{
    const CONFIG_DEFAULT_PACKAGE_TYPE = 'carriers/nethunssameday/package_type';
    const CONFIG_DEFAULT_PACKAGE_HEIGHT = 'carriers/nethunssameday/default_height';
    const CONFIG_DEFAULT_PACKAGE_LENGTH = 'carriers/nethunssameday/default_length';
    const CONFIG_DEFAULT_PACKAGE_WIDTH = 'carriers/nethunssameday/default_width';
    const CONFIG_DEFAULT_PACKAGE_WEIGHT = 'carriers/nethunssameday/default_weight';

    protected $_packageType;

    protected $_returnPapers;
    protected $_repack;
    protected $_exchangePackage;
    protected $_openPackage;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var Api */
    protected $api;

    /** @var Region */
    protected $directoryRegion;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Api $api,
        Region $directoryRegion
    )
    {
        parent::__construct();

        $this->scopeConfig = $scopeConfig;
        $this->api = $api;
        $this->directoryRegion = $directoryRegion;

        $this->_returnPapers = $this->scopeConfig->getValue(
            Carrier::CONFIG_RETURN_PAPERS_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
        $this->_repack = $this->scopeConfig->getValue(
            Carrier::CONFIG_REPACK_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
        $this->_exchangePackage = $this->scopeConfig->getValue(
            Carrier::CONFIG_EXCHANGE_PACKAGE_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
        $this->_openPackage = $this->scopeConfig->getValue(
            Carrier::CONFIG_OPEN_PACKAGE_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @param RateRequest $request
     */
    public function setAwbRecipient($request)
    {
        $data = array();

        $region = $this->directoryRegion->load($request->getDestRegionId())->getName();
        $response = $this->api->request(
            API::METHOD_GEOLOCATION_COUNTY,
            \Zend_Http_Client::GET,
            array(),
            array('name' => $region)
        );
        $data['county'] = $response['data'][0]['id'];

        $city = $request->getDestCity();
        $response = $this->api->request(
            API::METHOD_GEOLOCATION_CITY,
            \Zend_Http_Client::GET,
            array(),
            array('name' => $city, 'county' => $response['data'][0]['id'], 'address' => $request->getDestStreet())
        );
        $data['city'] = $response['data'][0]['id'];

        $data['address'] = $request->getDestStreet() . ' ' . $request->getDestStreetLine2();
        $data['name'] = $request->getDestPersonName() ? $request->getDestPersonName() : 'Dummy';
        $data['phoneNumber'] = $request->getDestPhoneNumber() ? $request->getDestPhoneNumber() : '0123456789';
        $data['personType'] = Carrier::PERSON_TYPE_INDIVIDUAL;

        $this->setData('awb_recipient', $data);
    }

    /**
     * @param RateRequest $request
     */
    public function setParcels($request)
    {
        $data = array();

        $parcel = array(
            'height' => $request->getPackageHeight() ? $request->getPackageHeight() : $this->getDefaultHeight(),
            'length' => $request->getPackageDepth() ? $request->getPackageDepth() : $this->getDefaultLength(),
            'width' => $request->getPackageWidth() ? $request->getPackageWidth() : $this->getDefaultWidth(),
            'weight' => $request->getPackageWeight() ? $request->getPackageWeight() : $this->getDefaultWeight()
        );
        $data[] = $parcel;

        $this->setData('parcels', $data);
    }

    /**
     *
     */
    public function setServiceTaxes()
    {
        $data = array();

        $response = $this->api->request(
            Api::METHOD_CLIENT_SERVICES,
            \Zend_Http_Client::GET
        );

        foreach ($response['data'] as $service) {
            if ($service['id'] != $this->getService()) {
                continue;
            }

            foreach ($service['serviceOptionalTaxes'] as $tax) {
                if ($tax['packageType'] != $this->getPackageType()) {
                    continue;
                }

                switch ($tax['name']) {
                    case 'Deschidere Colet':
                        if ($this->_openPackage) {
                            $data[] = $tax['id'];
                        }
                        break;
                    case 'Reambalare':
                        if ($this->_repack) {
                            $data[] = $tax['id'];
                        }
                        break;
                    case 'Colet la schimb':
                        if ($this->_exchangePackage) {
                            $data[] = $tax['id'];
                        }
                        break;
                    case 'Retur Documente':
                        if ($this->_returnPapers) {
                            $data[] = $tax['id'];
                        }
                        break;
                }
            }
        }

        $this->setData('service_taxes', $data);
    }

    /**
     * @return array
     */
    public function exportData()
    {
        $data = $this->getData();
        $response = array();
        foreach ($data as $key => $value) {
            $response[lcfirst(str_replace('_', '', ucwords($key, '_')))] = $value;
        }

        return $response;
    }

    /**
     * @return int
     */
    public function getConfigPackageType()
    {
        return $this->_packageType
            ? $this->_packageType
            : $this->scopeConfig->getValue(
                self::CONFIG_DEFAULT_PACKAGE_TYPE,
                ScopeInterface::SCOPE_WEBSITE
            );
    }

    /**
     * @return int
     */
    public function getDefaultHeight()
    {
        $default = $this->scopeConfig->getValue(
            self::CONFIG_DEFAULT_PACKAGE_HEIGHT,
            ScopeInterface::SCOPE_WEBSITE
        );

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Carrier::PACKAGE_TYPE_ENVELOPE:
                $default = 1;
                break;
            case Carrier::PACKAGE_TYPE_REGULAR:
                $default = 25;
                break;
            case Carrier::PACKAGE_TYPE_LARGE:
                $default = 100;
                break;
            default:
                $default = 50;
                break;
        }

        return $default;
    }

    /**
     * @return int
     */
    public function getDefaultLength()
    {
        $default = $this->scopeConfig->getValue(
            self::CONFIG_DEFAULT_PACKAGE_LENGTH,
            ScopeInterface::SCOPE_WEBSITE
        );

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Carrier::PACKAGE_TYPE_ENVELOPE:
                $default = 30;
                break;
            case Carrier::PACKAGE_TYPE_REGULAR:
                $default = 25;
                break;
            case Carrier::PACKAGE_TYPE_LARGE:
                $default = 100;
                break;
            default:
                $default = 50;
                break;
        }

        return $default;
    }

    /**
     * @return int
     */
    public function getDefaultWidth()
    {
        $default = $this->scopeConfig->getValue(
            self::CONFIG_DEFAULT_PACKAGE_WIDTH,
            ScopeInterface::SCOPE_WEBSITE
        );

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Carrier::PACKAGE_TYPE_ENVELOPE:
                $default = 20;
                break;
            case Carrier::PACKAGE_TYPE_REGULAR:
                $default = 25;
                break;
            case Carrier::PACKAGE_TYPE_LARGE:
                $default = 100;
                break;
            default:
                $default = 50;
                break;
        }

        return $default;
    }

    /**
     * @return int|float
     */
    public function getDefaultWeight()
    {
        $default = $this->scopeConfig->getValue(
            self::CONFIG_DEFAULT_PACKAGE_WEIGHT,
            ScopeInterface::SCOPE_WEBSITE
        );

        if ($default) {
            return $default;
        }

        switch ($this->getConfigPackageType()) {
            case Carrier::PACKAGE_TYPE_ENVELOPE:
                $default = 0.5;
                break;
            case Carrier::PACKAGE_TYPE_REGULAR:
                $default = 3;
                break;
            case Carrier::PACKAGE_TYPE_LARGE:
                $default = 50;
                break;
            default:
                $default = 5;
                break;
        }

        return $default;
    }
}