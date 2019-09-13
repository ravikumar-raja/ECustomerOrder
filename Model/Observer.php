<?php

class Airline_ECustomerOrder_Model_Observer {

    // update magentos stock with stock from prologistica when product is viewed
    // then let magento handle everything else
    public function productView(Varien_Event_Observer $observer) {
        try {
            $helper = Mage::helper('airline_ecustomerorder');
            $event = $observer->getEvent();
            $controller = $observer->getControllerAction();
            $productId = (int)$controller->getRequest()->getParam('id');
            $_product = Mage::getModel('catalog/product')->load($productId);
            $_product_id = $_product->getSku();
            $stockData = $_product->getStockItem();

            // if we are not managing stock or if we should not check stock with prologistica return
            if ($stockData->getManageStock() == 0 || $stockData->getCheckStock() == 0) {
                return;
            }

            // check availible quantity in prologistica
            $model = Mage::getModel('Airline_ECustomerOrder_Model_Api');
            $_result = $model->GetStock($_product_id);

            // if product stock is not the same as stock in prologistica
            if ($stockData->getData('qty') != $_result->PresentStock->PresentQty) {
                // set product stock
                Mage::devlog("updating product stock from [{$stockData->getData('qty')}] to [{$_result->PresentStock->PresentQty}]", null, "prologistica_service.log", 3);
                $stockData->setData('qty', $_result->PresentStock->PresentQty)->setData('is_in_stock', 1)->save();
            }

        } catch (Exception $e) {
            Mage::devlog("ERROR: " .$e->getMessage(), null, "prologistica_service.log", 2);
            if ($e->getMessage() == "Product not found") {
                $stockData->setData('qty', 0)->setData('is_in_stock', 1)->save();
            } else {
                //Mage::getSingleton('checkout/session')->addError($helper->__("Service is currently unavailible. Please try again later."));
            }
        }
    }

    // update magentos stock with stock from prologistica when product is added to cart
    // then let magento handle everything else
    public function addtoCart(Varien_Event_Observer $observer)
    {
        try {
            $helper = Mage::helper('airline_ecustomerorder');
            $event = $observer->getEvent();
            $request = $observer->getRequest();
            $_product = $event->getProduct();
            $_product_id = $_product->getSku();
            $_quantity = $event->getQuantity();
            $stockData = $_product->getStockItem();

            // if we are not managing stock or if we should not check stock with prologistica return
            if ($stockData->getManageStock() == 0 || $stockData->getCheckStock() == 0) {
               
                return;
            }

            // check availible quantity in prologistica
            Mage::devlog("checking stock ...", null, "prologistica_service.log", 3);
            $model = Mage::getModel('Airline_ECustomerOrder_Model_Api');
            $_result = $model->GetStock($_product_id);

            // if product stock is not the same as stock in prologistica
            if ($stockData->getData('qty') != $_result->PresentStock->PresentQty) {
                // set product stock
                Mage::devlog("updating product stock from [{$stockData->getData('qty')}] to [{$_result->PresentStock->PresentQty}]", null, "prologistica_service.log", 3);
                $stockData->setData('qty', $_result->PresentStock->PresentQty)->setData('is_in_stock', 1)->save();
            }

            // if product quantity in prologistica is not sufficient throw an error
            // for now we dont use that, as we let magento handle everything
            if ($_result->PresentStock->PresentQty < $_quantity && $stockData->getData('backorders') != 1) {
                // we might want to throw an error in case of unsuficient quantity
                Mage::throwException ("Insuficient quantity or product not in stock.");
            }

            // check if this is becomming a mixed order and warn customer
            $cart = Mage::getModel('checkout/cart')->getQuote();
            if (($cart->hasHomeDeliveryItems() && $request->getDeliveryMethod() == 2) || ($cart->hasOnboardDeliveryItems() && $request->getDeliveryMethod() == 1)) {
                Mage::getSingleton('checkout/session')->addError($helper->__("You have onboard and home delivery products in your cart. To continue with order you should remove one type."));
            }


        } catch (Exception $e) {
            Mage::devlog("ERROR: " .$e->getMessage(), null, "prologistica_service.log", 2);
            // until now all we do is set quantity of product.
            // but this event fires after adding to cart, so even if quantity is too high product is already in cart.
            // this is ok, cart will show error, and user will have a chance to change quantity directly in cart.

            // if we however want to redirect user back to product page and remove product from cart we also need to do this:
            // if quantity in cart is bigger than the quantity in stock

            // add error to session in case something went wrong (connection failed or something)
            //Mage::getSingleton('checkout/session')->addError($e->getMessage());
            if ($e->getMessage() == "Product not found") {
                $stockData->setData('qty', 0)->setData('is_in_stock', 1)->save();
            }
            if ($e->getMessage() == "Insuficient quantity or product not in stock.") {
                //Mage::getSingleton('checkout/session')->addError($helper->__("Product is out of stock."));
            } else {
                Mage::getSingleton('checkout/session')->addError($helper->__("Service is currently unavailible. Please try again later."));
            }

            // redirect back to product page
            Mage::app()->getFrontController()->getRequest()->setParam('return_url',$_product->getProductUrl());
        }
    }

