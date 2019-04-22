<?php

namespace Nethuns\Sameday\Model;

use \Magento\Directory\Model\CountryFactory;
use \Magento\Directory\Model\CurrencyFactory;
use \Magento\Directory\Model\RegionFactory;
use \Magento\Directory\Model\Region;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\DataObject;
use \Magento\Framework\Exception\LocalizedException;
use \Magento\Framework\Xml\Security;
use \Magento\OfflinePayments\Model\Cashondelivery;
use \Magento\Quote\Model\Quote\Address\RateRequest;
use \Magento\Quote\Model\Quote\Address\RateResult\Error;
use \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Shipping\Model\Rate\Result;
use \Magento\Shipping\Model\Rate\ResultFactory;
use \Magento\Shipping\Model\Simplexml\ElementFactory;
use \Magento\Store\Model\ScopeInterface;
use \Magento\Ui\Config\Data;


use \Nethuns\Sameday\Model\Rate\Request;
use \Nethuns\Sameday\Model\Rate\RequestFactory;

use \Psr\Log\LoggerInterface;

class Carrier extends \Magento\Shipping\Model\Carrier\AbstractCarrierOnline implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'nethunssameday';

    const CONFIG_SHIPPING_ORIGIN = 'carriers/nethunssameday/shipping_origin';
    const CONFIG_PACKAGE_TYPE = 'carriers/nethunssameday/package_type';
    const CONFIG_AWB_PAYMENT = 'carriers/nethunssameday/awb_payment';
    const CONFIG_RETURN_PAPERS_SERVICE = 'carriers/nethunssameday/return_papers';
    const CONFIG_REPACK_SERVICE = 'carriers/nethunssameday/repack';
    const CONFIG_EXCHANGE_PACKAGE_SERVICE = 'carriers/nethunssameday/exchange_package';
    const CONFIG_OPEN_PACKAGE_SERVICE = 'carriers/nethunssameday/open_package';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Package types
     */
    const PACKAGE_TYPE_REGULAR = 0;
    const PACKAGE_TYPE_ENVELOPE = 1;
    const PACKAGE_TYPE_LARGE = 2;

    /**
     * Service ID
     */
    const SAMEDAY_DELIVERY = 1;
    const NEXTDAY_DELIVERY = 7;

    /**
     * AWB Payment
     */
    const CLIENT = 1;
    const RECIPIENT = 2;
    const THIRD_PARTY = 3;

    /**
     * Person Type
     */
    const PERSON_TYPE_INDIVIDUAL = 0;
    const PERSON_TYPE_BUSINESS = 1;

    /**
     * Default package number
     */
    const DEFAULT_PACKAGE_NUMBER = 1;

    /**
     * Third party pickup
     */
    const TPP_YES = 1;
    const TPP_NO = 0;

    /**
     * Rate request data
     *
     * @var RateRequest|null
     */
    protected $_request;

    /**
     * Raw rate request data
     *
     * @var Request|null
     */
    protected $_rawRequest = null;

    /**
     * Rate result data
     *
     * @var Result|null
     */
    protected $_result;

    /**
     * @var RequestFactory
     */
    protected $_rawRequestFactory;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Region
     */
    protected $directoryRegion;

    protected $_returnPapers;
    protected $_repack;
    protected $_exchangePackage;
    protected $_openPackage;

    /**
     * Errors placeholder
     *
     * @var string[]
     */
    protected $_errors = [];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        RequestFactory $rawRequestFactory,
        Api $api,
        OrderRepositoryInterface $orderRepository,
        Region $directoryRegion,
        array $data = []
    ) {
        $this->_rawRequestFactory = $rawRequestFactory;
        $this->api = $api;
        $this->orderRepository = $orderRepository;
        $this->directoryRegion = $directoryRegion;

        parent::__construct(
            $scopeConfig, $rateErrorFactory, $logger, $xmlSecurity, $xmlElFactory,
            $rateResultFactory, $rateMethodFactory, $trackFactory, $trackErrorFactory,
            $trackStatusFactory, $regionFactory, $countryFactory, $currencyFactory,
            $directoryData, $stockRegistry, $data
        );

        $this->_returnPapers = $this->_scopeConfig->getValue(
            self::CONFIG_RETURN_PAPERS_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
        $this->_repack = $this->_scopeConfig->getValue(
            self::CONFIG_REPACK_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
        $this->_exchangePackage = $this->_scopeConfig->getValue(
            self::CONFIG_EXCHANGE_PACKAGE_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
        $this->_openPackage = $this->_scopeConfig->getValue(
            self::CONFIG_OPEN_PACKAGE_SERVICE,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Determine whether zip-code is required for the country of destination
     *
     * @param string|null $countryId
     * @return bool
     */
    public function isZipCodeRequired($countryId = null)
    {
        return false;
    }

    /**
     * get allowed methods
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @param Request $request
     * @return $this
     * @api
     */
    public function setRawRequest($request)
    {
        $this->_rawRequest = $request;
        return $this;
    }

    /**
     * Prepare and set request in property of current instance
     *
     * @param RateRequest $request
     * @return $this
     * @throws LocalizedException
     */
    public function setRequest(RateRequest $request)
    {
        $this->_request = $request;

        $requestObject = $this->_rawRequestFactory->create();

        $shippingOriginConfig = $this->_scopeConfig->getValue(
            self::CONFIG_SHIPPING_ORIGIN,
            ScopeInterface::SCOPE_WEBSITE
        );

        if(empty($shippingOriginConfig)) {
            throw new LocalizedException(
                __('Shipping origin is not configured. Please set it in the module settings')
            );
        }

        $shippingOrigin = explode(
            '___',
            $shippingOriginConfig
        );

        $requestObject->setPickupPoint(
            $request->getPickupPoint() ? $request->getPickupPoint() : $shippingOrigin[0]
        );
        $requestObject->setContactPerson(
            $request->getContactPerson() ? $request->getContactPerson() : $shippingOrigin[1]
        );
        $requestObject->setPackageType(
            $request->getPackageType() ?
                $request->getPackageType() :
                $this->_scopeConfig->getValue(
                    self::CONFIG_PACKAGE_TYPE,
                    ScopeInterface::SCOPE_WEBSITE
                )
        );
        $requestObject->setPackageNumber(
            $request->getPackageNumber() ? $request->getPackageNumber() : self::DEFAULT_PACKAGE_NUMBER
        );
        $requestObject->setPackageWeight(
            $request->getPackageWeight()
        );
        $requestObject->setAwbPayment(
            $request->getAwbPayment() ?
                $request->getAwbPayment() :
                $this->_scopeConfig->getValue(
                    self::CONFIG_AWB_PAYMENT,
                    ScopeInterface::SCOPE_WEBSITE
                )
        );

        $requestObject->setCashOnDelivery(
            $request->getBaseSubtotalInclTax() ? $request->getBaseSubtotalInclTax() : 0
        );
        $requestObject->setInsuredValue(
            $request->getPackageValue() ? $request->getPackageValue() : 0
        );
        $requestObject->setThirdPartyPickup(self::TPP_NO);

        $requestObject->setAwbRecipient($request);
        $requestObject->setParcels($request);
        $requestObject->setServiceTaxes();

        $this->setRawRequest($requestObject);

        return $this;
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->canCollectRates()) {
            return $this->getErrorMessage();
        }

        $apiRequest = clone $request;
        $this->setRequest($apiRequest);

        $this->_getQuotes();
        $this->_updateFreeMethodQuote($request);

        return $this->getResult();

    }

    /**
     * Get shipping quotes
     */
    protected function _getQuotes()
    {
        $this->_rawRequest->setService(self::NEXTDAY_DELIVERY);
        $response = $this->_getQuoteFromServer();
        $this->_parseResponse($response);

        $this->_rawRequest->setService(self::SAMEDAY_DELIVERY);
        $response = $this->_getQuoteFromServer();
        $this->_parseResponse($response);
    }

    /**
     * Get shipping quotes from the API service
     *
     * @return array
     */
    protected function _getQuoteFromServer()
    {
        return $this->api->request(
            Api::METHOD_ESTIMATE_COST,
            \Zend_Http_Client::POST,
            array(),
            $this->_rawRequest->exportData()
        );
    }

    /**
     * @param array $response
     */
    protected function _parseResponse($response)
    {
        $result = $this->getResult();

        if (isset($response['code']) && $response['code'] != 200) {
            $message = '';
            /* TODO: come up with a recursive solution */
            foreach ($response['errors']['children'] as $fields0) {
                foreach ($fields0 as $fields1) {
                    foreach ($fields1 as $fields2) {
                        if (!is_array($fields2)) {
                            $message .= $fields2 . ' ';
                        } else {
                            foreach ($fields2 as $fields3) {
                                foreach ($fields3 as $fields4) {
                                    $message .= $fields4 ? reset($fields4) : '';
                                }
                            }
                        }
                    }
                }
            }

            /* @var $error Error */
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->getCarrierCode());
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($message);
            $this->debugErrors($error);

            $result->append($this->getErrorMessage());
            return;
        }

        /* @var $rate \Magento\Quote\Model\Quote\Address\RateResult\Method */
        $rate = $this->_rateMethodFactory->create();
        $rate->setCarrier($this->getCarrierCode());
        $rate->setCarrierTitle($this->getConfigData('title'));
        $rate->setMethod($this->getMethodById($this->_rawRequest->getService(), 'code'));
        $rate->setMethodTitle($this->getMethodById($this->_rawRequest->getService(), 'title'));
        $rate->setCost($response['amount']);
        $rate->setPrice($response['amount']);
        $result->append($rate);
    }

    /**
     * @param Result|null
     */
    public function setResult($result)
    {
        $this->_result = $result;
    }

    /**
     * @return Result|null
     */
    public function getResult()
    {
        if(!$this->_result) {
            /* @var $result Result */
            $this->_result = $this->_rateFactory->create();
        }

        return $this->_result;
    }

    /**
     * @param $methodId
     * @param $key
     * @return string|array
     */
    public static function getMethodById($methodId, $key)
    {
        $methods = array(
            self::SAMEDAY_DELIVERY => array(
                'code' => 'sameday',
                'title' => __('Same Day Delivery')
            ),
            self::NEXTDAY_DELIVERY => array(
                'code' => 'nextday',
                'title' => __('Next Day Delivery')
            )
        );

        return $methods[$methodId][$key];
    }

    /**
     * @param $method
     * @param $key
     * @return string|array
     */
    public static function getMethodByCode($method, $key)
    {
        $methods = array(
            'sameday' => array(
                'id' => self::SAMEDAY_DELIVERY,
                'title' => __('Same Day Delivery')
            ),
            'nextday' => array(
                'id' => self::NEXTDAY_DELIVERY,
                'title' => __('Next Day Delivery')
            )
        );

        return $methods[$method][$key];
    }

    /**
     * Prepare shipment request.
     * Validate and correct request information
     *
     * @param DataObject $request
     * @return DataObject $request
     * @throws LocalizedException
     */
    protected function _prepareShipmentRequest(DataObject $request)
    {
        $request->setService($this->getMethodByCode($request->getShippingMethod(), 'id'));

        $shippingOrigin = explode(
            '___',
            $this->_scopeConfig->getValue(
                self::CONFIG_SHIPPING_ORIGIN,
                ScopeInterface::SCOPE_WEBSITE
            )
        );

        if(empty($shippingOriginConfig)) {
            throw new LocalizedException(
                __('Shipping origin is not configured. Please set it in the module settings')
            );
        }

        $request->setPickupPoint($shippingOrigin[0]);
        $request->setContactPerson($shippingOrigin[1]);

        $request->setPackageType(
            $this->_scopeConfig->getValue(
                self::CONFIG_PACKAGE_TYPE,
                ScopeInterface::SCOPE_WEBSITE
            )
        );

        $request->setPackageNumber(1);

        $request->setAwbPayment(
            $this->_scopeConfig->getValue(
                self::CONFIG_AWB_PAYMENT,
                ScopeInterface::SCOPE_WEBSITE
            )
        );

        $order = $this->orderRepository->get($request->getOrderShipment()->getOrderId());
        $request->setCashOnDelivery(
            $order->getPayment()->getMethod() == Cashondelivery::PAYMENT_METHOD_CASHONDELIVERY_CODE ?
            $order->getGrandTotal()  :
            0
        );

        $request->setInsuredValue($order->getGrandTotal());
        $request->setThirdPartyPickup(self::TPP_NO);

        $request->setAwbRecipient($this->_getAwbRecipient($request));

        $request->setParcels(array(
            array(
            'weight' => $request->getPackageParams()->getWeight(),
            'length' => $request->getPackageParams()->getLength(),
            'width'  => $request->getPackageParams()->getWidth(),
            'height' => $request->getPackageParams()->getHeight(),
            )
        ));

        $request->setServiceTaxes($this->_getServiceTaxes($request));

        return $request;
    }

    /**
     * Do request to shipment
     *
     * @param DataObject $request
     * @throws LocalizedException
     * @return DataObject $response
     */
    public function requestToShipment($request)
    {
        $packages = $request->getPackages();

        if (!is_array($packages) || !$packages) {
            throw new LocalizedException(__('No packages for request'));
        }

        /*$packages = [
            1 => [
                'params' => [
                    'container' => '',
                    'weight'    => 1,
                    'length'    => 2,
                    'width'     => 3,
                    'height'    => 4,
                    'weight_units'  => 'KILOGRAM',
                    'dimension_units'   => 'CENTIMETER',
                    'content_type'  => '',
                    'content_type_other' => ''
                ],
                'items' => [
                    3 => [
                        'qty' => 1,
                        'customs_value' => 45,
                        'price' => '45.000',
                        'name'  => 'prod name',
                        'weight' => '',
                        'product_id' => '14',
                        'order_item_id' => 3,
                    ]
                ]
            ]
        ];*/
        /* $request
        - shipper_contact_person_name
        - shipper_contact_person_first_name
        - shipper_contact_person_last_name
        - shipper_contact_company_name
        - shipper_contact_phone_number
        - shipper_email
        - shipper_address_street
        - shipper_address_street_1
        - shipper_address_street_2
        - shipper_address_city
        - shipper_address_state_or_province_code
        - shipper_address_postal_code
        - shipper_address_country_code
        - recipient_contact_person_name
        - recipient_contact_person_first_name
        - recipient_contact_person_last_name
        - recipient_contact_company_name
        - recipient_contact_phone_number
        - recipient_email
        - recipient_address_street
        - recipient_address_street_1
        - recipient_address_street_2
        - recipient_address_city
        - recipient_address_state_or_province_code
        - recipient_address_postal_code
        - recipient_address_country_code
        - shipping_method = 'sameday'
        - package_weight = 0
        - packages (same as above array)
        - base_currency_code
        - store_id
        - order_shipment (Magento\Sales\Model\Order\Shipment
            - id
            - store_id
            - total_weight
            - total_qty
            - email_sent
            - send_email
            - order_id
            - customer_id
            - shipping_address_id
            - shipment_status = null
            - increment_id
            - shipping_label = (PDF content)
            - customer note
            - customer_note_notify

        */

        $data = [];
        foreach ($packages as $packageId => $package) {

            $request->setPackageId($packageId);
            $request->setPackagingType($package['params']['container']);
            $request->setPackageWeight($package['params']['weight']);
            $request->setPackageParams(new DataObject($package['params']));
            $request->setPackageItems($package['items']);

            $request = $this->_prepareShipmentRequest($request);
            $result = $this->_doShipmentRequest($request);

            if ($result->hasErrors()) {
                $this->rollBack($data);
                break;
            } else {
                $data[] = [
                    'tracking_number' => $result->getTrackingNumber(),
                    'label_content' => $this->_getShippingLabelContent($result->getTrackingNumber()),
                ];
            }
            if (!isset($isFirstRequest)) {
                $request->setMasterTrackingId($result->getTrackingNumber());
                $isFirstRequest = false;
            }
        }

        $response = new DataObject(['info' => $data]);
        if ($result->getErrors()) {
            $response->setErrors($result->getErrors());
        }

        return $response;
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param DataObject $request
     * @return DataObject $result;
     */
    protected function _doShipmentRequest(DataObject $request)
    {
        $result = new DataObject();

        $response = $this->api->request(
            Api::METHOD_CREATE_AWB,
            \Zend_Http_Client::POST,
            array(),
            $this->_exportData($request)
        );

        if (empty($response['awbNumber'])) {
            $result->setErrors(__('Unexpected error. Please try again later or create the AWB manually'));
            return $result;
        }

        $result->setTrackingNumber($response['awbNumber']);
        return $result;
    }

    /**
     * @param $trackingNumber
     * @return array|string
     */
    protected function _getShippingLabelContent($trackingNumber)
    {
        $content = $this->api->request(
            Api::METHOD_DOWNLOAD_AWB . $trackingNumber,
            \Zend_Http_Client::GET,
            array(),
            array(),
            false
        );

        return $content;
    }

    /**
     * @param DataObject $request
     * @return array
     */
    protected function _exportData($request)
    {
        $data = $request->getData();
        $response = array();
        foreach ($data as $key => $value) {
            $response[lcfirst(str_replace('_', '', ucwords($key, '_')))] = $value;
        }

        return $response;
    }

    /**
     * @param $request
     * @return array
     */
    protected function _getAwbRecipient($request)
    {
        $awbRecipient = array();

        $region = $this->directoryRegion
            ->loadByCode(
                $request->getRecipientAddressStateOrProvinceCode(),
                $request->getRecipientAddressCountryCode()
            )->getName();

        $response = $this->api->request(
            API::METHOD_GEOLOCATION_COUNTY,
            \Zend_Http_Client::GET,
            array(),
            array('name' => $region)
        );

        $awbRecipient['county'] = $response['data'][0]['id'];

        $response = $this->api->request(
            API::METHOD_GEOLOCATION_CITY,
            \Zend_Http_Client::GET,
            array(),
            array(
                'name' => $request->getRecipientAddressCity(),
                'county' => $awbRecipient['county'],
                'address' => $request->getRecipientAddressStreet())
        );

        $awbRecipient['city'] = $response['data'][0]['id'];
        $awbRecipient['address'] = $request->getRecipientAddressStreet();
        $awbRecipient['name'] = $request->getRecipientContactPersonName();
        $awbRecipient['phoneNumber'] = $request->getRecipientContactPhoneNumber();
        $awbRecipient['personType'] = Carrier::PERSON_TYPE_INDIVIDUAL;

        return $awbRecipient;
    }

    /**
     * @param $request
     * @return array
     */
    protected function _getServiceTaxes($request)
    {
        $data = array();

        $response = $this->api->request(
            Api::METHOD_CLIENT_SERVICES,
            \Zend_Http_Client::GET,
            array(),
            array()
        );

        foreach ($response['data'] as $service) {
            if ($service['id'] != $request->getService()) {
                continue;
            }

            foreach ($service['serviceOptionalTaxes'] as $tax) {
                if ($tax['packageType'] != $request->getPackageType()) {
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

        return $data;
    }
}