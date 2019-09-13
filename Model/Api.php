<?php
/**
 * AIRLINE CustomerOrder Services Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Airline
 * @package    Airline_ECustomerOrder
 * @copyright  Copyright (c) 2012 EcomDev BV (http://www.ecomdev.org)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Ivan Chepurnyi <ivan.chepurnyi@ecomdev.org>
 *
 */

class SoapClientWrapper extends SoapClient {
    protected function callCurl($url, $data, $action) {

        $type = Mage::getStoreConfig('airline_ecustomerorder/general/mode');
        $handle   = curl_init();
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($handle, CURLOPT_URL, $url);

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLINFO_HEADER_OUT, true);
        curl_setopt($handle, CURLOPT_SSLVERSION, 1);
        curl_setopt($handle, CURLOPT_USERPWD, Mage::getStoreConfig('airline_ecustomerorder/general/username_'.$type).":".Mage::getStoreConfig('airline_ecustomerorder/general/password_'.$type));
        curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);

        curl_setopt($handle, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml", 'SOAPAction: "' . $action . '"', "Content-Length: ".strlen($data)));

        Mage::devlog("sending request [$action] to prologistica [$url]\n", null, 'prologistica_service.log',3);

        $response = curl_exec($handle);

        Mage::devlog(curl_getinfo($handle, CURLINFO_HEADER_OUT), null, 'prologistica_service.log', 4);

        if (empty($response)) {
            Mage::devlog('CURL error: '.curl_error($handle).curl_errno($handle), null, 'prologistica_service.log',2);
            Mage::devlog(curl_getinfo($handle), null, 'prologistica_service.log', 2);
            throw new SoapFault('CURL error: '.curl_error($handle),curl_errno($handle));
        }
        curl_close($handle);
        return $response;
    }

    public function __doRequest($request,$location,$action,$version,$one_way = 0) {
        return $this->callCurl($location, $request, $action);
    }
}

class Airline_ECustomerOrder_Model_Api
{
	protected $_soapClient = null;

    protected function _construct()
    {
        $this->_init('airline_ecustomerorder/api');
    }

    /**
	 * Returns Zend_Soap_Client instance
	 *
	 * @return Zend_Soap_Client
	 */
	public function getSoapClient()
	{
		if ($this->_soapClient === null) {
            $type = Mage::getStoreConfig('airline_ecustomerorder/general/mode');
            $url = Mage::getStoreConfig('airline_ecustomerorder/general/host_force_'.$type);


            //$this->_soapClient->setSoapVersion(SOAP_1_1);

            $this->_soapClient = new SoapClientWrapper("PLPreOrder.wsdl");
            if ($url != "") {
                Mage::devlog("setting location to [$url]", null, "prologistica_service.log", 3);
                $this->_soapClient->__setLocation($url);
            }
		}
	
		return $this->_soapClient;
	}

    /*Soap function call for sending the get present stock request to prologistica
	 * Input Param : Product Code
	 * Output : Soap response
	 * Failed Status : Soap Fault expception or Failure rsponse from Prologistica
	*/

    public function GetStock($_product)
    {
        try {
            $param = array(
                'ProductCode' => $_product
            );
            $result = $this->getSoapClient()->GetPresentStock($param);
        } catch(Exception $e) {
            // Log the exception thrown
            Mage::logException($e);
            Mage::throwException(
			    Mage::helper('airline_ecustomerorder')->__($e->faultstring)
			);
        }
        // Return the Soap response for successful transaction
        return $result->GetPresentStockResult;
    }
	
	/*Soap function call for sending the order cancellation request to prologistica
	 * Input Param : OrderId / $_OrderId
	 * Output : Soap response
	 * Failed Status : Soap Fault expception or Failure rsponse from Prologistica 
	*/
	
