<?php
/**
 * Created by PhpStorm.
 * User: PayKun Two
 * Date: 16-10-2018
 * Time: 12:08
 */

namespace Paykun\Checkout\Logger;


class Handler extends \Magento\Framework\Logger\Handler\Base {
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/paykun.log';

}