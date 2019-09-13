<?xml version="1.0"?>
<!--
/**
 * AIRLINE CustomerOrder Services Module
 *
 * NOTICE OF LICENSE
 *
 *
 * @category   Airline
 * @package    Airline_ECustomerOrder
 */
-->
<config>
    <modules>
        <Airline_ECustomerOrder>
            <version>1.0.5</version>
        </Airline_ECustomerOrder>
    </modules>
    <global>
        <models>
            <airline_ecustomerorder>
                <class>Airline_ECustomerOrder_Model</class>
                <resourceModel>Airline_ECustomerOrder_resource</resourceModel>
            </airline_ecustomerorder>
            <airline_ecustomerorder_resource>
                <class>Airline_ECustomerOrder_Model_Resource</class>
            </airline_ecustomerorder_resource>
            <checkout>
                <rewrite>
                    <cart>Airline_ECustomerOrder_Model_Checkout_Cart</cart>
                </rewrite>
            </checkout>
        </models>
        <helpers>
            <airline_ecustomerorder>
                <class>Airline_ECustomerOrder_Helper</class>
            </airline_ecustomerorder>
        </helpers>
        <events>
            <!-- this event is called before product is added to cart. we check for quantity with prologistica -->
            <controller_action_predispatch_catalog_product_view>
                <observers>
                    <controller_action_predispatch_catalog_product_view_handler>
                        <type>model</type>
                        <class>airline_ecustomerorder/observer</class>
                        <method>productView</method>
                    </controller_action_predispatch_catalog_product_view_handler>
                </observers>
            </controller_action_predispatch_catalog_product_view>

            <!-- this event is called before product is added to cart. we check for quantity with prologistica -->
            <checkout_cart_product_add_before>
                <observers>
                    <checkout_cart_product_add_before_handler>
                        <type>model</type>
                        <class>airline_ecustomerorder/observer</class>
                        <method>addtoCart</method>
                    </checkout_cart_product_add_before_handler>
                </observers>
            </checkout_cart_product_add_before>

            <!-- this event is called before when we begin checkout. we check and reserve quantity with prologistica -->
            <onepage_checkout_index_before>
                <observers>
                    <onepage_checkout_index_before_handler>
                        <type>model</type>
                        <class>airline_ecustomerorder/observer</class>
                        <method>beginCheckout</method>
                    </onepage_checkout_index_before_handler>
                </observers>
            </onepage_checkout_index_before>

            <!-- this event is called after order is canceled. we cancel order in prologistica. -->
            <order_cancel_after>
                <observers>
                    <airline_CustomerOrder>
                        <type>singleton</type>
                        <class>airline_ECustomerOrder_model_observer</class>
                        <method>OrderCancel</method>
                    </airline_CustomerOrder>
                </observers>
            </order_cancel_after>

            <!-- this event is called after opayment with adyen succeeded. we confirm order in prologistica. -->
            <!--<sales_order_status_after>-->
            <prologistica_send_order>
                <observers>
                    <airline_CustomerOrder>
                        <type>singleton</type>
                        <class>airline_ECustomerOrder_model_observer</class>
                        <method>OrderCreate</method>
                    </airline_CustomerOrder>
                </observers>
            </prologistica_send_order>

            <checkout_type_onepage_save_order_after>
                <observers>
                    <airline_CustomerOrder>
                        <type>singleton</type>
                        <class>airline_ECustomerOrder_model_observer</class>
                        <method>test1</method>
                    </airline_CustomerOrder>
                </observers>
            </checkout_type_onepage_save_order_after>

            <sales_order_state_after>
                <observers>
                    <airline_CustomerOrder>
                        <type>singleton</type>
                        <class>airline_ECustomerOrder_model_observer</class>
                        <method>StateChanged</method>
                    </airline_CustomerOrder>
                </observers>
            </sales_order_state_after>
            <sales_order_status_after>
                <observers>
                    <airline_CustomerOrder>
                        <type>singleton</type>
                        <class>airline_ECustomerOrder_model_observer</class>
                        <method>StatusChanged</method>
                    </airline_CustomerOrder>
                </observers>
            </sales_order_status_after>
        </events>
        <resources>
            <airline_ecustomerorder_setup>
                <setup>
                    <module>Airline_ECustomerOrder</module>
                    <class>Airline_Rule_Model_Resource_Setup</class>
                </setup>
            </airline_ecustomerorder_setup>
        </resources>
    </global>
</config>
