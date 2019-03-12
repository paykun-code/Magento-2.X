<?php

namespace Paykun\Checkout\Controller\Errors;

/**
 * Class ErrorCodes
 * @package Paykun\Checkout\Errors
 */
class ErrorCodes {

    const INVALID_MERCHANT_ID_CODE      = 100;
    const INVALID_MERCHANT_ID_STRING    = "Merchant ID is not valid";

    const INVALID_ACCESS_TOKEN_CODE     = 101;
    const INVALID_ACCESS_TOKEN_STRING   = "Access Token is not valid";

    const INVALID_API_SECRETE_CODE      = 102;
    const INVALID_API_SECRETE_STRING    = "Api Secret is not valid";

    const INVALID_ORDER_ID_CODE         = 103;
    const INVALID_ORDER_ID_STRING       = "Order ID must be longer than 10 characters";

    const INVALID_PURPOSE_CODE          = 104;
    const INVALID_PURPOSE_STRING        = "Please provide Payment Purpose";

    const INVALID_AMOUNT_CODE           = 105;
    const INVALID_AMOUNT_STRING         = "Amount should not be less than 10 rupees";

    const INVALID_SUCCESS_URL_CODE      = 106;
    const INVALID_SUCCESS_URL_STRING    = "Success Url is not valid";

    const INVALID_FAIL_URL_CODE         = 107;
    const INVALID_FAIL_URL_STRING       = "Failure Url is not valid";

    const MISSING_CUSTOMER_NAME_CODE    = 109;
    const MISSING_CUSTOMER_NAME_STRING  = "Please provide Customer Name";

    const INVALID_CUSTOMER_NAME_CODE    = 110;
    const INVALID_CUSTOMER_NAME_STRING  = "Only letters and white space allowed as Customer Name";

    const MISSING_CUSTOMER_EMAIL_CODE   = 111;
    const MISSING_CUSTOMER_EMAIL_STRING = "Please provide Customer Email";

    const INVALID_CUSTOMER_EMAIL_CODE   = 112;
    const INVALID_CUSTOMER_EMAIL_STRING = "Customer Email is not valid";

    const INVALID_MOBILE_NO_CODE        = 113;
    const INVALID_MOBILE_NO_STRING      = "Customer Mobile Number is not valid";

    const INVALID_DATA_PROVIDED_CODE    = 114;
    const INVALID_DATA_PROVIDED_STRING  = "Provided data is not proper to make this transaction";

    const INVALID_COUNTRY_NAME_CODE     = 115;
    const INVALID_COUNTRY_NAME_STRING   = "country must be longer than 4 characters";

    const INVALID_STATE_NAME_CODE       = 116;
    const INVALID_STATE_NAME_STRING     = "state must be longer than 2 characters";

    const INVALID_CITY_NAME_CODE        = 117;
    const INVALID_CITY_NAME_STRING      = "city must be longer than 2 characters";

    const INVALID_POSTAL_CODE_CODE      = 118;
    const INVALID_POSTAL_CODE_STRING    = "Postal code is not valid";

    const INVALID_ADDRESS_CODE          = 119;
    const INVALID_ADDRESS_STRING        = "address can not be left blank, should be longer than 4 characters";

    const INVALID_PAYMENT_DETAIL_CODE   = 120;
    const INVALID_PAYMENT_DETAIL_STRING = "Invalid payment detail";

    const INVALID_SYSTEM_CONFIG_CODE    = 121;
    const INVALID_SYSTEM_CONFIG_STRING  = "An error occurred while fetching system configuration.";

    const SESSION_ORDER_ID_NOT_FOUND_CODE          = 123;
    const SESSION_ORDER_ID_NOT_FOUND_STRING        = "Order not found";

    const CURRIENCY_NOT_ALLOEWD_CODE          = 125;
    const CURRIENCY_NOT_ALLOEWD_STRING        = "Given currency is not valid for Paykun, please provide only Indian Rs.";

    const CANCELLED_ORDER_MAIL_COULD_NOT_SEND_CODE          = 126;
    const CANCELLED_ORDER_MAIL_COULD_NOT_SEND_STRING        = "Couldn't send mail from your environment please check your SMTP settings.";

}
