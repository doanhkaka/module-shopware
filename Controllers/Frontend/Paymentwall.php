<?php

if (!class_exists('Paymentwall_Config'))
    require_once(dirname(dirname(dirname(__FILE__))). "/lib/lib/paymentwall.php");

class Shopware_Controllers_Frontend_Paymentwall extends Shopware_Controllers_Frontend_Payment
{
    private $config;
    const ORDER_OPEN = 0;
    const ORDER_PROCESS = 1;
    const ORDER_COMPLETED = 2;
    const ORDER_CANCELED = 8;
    const PAYMENT_COMPLETELY_PAID = 12;
    const PAYMENT_CANCELED = 20;


    public function init()
    {
        $this->config = Shopware()->Plugins()->Frontend()->Paymentwall()->Config();

        Paymentwall_Config::getInstance()->set(array(
            'api_type' => Paymentwall_Config::API_GOODS,
            'public_key' => trim($this->config->get("projectKey")), // available in your Paymentwall merchant area
            'private_key' => trim($this->config->get("secretKey")) // available in your Paymentwall merchant area
        ));
    }

    public function indexAction()
    {
        if (!empty($_SESSION['order'])) {
            $orderNumber = $_SESSION['order'];
        } else {
            $orderNumber = $this->saveOrder($this->createPaymentUniqueId(), md5($this->createPaymentUniqueId()), self::ORDER_OPEN);
            $_SESSION['order'] = $orderNumber;
        }

        $orderId = $this->getOrderIdByOrderNumber($orderNumber);
        $params = array(
            'orderId' => $orderId,
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'user' => $this->getUser(),
        );

        $this->View()->orderId = $orderId;
        $this->View()->iframe = $this->getWidget($params);
    }

    /**
     * Get iframe widget pwlocal
     *
     * @param array $params
     */
    private function getWidget($params)
    {
        $widget = new Paymentwall_Widget(
            !empty($params['user']['additional']['user']['customerId']) ? $params['user']['additional']['user']['customerId'] : $_SERVER['REMOTE_ADDR'], // id of the end-user
            trim($this->config->get("widgetCode")),
            array(
                new Paymentwall_Product(
                    $params['orderId'],
                    $params['amount'],
                    $params['currency'],
                    'Order #' . $params['order_number'],
                    Paymentwall_Product::TYPE_FIXED,
                    '',
                    '',
                    false
                )
            ),
            // additional parameters
            array_merge(
                array(
                    'integration_module' => 'shopware',
                    'test_mode' => ('Yes' == trim($this->config->get("testMode"))) ? 1 : 0
                ),
                $this->getUserProfileData($params['user'])
            )
        );

        return $widget->getHtmlCode();
    }

    /**
     * Build User Profile Data
     *
     * @param array $params
     */
    protected function getUserProfileData($params)
    {
        $billing = $params['billingaddress'];
        
        return array(
            'customer[city]' => $billing['city'],
            'customer[state]' => $billing['stateID'],
            'customer[address]' => $billing['street'],
            'customer[country]' => $billing['country'],
            'customer[zip]' => $billing['zipcode'],
            'customer[firstname]' => $billing['firstname'],
            'customer[lastname]' => $billing['lastname'],
            'email' => $params['additional']['user']['email']
        );
    }

    /**
     * Pingback update Order, Payment status
     *
     * @param array $_GET
     */
    public function pingbackAction()
    {
        unset($_GET['controller']);
        unset($_GET['action']);

        $pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
        $orderId = $pingback->getProductId();

        $order = Shopware()->Modules()->Order();
        
        if ($pingback->validate(true)) {
            if ($pingback->isDeliverable()) {
                $order->setOrderStatus($orderId, self::ORDER_PROCESS);
                $order->setPaymentStatus($orderId, self::PAYMENT_COMPLETELY_PAID);
            } elseif ($pingback->isCancelable()) {
                $order->setOrderStatus($orderId, self::ORDER_CANCELED);
                $order->setPaymentStatus($orderId, self::PAYMENT_CANCELED);
            }
            echo "OK";
        } else {
            echo $pingback->getErrorSummary();
        }
        die;
    }

    private function getOrderIdByOrderNumber($orderNumber)
    {
        return Shopware()->Db()->fetchOne("SELECT id FROM s_order WHERE ordernumber = ?", array($orderNumber));
    }

    private function getOrderStatusByOrderId($orderId)
    {
        return Shopware()->Db()->fetchOne("SELECT status FROM s_order WHERE id = ?", array($orderId));
    }

    /**
     * Check order status, redirect to thank you page
     *
     * @param integer $orderId
     */
    public function redirectAction()
    {
        $orderId = $this->Request()->getParam("orderId");
        $status = $this->getOrderStatusByOrderId($orderId);
        if ($status) {
            unset($_SESSION['order']);
        }
        echo $status;
        die;
    }
}

