<?php
/**
 * AIRLINE Reservation Services Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Airline
 * @package    Airline_Reservation
 * @copyright  Copyright (c) 2012 EcomDev BV (http://www.ecomdev.org)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Ivan Chepurnyi <ivan.chepurnyi@ecomdev.org>
 *
 */

class Airline_ECustomerOrder_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function _addRetryEntry($orderid, $type) {
        $model = Mage::getModel('airline_salesforce/retry');
        $model->setOrder($orderid)->setType($type)->setStatus('new')->setResponse('');
        $model->save();
    }

    /**
     * Formats date of the flight
     *
     * @param string $dateTime
     * @param string $timezone
     *
     * @return string
     */
    public function formatDate($dateTime, $timezone = null)
    {
        $date = $this->_getDate($dateTime, $timezone);
        $dateFormat = Mage::app()->getLocale()->getDateFormat(
            Mage_Core_Model_Locale::FORMAT_TYPE_LONG
        );

        return $date->toString($dateFormat);
    }

    /**
     * Returns date from datetime string
     *
     * @param string $dateTime
     * @param string $timezone
     *
     * @return Zend_Date
     */
    protected function _getDate($dateTime, $timezone = null)
    {
        if ($dateTime instanceof Zend_Date) {
            return $dateTime;
        }

        $date = Mage::app()->getLocale()->date($dateTime, Varien_Date::DATETIME_INTERNAL_FORMAT);
        if ($timezone !== null) {
            $zone = $date->getTimezoneFromString($timezone);
            $date->setTimezone($zone);
        }
        return $date;
    }

    /**
     * Formats time for flight
     *
     * @param string $dateTime
     * @param string $timezone
     * @return string
     */
    public function formatTime($dateTime, $timezone = null)
    {
        $date = $this->_getDate($dateTime, $timezone);
        $timeFormat = 'HHmm';
        return $date->toString($timeFormat);
    }
}
