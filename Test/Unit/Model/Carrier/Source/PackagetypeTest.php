<?php

namespace Nethuns\Sameday\Model\Carrier\Source;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class PackagetypeTest extends \PHPUnit\Framework\TestCase
{
    /** @var Packagetype */
    protected $object;

    /** @var ObjectManager */
    protected $objectManager;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->object = $this->objectManager->getObject(
            Packagetype::class
        );
    }

    public function testToOptionArray()
    {
        $this->assertEquals(
            [
                [
                    'value' => \Nethuns\Sameday\Model\Carrier::PACKAGE_TYPE_REGULAR,
                    'label' => __('Package'),
                ],
                [
                    'value' => \Nethuns\Sameday\Model\Carrier::PACKAGE_TYPE_ENVELOPE,
                    'label' => __('Envelope')
                ],
                [
                    'value' => \Nethuns\Sameday\Model\Carrier::PACKAGE_TYPE_LARGE,
                    'label' => __('Large package')
                ]
            ],
            $this->object->toOptionArray()
        );
    }
}
