<?php

namespace Nethuns\Sameday\Model;

use \Magento\Store\Model\ScopeInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\HTTP\Adapter\CurlFactory;
use \Magento\Framework\Serialize\Serializer\Json;
use \Magento\Framework\Message\ManagerInterface;
use \Magento\Framework\Model\Context;
use \Magento\Framework\Registry;
use \Psr\Log\LoggerInterface;

class Api extends \Magento\Framework\Model\AbstractModel
{
    /** @var Json */
    protected $json;
    /** @var ManagerInterface */
    protected $messageManager;
    /** @var LoggerInterface */
    protected $logger;
    /** @var CurlFactory */
    protected $curlFactory;
    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    const CONFIG_API_URL = 'carriers/nethunssameday/api_url';
    const CONFIG_API_USER = 'carriers/nethunssameday/username';
    const CONFIG_API_PASSWORD = 'carriers/nethunssameday/password';
    const CONFIG_HTTP_AUTH_USER = 'carriers/nethunssameday/http_user';
    const CONFIG_HTTP_AUTH_PASSWORD = 'carriers/nethunssameday/http_pass';

    const METHOD_AUTHENTICATE = 'authenticate';
    const METHOD_GEOLOCATION_CITY = 'geolocation/city';
    const METHOD_GEOLOCATION_COUNTY = 'geolocation/county';
    const METHOD_PICKUP_POINTS = 'client/pickup-points';
    const METHOD_CLIENT_SERVICES = 'client/services';
    const METHOD_ESTIMATE_COST = 'awb/estimate-cost';
    const METHOD_CREATE_AWB = 'awb';
    const METHOD_DOWNLOAD_AWB = 'awb/download/';

    /** @var string */
    protected $apiUrl;
    /** @var string */
    protected $token;
    /** @var string */
    protected $httpAuthUser;
    /** @var string */
    protected $httpAuthPassword;

