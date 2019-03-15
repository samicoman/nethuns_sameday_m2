<?php

namespace Nethuns\Sameday\Model\Carrier\Source;

use Magento\Framework\Option\ArrayInterface;

class Awbpayment implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => \Nethuns\Sameday\Model\Carrier::CLIENT,
                'label' => __('Sender'),
            ),
            array(
                'value' => \Nethuns\Sameday\Model\Carrier::RECIPIENT,
                'label' => __('Recipient')
            )
        );
    }
}