    public function beginCheckout(Varien_Event_Observer $observer)
    {
        try {
            $_event = $observer->getEvent();
            $_request = $_event->getRequest();
            $_session= Mage::getSingleton('checkout/session');
            $helper = Mage::helper('airline_ecustomerorder');

            // flag to signal an error
            $_error = false;

            // check if its mixed order, and if it is, block it
            $cart = Mage::getModel('checkout/cart')->getQuote();
            if ($cart->hasHomeDeliveryItems() && $cart->hasOnboardDeliveryItems()) {
                Mage::getSingleton('checkout/session')->addError($helper->__("You have onboard and home delivery products in your cart. To continue with order you should remove one type."));
                Mage::app()->getResponse()->setRedirect(Mage::helper('checkout/cart')->getCartUrl());
                return;
            }

            // get cart items
            foreach($_session->getQuote()->getAllItems() as $item)
            {
                $stockData = $item->getProduct()->getStockItem();
                $productid = $item->getProduct()->getSku();

                Mage::devlog("checking product [$productid] [{$item->getName()}]", null, "prologistica_service.log", 3);
                // if we are not managing stock or if we should not check stock with prologistica return
                // if values are not set we assume true
                if ($stockData->getManageStock() == 0 || $stockData->getCheckStock() == 0) {
                    Mage::devlog("NOT checking stock with prologistica [managestock: {$stockData->getManageStock()}] [checkstock: {$stockData->getCheckStock()}]", null, "prologistica_service.log", 3);
                    continue;
                } else {
                    Mage::devlog("checking stock with prologistica [managestock: {$stockData->getManageStock()}] [checkstock: {$stockData->getCheckStock()}]", null, "prologistica_service.log", 3);
                }


                $productqty = $item->getQty();
                $_updated = false;
                $_error = false;

                // call prologistica and try to reserve specified number of items
                // if OK, prologistica should return confirmation and maybe new number of items
                // if FAIL, prologistica should tell us how many items are still availible
                try {
                    $model = Mage::getModel('Airline_ECustomerOrder_Model_Api');
                    $response = $model->GetStock($productid, $productqty);

                    // for test purposes i set this value to 5
                    $nr = $response->PresentStock->PresentQty;
                    if ($productqty > $nr && $stockData->getData('backorders') != 1) {
                        // if required quantity is not availible, update cart quantity to max availible and set error to true
                        // instead of just setting error to true we might want to add custom item error
                        Mage::devlog("updating cart from [{$productqty}] to [{$nr}]", null, "prologistica_service.log", 2);
                        $_updated = true;
                        $item->setQty($nr);
                    }

                    // update product quantity in magento

                    Mage::devlog("updating product stock from [{$stockData->getData('qty')}] to [{$nr}]", null, "prologistica_service.log", 3);
                    $stockData->setData('qty', $nr)->save();

                } catch(Exception $e) {
                    Mage::devlog("ERROR: " .$e->getMessage(), null, "prologistica_service.log", 2);
                    $_error = true;
                    if ($e->getMessage() == "Product not found") {
                        $_session->addError($helper->__("Product '%s' is out of stock.", $item->getName()));
                    } else {
                        $_session->addError($helper->__("Service is currently unavailible. Please try again later."));
                        break;
                    }
                }
            }

            // if any warnings are set on cart redirect back. we dont want to just throw an error, becouse we want
            // to check all cart items not just until the first one with error. setting cart item errors might be better
            // as magento would then handle showing the errors and redirecting.
            if ($_error || $_updated)
            {
                // add error informing user that we updated quantities
                if ($_updated) {
                    Mage::devlog("Product quantities were updated!", null, "prologistica_service.log", 2);
                    $_session->addError($helper->__("Product quantities were updated!"));
                }

                // redirect back to cart
                Mage::devlog("Redirecting back to cart!", null, "prologistica_service.log", 2);
                Mage::app()->getResponse()->setRedirect(Mage::helper('checkout/cart')->getCartUrl());
            }
        } catch (Exception $e) {
            // we might want to show custom error in case that connection with prologistica failed etc
            Mage::devlog("ERROR: " .$e->getMessage(), null, "prologistica_service.log", 2);
            $_session->addError($e->getMessage());

            // redirect back to cart (uncomment to block checkout if service is not working)
            Mage::app()->getResponse()->setRedirect(Mage::helper('checkout/cart')->getCartUrl());
        }
    }