    /** @var string */
    protected $apiToken;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        CurlFactory $curlFactory,
        Json $json,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    )
    {
        parent::__construct($context, $registry);
        $this->scopeConfig = $scopeConfig;
        $this->curlFactory = $curlFactory;
        $this->json = $json;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * @param string $input
     * @param bool $decode
     * @return string|array
     */
    public function parseResponse($input, $decode = true)
    {
        try {
            $response = $decode ? $this->json->unserialize($input) : $input;
        } catch(\InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error('The input was:' . $input);
            $this->messageManager->addErrorMessage(__('Something is wrong with the API. Please check the logs.'));
            return '';
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error('The input was:' . $input);
            $this->messageManager->addErrorMessage(__('Something is wrong with the API. Please check the logs.'));
            return '';
        }
        return $response;
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        if(!$this->apiUrl) {
            $this->apiUrl = $this->scopeConfig->getValue(
                self::CONFIG_API_URL,
                ScopeInterface::SCOPE_WEBSITE
            );
        }

        return $this->apiUrl;
    }

    /**
     * @return string
     */
    public function getHttpAuthUser()
    {
        if(!$this->httpAuthUser) {
            $this->httpAuthUser = $this->scopeConfig->getValue(
                self::CONFIG_HTTP_AUTH_USER,
                ScopeInterface::SCOPE_WEBSITE
            );
        }

        return $this->httpAuthUser;
    }

    /**
     * @return string
     */
    public function getHttpAuthPassword()
    {
        if(!$this->httpAuthPassword) {
            $this->httpAuthPassword = $this->scopeConfig->getValue(
                self::CONFIG_HTTP_AUTH_PASSWORD,
                ScopeInterface::SCOPE_WEBSITE
            );
        }

        return $this->httpAuthPassword;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        if(!$this->token) {

            $response = $this->request(
                self::METHOD_AUTHENTICATE,
                \Zend_Http_Client::POST,
                array(
                    'X-Auth-Username' => $this->scopeConfig->getValue(
                        self::CONFIG_API_USER,
                        ScopeInterface::SCOPE_WEBSITE
                    ),
                    'X-Auth-Password' => $this->scopeConfig->getValue(
                        self::CONFIG_API_PASSWORD,
                        ScopeInterface::SCOPE_WEBSITE
                    )
                ),
                array(
                    'remember_me' => 'true'
                ),
                true,
                false
            );

            $this->token = !empty($response['token']) ? $response['token'] : '';
        }

        return $this->token;
    }

    /**
     * @param string $path
     * @param string $type
     * @param array $params
     * @return string
     */
    public function getRequestUrl($path, $type, $params = array())
    {
        $url = rtrim($this->getApiUrl(), '/');
        $url .= '/api/' . $path;
        $url .= '?_format=json';

        if(!empty($params) && $type == \Zend_Http_Client::GET) {
            $url .= '&' . http_build_query($params);
        }

        return $url;
    }

    /**
     * @param array $headers
     * @param boolean $useToken
     * @return array
     */
    public function getRequestHeaders($headers, $useToken = true)
    {
        /* HACK: Use keys & values when the API is fixed */
        $newHeaders = array();
        foreach ($headers as $key => $value) {
            $newHeaders[] = $key . ': ' . $value;
        }

        if($useToken) {
            $newHeaders[] = 'X-AUTH-TOKEN: ' . $this->getToken();
        }

        $newHeaders[] = 'Content-type: application/x-www-form-urlencoded';

        return $newHeaders;
    }

    /**
     * @param string $url
     * @param string $type
     * @param array $headers
     * @param array $params
     * @return array
     */
    public function getRequestOptions($url, $type, $headers, $params)
    {
        $options = array();

        if($type == \Zend_Http_Client::POST) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        if ($this->getHttpAuthUser() && $this->getHttpAuthPassword()) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $this->getHttpAuthUser() . ':' . $this->getHttpAuthPassword();
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_URL] = $url;
        $options[CURLINFO_HEADER_OUT] = true;

        return $options;
    }

    /**
     * @param string $path
     * @param string $type
     * @param array $headers
     * @param array $params
     * @param bool $decode
     * @param bool $useToken
     * @return array|string
     */
    public function request($path, $type, $headers = array(), $params = array(), $decode = true, $useToken = true)
    {
        $result = '';

        /* HACK: The API does not provide results for Bucuresti; Remove this call when fixed */
        $params = $this->hackParamsForCityRequest($path, $params);

        $url        = $this->getRequestUrl($path, $type, $params);
        $headers    = $this->getRequestHeaders($headers, $useToken);
        $options    = $this->getRequestOptions($url, $type, $headers, $params);

        try {

//            $curl = $this->curlFactory->create();
//            $curl->setConfig(array('header' => false));
//            $curl->setOptions($options);
//            $curl->write($type, $url, '1.1');
//            $result = $this->parseResponse($curl->read(), $decode);

//            if ($curl->getErrno()) {
//                $this->messageManager->addErrorMessage($curl->getError());
//                $this->logger->warning(
//                    new \Exception(
//                        sprintf(
//                            'CURL connection error #%s: %s',
//                            $curl->getErrno(),
//                            $curl->getError()
//                        )
//                    )
//                );
//            }

//            $curl->close();

            /* HACK: Replace with the lines above when the API is fixed */
            $curl = curl_init($url);
            curl_setopt_array($curl, $options);
            $result = $this->parseResponse(curl_exec($curl), $decode);

            if (curl_error($curl)) {
                $this->messageManager->addErrorMessage(curl_error($curl));
                $this->logger->warning(
                    new \Exception(
                        sprintf(
                            'CURL connection error #%s: %s',
                            curl_errno($curl),
                            curl_error($curl)
                        )
                    )
                );
            }

            if (!empty($result['error'])) {
                $this->messageManager->addErrorMessage($result['error']['message']);
                $this->logger->warning(
                    new \Exception(
                        sprintf(
                            'API error #%s: %s',
                            $result['error']['code'],
                            $result['error']['message']
                        )
                    )
                );
            }

            curl_close($curl);

        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $result;
    }

    /**
     * If the city is Bucuresti, get the sector from other fields and replace the name field with the sector
     *
     * @param string $path
     * @param array $params
     * @return array
     */
    function hackParamsForCityRequest($path, $params)
    {
        if($path != self::METHOD_GEOLOCATION_CITY) {
            return $params;
        }

        if (!strtolower(iconv('UTF-8','ASCII//TRANSLIT', $params['name'])) == 'bucuresti') {
            return $params;
        }

        if(empty($params['address'])) {
            return $params;
        }

        $address = strtolower($params['address']);
        $pattern = '/sector(ul)*(\s)(\d){1}/mi';
        $matches = array();

        preg_match($pattern, $address, $matches);

        unset($params['address']);
        $params['name'] = !empty($matches[0]) ? 'Sectorul ' . end($matches) : $params['name'];

        return $params;
    }
}