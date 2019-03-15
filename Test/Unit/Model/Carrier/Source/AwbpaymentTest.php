<?php

namespace Nethuns\Sameday\Model\Carrier\Source;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class AwbpaymentTest extends \PHPUnit\Framework\TestCase
{
    /** @var Awbpayment */
    protected $object;

    /** @var ObjectManager */
    protected $objectManager;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->object = $this->objectManager->getObject(
            Awbpayment::class
        );
    }

    public function testToOptionArray()
    {
        $this->assertEquals(
            [
                [
                    'value' => \Nethuns\Sameday\Model\Carrier::CLIENT,
                    'label' => __('Sender'),
                ],
                [
                    'value' => \Nethuns\Sameday\Model\Carrier::RECIPIENT,
                    'label' => __('Recipient')
                ]
            ],
            $this->object->toOptionArray()
        );
    }
}
