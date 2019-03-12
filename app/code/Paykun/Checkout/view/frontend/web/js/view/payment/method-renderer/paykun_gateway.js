/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Paykun_Checkout/js/action/set-payment-method-action',
        'mage/url',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, Component, setPaymentMethodAction, urlBuilder, errorProcessor, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                redirectAfterPlaceOrder: false,
                template: 'Paykun_Checkout/payment/form',
                transactionResult: ''

            },

            afterPlaceOrder: function () {

                this.startPayKunPaymentProcessor();
                setPaymentMethodAction(this.messageContainer);
                return false;

            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;

            },

            getCode: function() {

                return 'paykun_gateway';

            },

            getData: function() {

                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult()
                    }
                };

            },

            getTransactionResults: function() {

                return _.map(window.checkoutConfig.payment.paykun_gateway.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });

            },



            startPayKunPaymentProcessor: function () {

                var serviceUrl = urlBuilder.build('paykun_checkout_gateway/index/paykunprocessor'); // Our controller to re-collect the totals
                var currentObj = this;
                $.ajax({
                    url : serviceUrl,
                    type : 'POST',
                    data: {
                        format: 'json'
                    },
                    dataType:'json',
                    success : function(response) {

                        if(response.success == true) {

                            currentObj.prepareGatewayForm(response.formData);

                            /*var Response = {"responseText": "This is just testing message"};
                            var error = JSON.parse(JSON.stringify(Response.responseText));
                            console.log(error);

                            errorProcessor.process(JSON.stringify(Response.responseText), this.messageContainer);*/

                        } else  {

                            fullScreenLoader.stopLoader();
                            alert(response.message);
                            //errorProcessor.process(response.message, this.messageContainer);

                        }

                    },
                    error : function(response,error) {

                        fullScreenLoader.stopLoader();
                        errorProcessor.process(response, this.messageContainer);

                    }
                });

            },

            prepareGatewayForm: function (formData) {

                var form                = document.createElement("form");
                var encrypted_request   = document.createElement("input");
                var merchant_id         = document.createElement("input");
                var access_token        = document.createElement("input");

                //Form attributes
                form.method = "POST";
                form.action = formData.gateway_url;
                form.name   = "server_request";
                form.target	= "_top";

                //hidden fields preparation
                encrypted_request.type	= "hidden";
                merchant_id.typee		= "hidden";
                access_token.type 		= "hidden";

                encrypted_request.name	= "encrypted_request";
                merchant_id.name		= "merchant_id";
                access_token.name 		= "access_token";

                encrypted_request.value	= formData.encrypted_request;
                merchant_id.value		= formData.merchant_id;
                access_token.value 		= formData.access_token;

                form.appendChild(encrypted_request);
                form.appendChild(merchant_id);
                form.appendChild(access_token);

                document.body.appendChild(form);
                form.submit();

            }

        });
    }
);