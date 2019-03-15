<?php

namespace Nethuns\Sameday\Model\Carrier\Source;

use Magento\Framework\Option\ArrayInterface;
use Nethuns\Sameday\Model\Api;

class Shippingorigin implements ArrayInterface
{
    /**
     * @var \Nethuns\Sameday\Model\Api
     */
    protected $api;

    public function __construct(
        Api $api
    ) {
        $this->api = $api;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $shippingOriginArray = $this->getPickupPoints();
        $returnArr = array();
        foreach ($shippingOriginArray as $key => $val) {
            $returnArr[] = array(
                'value' => $key,
                'label' => $val
            );
        }
        return $returnArr;
    }

    /**
     * @return array
     */
    public function getPickupPoints()
    {
        $pickup_points = array();
        $response = $this->api->request(API::METHOD_PICKUP_POINTS, \Zend_Http_Client::GET);

        if(empty($response['data'])) {
            return array();
        }

        foreach ($response['data'] as $pickup_point) {
            $pickup_points[
                $pickup_point['id'] . '___' . $pickup_point['pickupPointContactPerson'][0]['id']
            ] = $pickup_point['address'];
        }

        return $pickup_points;
    }
}