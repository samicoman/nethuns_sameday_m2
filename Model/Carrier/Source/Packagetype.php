<?php

namespace Nethuns\Sameday\Model\Carrier\Source;

use Magento\Framework\Option\ArrayInterface;

class Packagetype implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => \Nethuns\Sameday\Model\Carrier::PACKAGE_TYPE_REGULAR,
                'label' => __('Package'),
            ),
            array(
                'value' => \Nethuns\Sameday\Model\Carrier::PACKAGE_TYPE_ENVELOPE,
                'label' => __('Envelope')
            ),
            array(
                'value' => \Nethuns\Sameday\Model\Carrier::PACKAGE_TYPE_LARGE,
                'label' => __('Large package')
            )
        );
    }
}