	public function OrderCancel($_OrderId)
	{
        $order = Mage::getModel('sales/order')->load($_OrderId);
        try {
            $param = array(
                'PreOrderID' => $order->getIncrementId()
            );
		    $result = $this->getSoapClient()->PreOrderCancel($param);
        } catch(Exception $e) {
            // Log the exception thrown
            //Mage::logException($e);
            Mage::throwException(
                Mage::helper('airline_ecustomerorder')->__($e->faultstring)
            );
        }
		// Return the Soap response for successful transaction
		return $result->OrderCancelResult;
	}

/*Soap function call for sending the order creation request to prologistica
	 * Input Param : OrderId / $_OrderId
	 * Output : Soap response
	 * Failed Status : Soap Fault expception or Failure rsponse from Prologistica
	*/
	public function OrderCreate($_OrderId)
    {
        $type = Mage::getStoreConfig('airline_ecustomerorder/general/mode');
        $order = Mage::getModel('sales/order')->load($_OrderId);

        $request = new stdClass();
        $items = new ArrayObject();

        foreach ($order->getAllVisibleItems() as $item) {
            // dont send paid options to prologistica
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            $attributeSetName = Mage::getModel('eav/entity_attribute_set')->load($product->getAttributeSetId())->getAttributeSetName();
            if ($attributeSetName == "Paid Options") {
                continue;
            }

            // dont send your surprise items to prologistica for home delivery
            if ($product->getData('your_surprise_id') && !$order->hasOnBoardDeliveryItems()) continue;

            // dont send items for which we dont check stock to prologistica
            $stockData = $product->getStockItem();
            //if ($stockData->getManageStock() == 0 OR $stockData->getCheckStock() == 0) continue;
            // UPDATE ... new dropdown to select if we want to send to prolo
            if ($product->getData("prologistica_send") == 0) continue;

            $i = new stdClass();
            $i->ProductCode = $item->getSku();
            $i->OrderedQty = intval($item->getQtyOrdered());

            $i->GrossPrice = ($item->getMiles() > 0) ? $item->getBaseOriginalPrice() : $item->getBasePrice();
            $i->GrossDiscountAmount = $item->getDiscountAmount();
            $i->DiscountPrct = $item->getDiscountPercent();
            $i->Gift = ($order->getStore()->getWebsite()->getCode() == "wannagives" ? "1" : "0");


            /**
             * Encode each array element with SoapVar.  Parameter 5 is the name of the
             * XML element you want to use.  This only seems to work within
             * an ArrayObject.
             */
            $i = new SoapVar($i, SOAP_ENC_OBJECT, null, null, 'PreOrderProduct');

            $items->append($i);
        }
        if ($items->count() == 0) {
            // no products to send ...
            Mage::devlog('no products to send to prolo, skipping ...', null, 'prologistica_service.log', 3);
            return "nothingtosend";
        }
        if ($order->hasOnBoardDeliveryItems()) {
            $flightnr = explode("\n", $order->getShippingAddress()->getFlightNumber());
            $flightDate = explode(" ",$order->getShippingAddress()->getFlightDeparture());
            $proFlightDate =  str_replace("-", "", $flightDate[0]);
            $flightPax = Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFlightPax());
            $name = explode("//", $flightPax);

            $dept = $order->getShippingAddress()->getFlightOriginAirport();
            $dest = $order->getShippingAddress()->getFlightDestinationAirport();

            $param = new stdClass();
            $param->AirlineID = "KL";
            $param->PreOrderID = $order->getIncrementId();
            $param->GroundDelivery = 0;
            $param->FlightPrefix = $order->getShippingAddress()->getFlightCarrier();
            $param->FlightNr = $order->getShippingAddress()->getFlightNumber();
            $param->FlightSuffix = "";
            $param->LegSerial = 1;
            $param->LocalDepDate = $proFlightDate;
            $param->DeptAirport = $dept;
            $param->DestAirport = $dest;
            $param->PassengerNameFirst = $name[1] != "" ? $name[1] : Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFlightFirstName());
            $param->PassengerNameLast = $name[0] != "" ? $name[0] : Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFlightLastName());
            $param->LoyaltyNr = ($order->getDecryptFbNumber() != '') ? $order->getDecryptFbNumber() : "";
            $param->InformationText = "";
            $param->PreOrderProducts = $items;
        } else {
            $param = new StdClass();
            $param->AirlineID = "KL";
            $param->PreOrderID = $order->getIncrementId();
            $param->GroundDelivery = 1;
            $param->FlightPrefix = "";
            $param->FlightNr = "";
            $param->FlightSuffix = "";
            $param->LegSerial = 1;
            $param->LocalDepDate = Mage::getModel('core/date')->date('Ymd');
            $param->DeptAirport = "";
            $param->DestAirport = "";
            $param->PassengerNameFirst = Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFirstname());
            $param->PassengerNameLast = Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getLastname());
            $param->LoyaltyNr = ($order->getDecryptFbNumber() != '') ? $order->getDecryptFbNumber() : "";
            $param->InformationText = "";
            $param->PreOrderProducts = $items;
        }

        try {
            $request->PreOrders = new ArrayObject();
            /**
             * Encode each array element with SoapVar.  Parameter 5 is the name of the
             * XML element you want to use.  This only seems to work within
             * an ArrayObject.
             */
            $param = new SoapVar($param, SOAP_ENC_OBJECT, null, null, 'PreOrder');

            $request->PreOrders->append($param);

            $result = $this->getSoapClient()->PreOrderCreate($request);
			
        } catch(Exception $e) {
            // Log the exception thrown
            Mage::throwException(
                Mage::helper('airline_ecustomerorder')->__($e->faultstring)
            );
        }
        // Return the Soap response for successful transaction
        return true;
	}
	/*
	 * AIRLINE need to send the failed order again to Prologistica in order to process order
	 * We will create new resend function based on the order failed prolo for the order's for day
	 * We will get new cron function to run the failed order to send to PROLO AGAIN
	*/
    public function OrderResend($_OrderId)
    {
        $type = Mage::getStoreConfig('airline_ecustomerorder/general/mode');
        $order = Mage::getModel('sales/order')->load($_OrderId);

        $request = new stdClass();
        $items = new ArrayObject();

        foreach ($order->getAllVisibleItems() as $item) {
            // dont send paid options to prologistica
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            $attributeSetName = Mage::getModel('eav/entity_attribute_set')->load($product->getAttributeSetId())->getAttributeSetName();
            if ($attributeSetName == "Paid Options") {
                continue;
            }

            // dont send your surprise items to prologistica for home delivery
            if ($product->getData('your_surprise_id') && !$order->hasOnBoardDeliveryItems()) continue;

            // dont send items for which we dont check stock to prologistica
            $stockData = $product->getStockItem();
            //if ($stockData->getManageStock() == 0 OR $stockData->getCheckStock() == 0) continue;
            // UPDATE ... new dropdown to select if we want to send to prolo
            if ($product->getData("prologistica_send") == 0) continue;

            $i = new stdClass();
            $i->ProductCode = $item->getSku();
            $i->OrderedQty = intval($item->getQtyOrdered());

            $i->GrossPrice = ($item->getMiles() > 0) ? $item->getBaseOriginalPrice() : $item->getBasePrice();
            $i->GrossDiscountAmount = $item->getDiscountAmount();
            $i->DiscountPrct = $item->getDiscountPercent();
            $i->Gift = ($order->getStore()->getWebsite()->getCode() == "wannagives" ? "1" : "0");


            /**
             * Encode each array element with SoapVar.  Parameter 5 is the name of the
             * XML element you want to use.  This only seems to work within
             * an ArrayObject.
             */
            $i = new SoapVar($i, SOAP_ENC_OBJECT, null, null, 'PreOrderProduct');

            $items->append($i);
        }
        if ($items->count() == 0) {
            // no products to send ...
            Mage::devlog('no products to send to prolo, skipping ...', null, 'prologistica_service.log', 3);
            return "nothingtosend";
        }
        if ($order->hasOnBoardDeliveryItems()) {
            $flightnr = explode("\n", $order->getShippingAddress()->getFlightNumber());
            $flightDate = explode(" ",$order->getShippingAddress()->getFlightDeparture());
            $proFlightDate =  str_replace("-", "", $flightDate[0]);
            $flightPax = Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFlightPax());
            $name = explode("//", $flightPax);

            $dept = $order->getShippingAddress()->getFlightOriginAirport();
            $dest = $order->getShippingAddress()->getFlightDestinationAirport();

            $param = new stdClass();
            $param->AirlineID = "KL";
            $param->PreOrderID = $order->getIncrementId();
            $param->GroundDelivery = 0;
            $param->FlightPrefix = $order->getShippingAddress()->getFlightCarrier();
            $param->FlightNr = $order->getShippingAddress()->getFlightNumber();
            $param->FlightSuffix = "";
            $param->LegSerial = 1;
            $param->LocalDepDate = $proFlightDate;
            $param->DeptAirport = $dept;
            $param->DestAirport = $dest;
            $param->PassengerNameFirst = $name[1] != "" ? $name[1] : Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFlightFirstName());
            $param->PassengerNameLast = $name[0] != "" ? $name[0] : Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFlightLastName());
            $param->LoyaltyNr = ($order->getDecryptFbNumber() != '') ? $order->getDecryptFbNumber() : "";
            $param->InformationText = "";
            $param->PreOrderProducts = $items;
        } else {
            $param = new StdClass();
            $param->AirlineID = "KL";
            $param->PreOrderID = $order->getIncrementId();
            $param->GroundDelivery = 1;
            $param->FlightPrefix = "";
            $param->FlightNr = "";
            $param->FlightSuffix = "";
            $param->LegSerial = 1;
            //$param->LocalDepDate = date("Ymd");
            $param->LocalDepDate = Mage::getModel('core/date')->date('Ymd');
            $param->DeptAirport = "";
            $param->DestAirport = "";
            $param->PassengerNameFirst = Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getFirstname());
            $param->PassengerNameLast = Mage::getModel('core/encryption')->decrypt($order->getShippingAddress()->getLastname());
            $param->LoyaltyNr = ($order->getDecryptFbNumber() != '') ? $order->getDecryptFbNumber() : "";
            $param->InformationText = "";
            $param->PreOrderProducts = $items;
        }

        try {
            $request->PreOrders = new ArrayObject();
            /**
             * Encode each array element with SoapVar.  Parameter 5 is the name of the
             * XML element you want to use.  This only seems to work within
             * an ArrayObject.
             */
            $param = new SoapVar($param, SOAP_ENC_OBJECT, null, null, 'PreOrder');

            $request->PreOrders->append($param);

            $result = $this->getSoapClient()->PreOrderCreate($request);
			
        } catch(Exception $e) {
            // Log the exception thrown
			
			Mage::throwException(
                Mage::helper('airline_ecustomerorder')->__($e->faultstring)
            );
        }
        // Return the Soap response for successful transaction
        return true;
    }
}