	/* Method listening to the Event checkout_type_save_order_after
 	* Send Order cancellation information to prologistica
 	* Input : Soap request with Order ID
 	* Success Response : Empty if the request is sucsess\
 	* Failure Response : Soap Failure message  
 	*/
    public function OrderCancel(Varien_Event_Observer $observer)
    {
    	// Get the Order object
        try {
            $order = $observer->getEvent()->getOrder();

            // get the Pre-Order ID
            $_id = $order->getId();

            // Get the status of the order if we need to validate it.
            $_status = $order->getStatus();

            // Call the Airline_ECustomerOrder_Model_Api to send order cancellation Soap request.
            $model = Mage::getModel('Airline_ECustomerOrder_Model_Api');

            Mage::devlog("Canceling order [$_id] with status [$_status]", null, "prologistica_service.log", 2);
            // Get the soap response from prologistica from the OrderCancel Method.
            $_result= $model->OrderCancel($_id);
        } catch (Exception $e) {
            Mage::devlog("ERROR: " .$e->getMessage(), null, "prologistica_service.log", 2);
        }

    }#End of OrderCancel Method

	/*Observer method listenting to the event to send New order information to Prologistica	 
	 * Call the method Airline_ECustomerOrder_Model_Api->OrderCreation to send the soap request
	 * Result : Soap response from the Prologistica
	 */
    public function OrderCreate(Varien_Event_Observer $observer)
    {
        // if state was changed to processing:
        //if ($observer->getOrder()->getState() != "processing" OR $observer->getOrder()->getStatus() != 'processing') return;

        // Get the Order object
        $order = $observer->getEvent()->getOrder();

        // get the Pre-Order ID
        $_id = $order->getId();

        // Get the status of the order if we need to validate it.
        $_status = $order->getStatus();
        $_state = $order->getState();

        try {
            if ($order->getSentProlo() == 1) {
                return;
            }

            Mage::devlog("Creating order [$_id] with status [$_state][$_status]", null, "prologistica_service.log", 2);
            // Call the Airline_ECustomerOrder_Model_Api to send order cancellation Soap request.
            $model = Mage::getModel('Airline_ECustomerOrder_Model_Api');

            // Get the soap response from prologistica from the OrderCancel Method.
            $_result= $model->OrderCreate($_id);
            $order->setSentProlo(1)->save();

            if ($_result === true) {
                $order->setState('processing', 'sent_prolo', 'order sent to prologistica')->save();
            } else if ($_result === "nothingtosend") {
                $order->addStatusHistoryComment("nothing to send to prologistica");
            }else {
                Mage::helper('airline_ecustomerorder')->_addRetryEntry($_id, 'prologistica');
                $order->setState('processing', 'fail_prolo', 'order was not sent to prologistica')->save();
            }


        } catch (Exception $e) {
            //Mage::logException($e);
            Mage::devlog("ERROR: " .$e->getMessage(), null, "prologistica_service.log", 2);
            Mage::helper('airline_ecustomerorder')->_addRetryEntry($_id, 'prologistica');
            $order->setState('processing', 'fail_prolo', $e->getMessage())->save();
        }

    } #End of OrderCreation Method

    public function StatusChanged(Varien_Event_Observer $observer) {
        $event = $observer->getEvent();
        $status = $observer->getStatus();
        $state = $observer->getState();

        if ($status == "declined_prolo" || $status == "70" || $status == "99") {
            $order= $observer->getOrder();
            $order->save();
            $order->setState("canceled", "refund")->save();
            $observer->setState("canceled");
            $observer->setStatus("refund");
        } else if ($status == "refund") {
             
            $observer->getOrder();
            $templateId = "sales_email_order_template_refund";
            $emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);
            $vars = array('order' => $order, 'order_id'=>$order->getIncrementId());

            $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $order->getStoreId()));
            $emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $order->getStoreId()));

            $to = Mage::getStoreConfig('trans_email/airline_aftersales/name', $order->getStoreId());
            $toMail = Mage::getStoreConfig('trans_email/airline_aftersales/email', $order->getStoreId());
            $emailTemplate->send($toMail, $to, $vars);
        }


    }

    public function StateChanged(Varien_Event_Observer $observer) {
        $event = $observer->getEvent();
        $status = $observer->getStatus();
        $state = $observer->getState();

        if ($status == "refund") {
            // send the refund email
            $order=$observer->getOrder();
            $templateId = "sales_email_order_template_refund";
            $emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);
            $vars = array('order' => $order, 'order_id'=>$order->getIncrementId());
     
            $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $order->getStoreId()));
            $emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $order->getStoreId()));

            $to = Mage::getStoreConfig('trans_email/airline_aftersales/name', $order->getStoreId());
            $toMail = Mage::getStoreConfig('trans_email/airline_aftersales/email', $order->getStoreId());
            $emailTemplate->send($toMail, $to, $vars);
        }
    }

}# end of Class
