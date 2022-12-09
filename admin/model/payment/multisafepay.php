<?php
namespace Opencart\Admin\Model\Extension\Multisafepay\Payment;

require_once(DIR_EXTENSION . 'multisafepay/system/library/multisafepay.php');

use Opencart\System\Engine\Model;

class Multisafepay extends Model {

    protected $registry;

    public function __construct($registry) {
        parent::__construct($registry);
    }

    /**
     * This function add a new order history element
     *
     * @param int $order_id
     * @param int $order_status_id
     * @param string $comment
     * @param bool $notify
     *
     * @return void
     */
    public function addOrderHistory(int $order_id, int $order_status_id, string $comment = '', bool $notify = false): void
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = '" . $order_status_id . "', `date_modified` = NOW() WHERE `order_id` = '" . $order_id . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . $order_id . "', `order_status_id` = '" . $order_status_id . "', `notify` = '" . (int)$notify . "', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");
    }

    /**
     * Function that check if a new version of the MSP Plugin exists, comparing the current version with
     * the latest release tag in GitHub
     *
     * @return bool
     */
    public function checkForNewVersions(): bool
    {
        $this->registry->set('multisafepay', new \Opencart\System\Library\Multisafepay($this->registry));
        $current_version = $this->multisafepay->getPluginVersion();
        $options = array(
            'http'=> array(
                'method'=>"GET",
                'header'=>"Accept-language: en\r\n" .
                    "Cookie: foo=bar\r\n" .
                    "User-Agent: PHP\r\n"
            )
        );
        $context = stream_context_create($options);
        $content = file_get_contents('https://api.github.com/repos/multisafepay/opencart-4/releases/latest', false, $context);
        if ($content) {
            $information = json_decode($content, false);
            if (!empty($information->tag_name) && ($information->tag_name > $current_version)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the ID of the next invoice number
     *
     * @param int $order_id
     *
     * @return int
     */
    public function getNextInvoiceId(int $order_id): int
    {
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);

        $invoice_no = 1;
        if ($order_info && !$order_info['invoice_no']) {
            $query = $this->db->query("SELECT MAX(`invoice_no`) AS `invoice_no` FROM `" . DB_PREFIX . "order` WHERE `invoice_prefix` = '" . $this->db->escape($order_info['invoice_prefix']) . "'");
            if ($query->row['invoice_no']) {
                $invoice_no = (int)$query->row['invoice_no'] + 1;
            }
        }
        return $invoice_no;
    }

    /**
     * Returns the ID of the next invoice number
     *
     * @param string $key
     * @param int $store_id
     *
     * @return mixed
     */
    public function getSettingValue(string $key, int $store_id = 0): mixed
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '" . $store_id . "' AND `key` = '" . $this->db->escape($key) . "'");
        if ($query->num_rows) {
            if ($query->row['serialized']) {
                return json_decode($query->row['value'], true);
            }
            return $query->row['value'];
        }
        return null;
    }

    /**
     * Remove coupons, vouchers, reward points, and affiliate commissions in transactions fully refunded
     *
     * @param int $order_id
     *
     * @return void
     */
    public function removeCouponsVouchersRewardsPointsAffiliateCommission(int $order_id): void
    {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "coupon_history` WHERE `order_id` = '" . $order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "voucher_history` WHERE `order_id` = '" . $order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_reward` WHERE `order_id` = '" . $order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_transaction` WHERE `order_id` = '" . $order_id . "'");
    }
}
