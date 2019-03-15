<?php

namespace Nethuns\Sameday\Model\Rate;

use \Magento\Directory\Model\Region;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Magento\Framework\DataObject;
use \Magento\Quote\Model\Quote\Address\RateRequest;
use \Nethuns\Sameday\Model\Api;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class RequestTest extends \PHPUnit\Framework\TestCase
{
    /** @var Request */
    protected $object;

    /** @var ObjectManager */
    protected $objectManager;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var Api|MockObject */
    protected $api;

    /** @var Region|MockObject */
    protected $directoryRegion;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);

        $this->api = $this->createMock(Api::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->directoryRegion = $this->createMock(Region::class);

        $this->scopeConfig->expects($this->any())
            ->method('getValue')
            ->willReturn(true);

        $this->object = $this->objectManager->getObject(
            Request::class,
            [
                'scopeConfig'       => $this->scopeConfig,
                'api'               => $this->api,
                'directoryRegion'   => $this->directoryRegion
            ]
        );
    }

    public function testSetServiceTaxes()
    {
        $this->object->setService(999);
        $this->object->setPackageType(2);

        $sampleApiResponse = [
            'data' => [
                [
                    'id'    => 999,
                    'serviceOptionalTaxes' => [
                        [ 'id' => 1, 'packageType' => 1, 'name' => 'Deschidere Colet' ],
                        [ 'id' => 2, 'packageType' => 1, 'name' => 'Reambalare' ],
                        [ 'id' => 3, 'packageType' => 2, 'name' => 'Deschidere Colet' ],
                        [ 'id' => 4, 'packageType' => 2, 'name' => 'Reambalare' ],
                    ]
                ],
                [
                    'id'    => 888,
                    'serviceOptionalTaxes' => [
                        [ 'id' => 5, 'packageType' => 1, 'name' => 'Deschidere Colet' ],
                        [ 'id' => 6, 'packageType' => 1, 'name' => 'Reambalare' ],
                        [ 'id' => 7, 'packageType' => 2, 'name' => 'Deschidere Colet' ],
                        [ 'id' => 8, 'packageType' => 2, 'name' => 'Reambalare' ],
                    ]
                ],
            ]
        ];

        $this->api->expects($this->once())
            ->method('request')
            ->with(API::METHOD_CLIENT_SERVICES, \Zend_Http_Client::GET)
            ->willReturn($sampleApiResponse);

        $this->object->setServiceTaxes();

        $this->assertEquals(
            [ 3, 4 ],
            $this->object->getServiceTaxes()
        );
    }

    public function testSetAwbRecipient()
    {
        $countyResponse = [
            'data' => [
                [
                    'id'    => 55
                ]
            ]
        ];
        $cityResponse = [
            'data' => [
                [
                    'id'    => 77
                ]
            ]
        ];

        $request = new RateRequest();
        $request->setDestRegionId(1);
        $request->setDestCity('City Name');
        $request->setDestStreet('Street Name');
        $request->setDestStreetLine2('Street Line 2');

        $this->directoryRegion->expects($this->once())
            ->method('load')
            ->with(1)
            ->will($this->returnSelf());

        $this->directoryRegion->expects($this->once())
            ->method('getName')
            ->willReturn('Region Name');

        $this->api->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                [
                    API::METHOD_GEOLOCATION_COUNTY,
                    \Zend_Http_Client::GET,
                    [],
                    [
                        'name' => 'Region Name'
                    ]
                ],
                [
                    API::METHOD_GEOLOCATION_CITY,
                    \Zend_Http_Client::GET,
                    [],
                    [
                        'name' => 'City Name',
                        'county' => 55,
                        'address' => 'Street Name'
                    ]
                ]
            )
            ->willReturnOnConsecutiveCalls($countyResponse, $cityResponse);

        $this->object->setAwbRecipient($request);

        $this->assertEquals(
            [
                'city'      => 77,
                'county'    => 55,
                'address'   => 'Street Name Street Line 2',
                'name'      => 'Dummy',
                'phoneNumber' => '0123456789',
                'personType'  => 0,
            ],
            $this->object->getAwbRecipient()
        );

    }

    public function testExportData()
    {
        $this->object->setBlueFlag('blue');
        $this->object->setGreenBackgroundPaper('green');
        $this->object->setRed('dark');

        $this->assertEquals(
            [
                'blueFlag' => 'blue',
                'greenBackgroundPaper' => 'green',
                'red' => 'dark',
            ],
            $this->object->exportData()
        );
    }
}
