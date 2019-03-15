<?php

namespace Nethuns\Sameday\Model\Carrier\Source;

use \Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use \Nethuns\Sameday\Model\Api;

class ShippingoriginTest extends \PHPUnit\Framework\TestCase
{
    /** @var Packagetype */
    protected $object;

    /** @var ObjectManager */
    protected $objectManager;

    /** @var Api */
    protected $api;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);

        $this->api = $this->createMock(Api::class);

        $this->object = $this->objectManager->getObject(
            Shippingorigin::class,
            [
                'api'       => $this->api
            ]
        );

        $this->api->expects($this->once())
            ->method('request')
            ->with(
                API::METHOD_PICKUP_POINTS,
                \Zend_Http_Client::GET)
            ->willReturn(array(
                'data' => array(
                    array(
                        'id'        =>  1,
                        'address'   =>  'address 1',
                        'pickupPointContactPerson' => array(
                            array(
                                'id'    => 1
                            )
                        ),
                    ),
                    array(
                        'id'        =>  2,
                        'address'   =>  'address 2',
                        'pickupPointContactPerson' => array(
                            array(
                                'id'    => 2
                            )
                        ),
                    )
                )
            )
        );
    }

    public function testToOptionArray()
    {

        $this->assertEquals(
            [
                [
                    'value' => '1___1',
                    'label' => 'address 1',
                ],
                [
                    'value' => '2___2',
                    'label' => 'address 2'
                ]
            ],
            $this->object->toOptionArray()
        );
    }
}
