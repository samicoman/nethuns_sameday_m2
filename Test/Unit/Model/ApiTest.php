<?php

namespace Nethuns\Sameday\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\Message\ManagerInterface;
use \Magento\Framework\HTTP\Adapter\CurlFactory;
use \Magento\Framework\HTTP\Adapter\Curl;
use \Magento\Framework\Serialize\Serializer\Json;
use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Store\Model\ScopeInterface;
use \Psr\Log\LoggerInterface;

class ApiTest extends \PHPUnit\Framework\TestCase
{
    /** @var Api */
    protected $api;

    /** @var ObjectManager */
    protected $objectManager;

    /** @var Json */
    protected $json;

    /** @var ScopeConfigInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $scopeConfig;

    /** @var ManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $messageManagerMock;

    /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $loggerMock;

    /** @var Api|\PHPUnit_Framework_MockObject_MockObject */
    protected $apiMock;

    /** @var Curl|\PHPUnit_Framework_MockObject_MockObject */
    private $curlAdapterMock;


    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);

        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->apiMock = $this->createMock(Api::class);

        $this->curlAdapterMock = $this->getMockBuilder(Curl::class)
            ->disableOriginalConstructor()
            ->getMock();

        $curlFactoryMock = $this->getMockBuilder(CurlFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
        $curlFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->curlAdapterMock);

        $this->json = $this->objectManager->getObject(Json::class);
        $this->api = $this->objectManager->getObject(
            Api::class,
            [
                'scopeConfig'       => $this->scopeConfig,
                'curlFactory'       => $curlFactoryMock,
                'json'              => $this->json,
                'messageManager'    => $this->messageManagerMock,
                'logger'            => $this->loggerMock
            ]
        );

        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->willReturn('CONFIG_VALUE');
    }

    public function testRequestTypeGetSuccess()
    {
        $this->curlAdapterMock->expects($this->any())
            ->method('setOptions')
            ->with(array(
                CURLOPT_HTTPAUTH        => 1,
                CURLOPT_USERPWD         => "CONFIG_VALUE:CONFIG_VALUE",
                CURLOPT_HTTPHEADER      => array(
                                            "X-AUTH-TOKEN" => "514c03a24276a84098d40c3979ec28b843de7ab9",
                                            "Content-type" => "application/x-www-form-urlencoded"
                ),
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_URL             => "CONFIG_VALUE/api/PATH?_format=json&A=1&B=2",
                CURLINFO_HEADER_OUT     => 1,
            ));

        $this->curlAdapterMock->expects($this->any())
            ->method('write')
            ->with(
                'GET',
                'CONFIG_VALUE/api/PATH?_format=json&A=1&B=2',
                '1.0');

        $this->curlAdapterMock->expects($this->any())
            ->method('read')
            ->willReturn('{"success":"cool"}');

        $this->curlAdapterMock->expects($this->any())
            ->method('getErrno')
            ->willReturn(false);

        $this->curlAdapterMock->expects($this->any())
            ->method('close');

//        $this->messageManagerMock->expects($this->once())
//            ->method('addErrorMessage')
//            ->with(__('Something is wrong with the API. Please check the logs.'));
//        $this->loggerMock->expects($this->atLeastOnce())
//            ->method('error');

//        $this->assertEquals(
//            array('success' => 'cool'),
//            $this->api->request(
//                'PATH',
//                'GET',
//                array(),
//                array('A' => '1', 'B' => '2')
//            )
//        );
    }

    public function testRequestTypeGetFailure()
    {
        $this->curlAdapterMock->expects($this->any())
            ->method('setOptions');

        $this->curlAdapterMock->expects($this->any())
            ->method('write');

        $this->curlAdapterMock->expects($this->any())
            ->method('read')
            ->willReturn('INVALID_JSON');

        $this->curlAdapterMock->expects($this->any())
            ->method('getErrno')
            ->willReturn(401);

        $this->curlAdapterMock->expects($this->any())
            ->method('close');

        $this->messageManagerMock->expects($this->atLeastOnce())
            ->method('addErrorMessage');
        $this->loggerMock->expects($this->atLeastOnce())
            ->method('error');

        $this->assertEquals(
            '',
            $this->api->request(
                'PATH',
                'GET',
                array(),
                array('A' => '1', 'B' => '2')
            )
        );
    }

    public function testRequestTypePostSuccess()
    {

    }

    public function testRequestTypePostFailure()
    {

    }

    public function testParseResponse()
    {

    }

    public function testGetRequestUrl()
    {

    }

    public function testGetRequestHeaders()
    {

    }

    public function testGetRequestOptions()
    {

    }
}