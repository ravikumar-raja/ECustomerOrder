<?php
class Airline_ECustomerOrder_Model_Checkout_Cart extends Mage_Checkout_Model_Cart
{
    /**
     * Create checkout_cart_product_add_before event observer
     *
     * @param   int|Mage_Catalog_Model_Product $productInfo
     * @param   mixed $requestInfo
     * @return  Mage_Checkout_Model_Cart
     */
    public function addProduct($productInfo, $requestInfo=null)
    {
        $product = $this->_getProduct($productInfo);
        $request = $this->_getProductRequest($requestInfo);
        $quantity = $request->getQty();

        Mage::dispatchEvent('checkout_cart_product_add_before', array(
            'product' => $product,
            'request' => $request,
            'quantity' => $quantity
        ));
        return parent::addProduct($productInfo, $requestInfo);
    }
}
