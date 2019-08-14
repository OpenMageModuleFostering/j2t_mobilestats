<?php
/**
 * J2T MobileStats
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@j2t-design.com so we can send you a copy immediately.
 *
 * @category   Magento extension
 * @package    J2t_Mobilestats
 * @copyright  Copyright (c) 2012 J2T DESIGN. (http://www.j2t-design.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class J2t_Mobilestats_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        
        $session = Mage::getSingleton('admin/session');
        /** @var $session Mage_Admin_Model_Session */
        $request = Mage::app()->getRequest();
        
        
        if ($request->getPost('login')) {
            $postLogin  = $request->getPost('login');

            $username   = isset($postLogin['username']) ? $postLogin['username'] : '';
            $password   = isset($postLogin['password']) ? $postLogin['password'] : '';
            //$session->login($username, $password, $request);

            $user = Mage::getModel('admin/user');
            $user->login($username, $password);
            if ($user->getId()) {

                echo 'OK';
            } else {
                echo 'KO';
            }
        }
        die;
    }
    
    
    public function loginAction()
    {
        $return_value = array();
        $session = Mage::getSingleton('admin/session');
        /** @var $session Mage_Admin_Model_Session */
        $request = Mage::app()->getRequest();
        if ($request->getParam('username') && $request->getParam('password')) {

            $username   = ($request->getParam('username')) ? $request->getParam('username') : '';
            $password   = ($request->getParam('password')) ? $request->getParam('password') : '';

            $user = Mage::getModel('admin/user');
            $user->login($username, $password);
            if ($user->getId()) {

                $return_value['state'] = 'OK';
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        echo $_GET['callback'] . '(' . json_encode($return_value) . ');';
        die;
    }
    
    protected function loginUser($username, $password)
    {
        $return_value = array();
        $session = Mage::getSingleton('admin/session');        
        if ($username && $password) {

            $user = Mage::getModel('admin/user');
            $user->login($username, $password);
            if ($user->getId()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    
    public function dashboardAction(){
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                //last 24h      >> 24h
                //last 7 days   >> 7d
                //current month >> 1m
                //YTD           >> 1y
                //2YTD          >> 2y
                
                $period = ($request->getParam('period') != "") ? $request->getParam('period') : "24h";
                $type   = ($request->getParam('type') != "") ? $request->getParam('type') : "quantity";
                
                ///////////////////////////// Summary /////////////////////////////
                
                $isFilter = $request->getParam('store') || $request->getParam('website') || $request->getParam('group');
                $period = $request->getParam('period', '24h');

                /* @var $collection Mage_Reports_Model_Mysql4_Order_Collection */
                $collection = Mage::getResourceModel('reports/order_collection')
                    ->addCreateAtPeriodFilter($period)
                    ->calculateTotals($isFilter);
                
                $store_currency = Mage::app()->getStore()->getId();
                
                if ($request->getParam('store')) {
                    $collection->addFieldToFilter('store_id', $request->getParam('store'));
                    $store_currency = $request->getParam('store');
                } else if ($request->getParam('website')){
                    $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                    $collection->addFieldToFilter('store_id', array('in' => $storeIds));
                } else if ($request->getParam('group')){
                    $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                    $collection->addFieldToFilter('store_id', array('in' => $storeIds));
                } elseif (!$collection->isLive()) {
                    $collection->addFieldToFilter('store_id',
                        array('eq' => Mage::app()->getStore(Mage_Core_Model_Store::ADMIN_CODE)->getId())
                    );
                }

                $collection->load();

                $totals = $collection->getFirstItem();
                
                $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();
                $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);
                $revenue = $this->renderCurrency($totals->getRevenue(), $currency_code, $rate);
                $tax = $this->renderCurrency($totals->getTax(), $currency_code, $rate);
                $shipping = $this->renderCurrency($totals->getShipping(), $currency_code, $rate);
                
                $return_value['revenue'] = $revenue;
                $return_value['tax'] = $tax;
                $return_value['shipping'] = $shipping;
                $return_value['quantity'] = ($totals->getQuantity()) ? ($totals->getQuantity()*1) : 0;
                
                ///////////////////////////// Summary /////////////////////////////
                
                //$all_series = $this->getRowsData(array('quantity'), false, $period);
                $all_series = $this->getRowsData(array($type), false, $period);
                
                
                /*$axisMaps = array(
                    'x' => 'range',
                    'y' => 'quantity'
                );*/
                $axisMaps = array(
                    'x' => 'range',
                    'y' => $type
                );
                $axisLabels = array();
                
                foreach ($axisMaps as $axis => $attr){
                    //$this->setAxisLabels($axis, $this->getRowsData($attr, true));
                    $axisLabels[$axis] = $this->getRowsData($attr, true, $period);
                }
                
                /*echo '<pre>';
                print_r($axisLabels);
                die;*/
                
                //$period = '24h';
                $timezoneLocal = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
                
                
                if ($request->getParam('store')) {
                    if ($request->getParam('store') != 0){
                        
                    }
                } 
                
                list ($dateStart, $dateEnd) = Mage::getResourceModel('reports/order_collection')
                    ->getDateRange($period, '', '', true);
                
                $dateStart->setTimezone($timezoneLocal);
                $dateEnd->setTimezone($timezoneLocal);

                $dates = array();
                $dates_mk = array();
                $datas = array();
                
                
                $return_label = "";
                $return_min_tick_size = "";
                $return_time_format = "";

                while($dateStart->compare($dateEnd) < 0){
                    switch ($period) {
                        case '24h':
                            $d = $dateStart->toString('yyyy-MM-dd HH:00');
                            $d_mk = Varien_Date::toTimestamp($d);//$dateStart->get(Zend_Date::TIMESTAMP);
                            $dateStart->addHour(1);
                            $return_label = $this->__('Sales: Last 24 Hours');
                            $return_min_tick_size = "hour";
                            $return_time_format = "%H:%M";
                            break;
                        case '7d':
                            $d = $dateStart->toString('yyyy-MM-dd');
                            $d_mk = Varien_Date::toTimestamp($d);//$dateStart->get(Zend_Date::TIMESTAMP);
                            $dateStart->addDay(1);
                            $return_label = $this->__('Sales: Last 7 days');
                            $return_min_tick_size = "day";
                            $return_time_format = "%d/%m/%y";
                            break;
                        case '1m':
                            $d = $dateStart->toString('yyyy-MM-dd');
                            $d_mk = Varien_Date::toTimestamp($d);//$dateStart->get(Zend_Date::TIMESTAMP);
                            $dateStart->addDay(1);
                            $return_label = $this->__('Sales: Current month');
                            $return_min_tick_size = "day";
                            $return_time_format = "%d/%m/%y";
                            break;
                        case '1y':
                            $d = $dateStart->toString('yyyy-MM');
                            $d_mk = Varien_Date::toTimestamp($d);//$dateStart->get(Zend_Date::TIMESTAMP);
                            $dateStart->addMonth(1);
                            $return_label = $this->__('Sales: YTD');
                            $return_min_tick_size = "month";
                            $return_time_format = "%d/%m/%y";
                            break;
                        case '2y':
                            $d = $dateStart->toString('yyyy-MM');
                            $d_mk = Varien_Date::toTimestamp($d);//$dateStart->get(Zend_Date::TIMESTAMP);
                            $dateStart->addMonth(1);
                            $return_label = $this->__('Sales: 2YTD');
                            $return_min_tick_size = "month";
                            $return_time_format = "%d/%m/%y";
                            break;
                    }
                    
                    foreach ($all_series as $index=>$serie) {                        
                        if (in_array($d, $axisLabels['x'])) {
                            $datas[$index][] = (float)array_shift($all_series[$index]);
                        } else {
                            $datas[$index][] = 0;
                        }
                    }
                    $dates[] = $d;
                    $dates_mk[] = $d_mk;
                }
                
                $dates_arr = array();
                foreach ($dates_mk as $key => $date_mk){
                    $dates_arr[] = array($date_mk, $datas[$type][$key]);
                }
                
                $return_value['mode'] = 'time';
                //$return_value['time_format'] = '%d/%m/%y';
                $return_value['time_format'] = $return_time_format;
                $return_value['min_tick_size'] = $return_min_tick_size;
                $return_value['label'] = $return_label;
                
                $return_value['data'] = $dates_arr;
                
                $return_value['state'] = 'OK';
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        echo $_GET['callback'] . '(' . json_encode($return_value) . ');';
        die;
    }
    
    protected function getRowsData($attributes = array('quantity'), $single = false, $period = '24h')
    {   
        $request = Mage::app()->getRequest();
        $isFilter = $request->getParam('store') || $request->getParam('website') || $request->getParam('group');
        
        $collection = Mage::getResourceSingleton('reports/order_collection')->prepareSummary($period, 0, 0, $isFilter);
        if ($isFilter){
            $collection->addAttributeToFilter('store_id', $request->getParam('store'));
        }
        
        $items = $collection->getItems();
        
        $options = array();
        foreach ($items as $item){
            if ($single) {
                $options[] = max(0, $item->getData($attributes));
            } else {
                foreach ((array)$attributes as $attr){
                    $options[$attr][] = max(0, $item->getData($attr));
                }
            }
        }
        return $options;
    }
    
    
    public function testAction(){
        $return['module_installed'] = 1;
        
        echo $_GET['callback'] . '(' . json_encode($return) . ');';
        die;
    }
    
    protected function renderCurrency($data, $currency_code, $rate)
    {
        if (!$currency_code) {
            return $data;
        }

        $data = floatval($data) * $rate; 
        $data = sprintf("%f", $data);
        $data = Mage::app()->getLocale()->currency($currency_code)->toCurrency($data);
        return $data;
    }
    
    public function newestcustomerAction() {
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                // get all most viewed product
                //data:['Date', 'User', 'Tweet'],
                //cols: ['15%', '35%', '40%']
                $return_value['data'] = array($this->__('Customer Name'), $this->__('Number of Orders'), $this->__('Average Order Amount'), $this->__('Total Order Amount'));
                $return_value['cols'] = array('40%', '20%', '20%', '20%');
                
                
                $collection = Mage::getResourceModel('reports/customer_collection')
                    ->addCustomerName();
                
                $storeFilter = 0;
                
                
                $store_currency = Mage::app()->getStore()->getId();
                
                if ($request->getParam('store')) {
                    $collection->addAttributeToFilter('store_id', $request->getParam('store'));
                    $store_currency = $request->getParam('store');
                    $storeFilter = 1;
                } else if ($request->getParam('website')){
                    $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                    $collection->addAttributeToFilter('store_id', array('in' => $storeIds));
                } else if ($request->getParam('group')){
                    $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                    $collection->addAttributeToFilter('store_id', array('in' => $storeIds));
                }

                $collection->addOrdersStatistics($storeFilter)
                    ->orderByCustomerRegistration();
                
                $collection->setPageSize(10);
                
                
                
                $return_value['external_call'] = 'customer';
                $return_value['external_elements'] = array();
                
                $return_value['content'] = array();
                
                $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();
                $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);
                foreach($collection as $customer) {
                    //'Customer Name', 'Number of Orders', 'Average Order Amount', 'Total Order Amount'
                    $customer_name = $customer->getData('name');
                    $nb_order = $customer->getData('orders_count');
                    
                    //$currency_code = Mage::app()->getStore((int)$this->getParam('store'))->getBaseCurrencyCode();
                    
                    $average_amount = $this->renderCurrency($customer->getData('orders_avg_amount'), $currency_code, $rate);
                    $total_amount = $this->renderCurrency($customer->getData('orders_sum_amount'), $currency_code, $rate);
                    
                    $return_value['content'][] = array($customer_name, $nb_order, $average_amount, $total_amount);
                    $return_value['external_elements'][] = $customer->getId();
                    
                }
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    public function last5searchesAction(){
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                
                $collection = Mage::getModel('catalogsearch/query')
                        ->getResourceCollection();
                $collection->setRecentQueryFilter();

                if($request->getParam('store') || $request->getParam('website') || $request->getParam('group')) {
                    if ($request->getParam('store')) {
                        $collection->addFieldToFilter('store_id', $request->getParam('store'));
                    } else if ($request->getParam('website')){
                        $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                        $collection->addFieldToFilter('store_id', array('in' => $storeIds));
                    } else if ($request->getParam('group')){
                        $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                        $collection->addFieldToFilter('store_id', array('in' => $storeIds));
                    }
                } 
                $collection->setPageSize(5);
                
                $return_value['data'] = array($this->__('Search Term'), $this->__('Results'), $this->__('Number of Uses'));
                $return_value['cols'] = array('50%', '25%', '25%');
                
                $return_value['content'] = array();
                foreach($collection as $search) {
                    
                    $query_text = $search->getData('query_text');
                    $num_results = $search->getData('num_results');
                    $popularity = $search->getData('popularity');
                    
                    
                    $return_value['content'][] = array($query_text, $num_results, $popularity);
                    
                }
                
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    public function top5searchesAction(){
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                
                $collection = Mage::getModel('catalogsearch/query')
                        ->getResourceCollection();
                //$collection->setRecentQueryFilter();
                
                $storeIds = '';
                if($request->getParam('store') || $request->getParam('website') || $request->getParam('group')) {
                    if ($request->getParam('store')) {
                        $storeIds = $request->getParam('store');
                        $collection->addFieldToFilter('store_id', $request->getParam('store'));
                    } else if ($request->getParam('website')){
                        $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                        //$collection->addFieldToFilter('store_id', array('in' => $storeIds));
                    } else if ($request->getParam('group')){
                        $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                        //$collection->addFieldToFilter('store_id', array('in' => $storeIds));
                    } 
                } 
                $collection->setPopularQueryFilter($storeIds);
                $collection->setPageSize(5);
                
                $return_value['data'] = array($this->__('Search Term'), $this->__('Results'), $this->__('Number of Uses'));
                $return_value['cols'] = array('50%', '25%', '25%');
                
                $return_value['content'] = array();
                foreach($collection as $search) {
                    
                    $name = $search->getData('name');
                    $num_results = $search->getData('num_results');
                    $popularity = $search->getData('popularity');
                    
                    
                    $return_value['content'][] = array($name, $num_results, $popularity);
                    
                }
                
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    public function orderdetailsAction() {
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                $order_id = $request->getParam('id');
                $order_details = Mage::getModel('sales/order')->load($order_id);
                
                $return_value['content'] = "";
                
                if ($order_details->getId()){
                    $content = $this->__("Order: %s (%s)", $order_details->getIncrementId(), $order_details->getStatus());
                    $content .= "<br />".$this->__("Nb of items ordered: %s", count($order_details->getAllItems()));
                    
                    $currency_code = Mage::app()->getStore($order_details->getStoreId())->getBaseCurrencyCode();                    
                    $rate = 1;
                    $shipping = $this->renderCurrency($order_details->getShippingAmount(), $currency_code, $rate);
                    $tax = $this->renderCurrency($order_details->getTaxAmount(), $currency_code, $rate);
                    $grand = $this->renderCurrency($order_details->getGrandTotal(), $currency_code, $rate);
                    $discount = $this->renderCurrency($order_details->getDiscountAmount(), $currency_code, $rate);
                    
                    $content .= "<br />".$this->__("Discount: %s", $discount);
                    $content .= "<br />".$this->__("Shipping: %s", $shipping);
                    $content .= "<br />".$this->__("Total (tax %s): %s", $tax, $grand);
                    
                    $return_value['content'] = $content;
                }
                
                $return_value['state'] = 'OK';
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        echo $_GET['callback'] . '(' . json_encode($return_value) . ');';
        die;
    }
    
    public function customerdetailsAction() {
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                $customer_id = $request->getParam('id');
                $customer_details = Mage::getModel('customer/customer')->load($customer_id);
                
                $return_value['content'] = "";
                
                if ($customer_details->getId()){
                    $content = $this->__("Name: %s", $customer_details->getName());
                    $content .= "<br />".$this->__("Email: %s", $customer_details->getEmail());
                    
                    
                    $group = Mage::getModel('customer/group')->load($customer_details->getGroupId());
                    
                    $content .= "<br />".$this->__("Customer Group: %s", $group->getCode());
                    
                    $return_value['content'] = $content;
                }
                
                $return_value['state'] = 'OK';
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        echo $_GET['callback'] . '(' . json_encode($return_value) . ');';
        die;
    }
    
    public function last5ordersAction(){
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                
                $collection = Mage::getResourceModel('reports/order_collection')
                    ->addItemCountExpr()
                    ->joinCustomerName('customer')
                    ->orderByCreatedAt();
                
                $store_currency = Mage::app()->getStore()->getId();

                if($request->getParam('store') || $request->getParam('website') || $request->getParam('group')) {
                    if ($request->getParam('store')) {
                        $collection->addAttributeToFilter('store_id', $request->getParam('store'));
                        $store_currency = $request->getParam('store');
                    } else if ($request->getParam('website')){
                        $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                        $collection->addAttributeToFilter('store_id', array('in' => $storeIds));
                    } else if ($request->getParam('group')){
                        $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                        $collection->addAttributeToFilter('store_id', array('in' => $storeIds));
                    }
                    $collection->addRevenueToSelect();
                } else {
                    $collection->addRevenueToSelect(true);
                }
                $collection->setPageSize(5);
                
                $return_value['data'] = array($this->__('Customer'), $this->__('Items'), $this->__('Grand Total'));
                $return_value['cols'] = array('50%', '25%', '25%');
                
                $return_value['external_call'] = 'order';
                $return_value['external_elements'] = array();
                
                $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();                    
                $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);
                
                $return_value['content'] = array();
                foreach($collection as $order) {
                    $customer = $order->getData('customer');                    
                    $items_count = $order->getData('items_count');
                    
                    
                    $revenue = $this->renderCurrency($order->getRevenue(), $currency_code, $rate);
                    
                    $return_value['content'][] = array($customer, $items_count, $revenue);
                    $return_value['external_elements'][] = $order->getId();
                    
                }
                
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    public function mostviewedAction() {
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                // get all most viewed product
                //data:['Date', 'User', 'Tweet'],
                //cols: ['15%', '35%', '40%']
                $return_value['data'] = array($this->__('Product Name'), $this->__('Price'), $this->__('Number of Views'));
                $return_value['cols'] = array('50%', '25%', '25%');
                
                $store_currency = Mage::app()->getStore()->getId();
                
                if ($request->getParam('store')){
                    $storeId = (int)$request->getParam('store');
                    $store_currency = $request->getParam('store');
                } else {
                    $storeIds = Mage::app()->getWebsite(1)->getStoreIds();
                    $storeId = array_pop($storeIds);
                }
                
                $collection = Mage::getResourceModel('reports/product_collection')
                    ->addAttributeToSelect('*')
                    ->addViewsCount()
                    ->setStoreId($storeId)
                    ->addStoreFilter($storeId);
                
                $collection->setPageSize(10);
                $return_value['content'] = array();
                foreach($collection as $product) {
                    
                    //Mage::log($product->getData()
                    $views = $product->getData('views');
                    //$price = $product->getPrice();
                    
                    $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();
                    
                    
                    $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);
                    $price = $this->renderCurrency($product->getPrice(), $currency_code, $rate);
                    
                    $name = $product->getName();
                    $return_value['content'][] = array($name, $price, $views);
                    
                }
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    
    public function bestsellersAction() {
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                $return_value['data'] = array($this->__('Product Name'), $this->__('Price'), $this->__('Quantity Ordered'));
                $return_value['cols'] = array('50%', '25%', '25%');
                
                $collection = Mage::getResourceModel('sales/report_bestsellers_collection')
                    ->setModel('catalog/product');
                
                $storeId = '';
                
                $store_currency = Mage::app()->getStore()->getId();
                if ($request->getParam('website')) {
                    $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                    $storeId = array_pop($storeIds);
                } else if ($request->getParam('group')) {
                    $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                    $storeId = array_pop($storeIds);
                } else if ($request->getParam('store')) {
                    $storeId = (int)$request->getParam('store');
                    $store_currency = $request->getParam('store');
                }
                
                $collection->addStoreFilter($storeId);
                
                $collection->setPageSize(10);
                
                $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();
                $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);
                
                $return_value['content'] = array();
                foreach($collection as $order) {
                    $product_name = $order->getData('product_name');
                    $product_price = $this->renderCurrency($order->getData('product_price'), $currency_code, $rate);
                    $qty_ordered = $order->getData('qty_ordered');
                    $return_value['content'][] = array($product_name, $product_price, $qty_ordered);
                }
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    
    public function mostcustomerAction() {
        $return_value = array();
        
        $request = Mage::app()->getRequest();
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                // get all most viewed product
                //data:['Date', 'User', 'Tweet'],
                //cols: ['15%', '35%', '40%']
                $return_value['data'] = array($this->__('Customer Name'), $this->__('Number of Orders'), $this->__('Average Order Amount'), $this->__('Total Order Amount'));
                $return_value['cols'] = array('40%', '20%', '20%', '20%');
                
                $collection = Mage::getResourceModel('reports/order_collection');
                
                $collection
                    ->groupByCustomer()
                    ->addOrdersCount()
                    ->joinCustomerName();
                
                $collection->setPageSize(10);
                
                $storeFilter = 0;
                
                
                $store_currency = Mage::app()->getStore()->getId();
                if ($request->getParam('store')) {
                    $collection->addAttributeToFilter('store_id', $request->getParam('store'));
                    $store_currency = $request->getParam('store');
                    $storeFilter = 1;
                } else if ($request->getParam('website')){
                    $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
                    $collection->addAttributeToFilter('store_id', array('in' => $storeIds));
                } else if ($request->getParam('group')){
                    $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
                    $collection->addAttributeToFilter('store_id', array('in' => $storeIds));
                }
                
                $collection->addSumAvgTotals($storeFilter)
                    ->orderByTotalAmount();
                
                
                $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();
                $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);
                
                $return_value['content'] = array();
                foreach($collection as $customer) {
                    //'Customer Name', 'Number of Orders', 'Average Order Amount', 'Total Order Amount'
                    $customer_name = $customer->getData('name');
                    $nb_order = $customer->getData('orders_count');
                    
                    $average_amount = $this->renderCurrency($customer->getData('orders_avg_amount'), $currency_code, $rate);
                    $total_amount = $this->renderCurrency($customer->getData('orders_sum_amount'), $currency_code, $rate);
                    
                    $return_value['content'][] = array($customer_name, $nb_order, $average_amount, $total_amount);
                    
                }
                $return_value['state'] = 'OK';
                
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        
        return $return_value;
    }
    
    public function tablestatsAction() {
        //mostviewed
        //newestcustomer
        $return_value = array();
        $return_value['state'] = 'KO';
        $request = Mage::app()->getRequest();
        if ($type = $request->getParam('type')){
            if ($type == 'newestcustomer'){
                $return_value = $this->newestcustomerAction(); 
            } else if ($type == 'mostcustomer') {
                $return_value = $this->mostcustomerAction(); 
            } else if ($type == 'lastfiveorders') {
                $return_value = $this->last5ordersAction();
            } else if ($type == 'lastfivesearches'){
                $return_value = $this->last5searchesAction();
            } else if ($type == 'topfivesearches'){
                $return_value = $this->top5searchesAction();
            } else if ($type == 'bestsellers'){
                $return_value = $this->bestsellersAction();
            } else {
                $return_value = $this->mostviewedAction();
            }
        }
        
        if ($return_value['state'] == 'OK'){
            $dashboard_main = $this->dashboardMain();
            $return_value['lifetimesale'] = $dashboard_main['lifetimesale'];
            $return_value['averageorders'] = $dashboard_main['averageorders'];
        }
        
        echo $_GET['callback'] . '(' . json_encode($return_value) . ');';
        die;
    }
    
    public function getstoresAction() {
        $return_value = array();
        $request = Mage::app()->getRequest();
        $return_value['data'] = array();
        
        //$return_value['data'][] = array('label' => $this->__('All Store Views'), 'value' => 0);
        if (($username = $request->getParam('username')) && ($password = $request->getParam('password'))) {
            if ($this->loginUser($username, $password)){
                $allStores = Mage::app()->getStores();
                foreach ($allStores as $_eachStoreId => $val) 
                {
                    $_storeCode = Mage::app()->getStore($_eachStoreId)->getCode();
                    $_storeName = Mage::app()->getStore($_eachStoreId)->getName();
                    $_storeId = Mage::app()->getStore($_eachStoreId)->getId();
                    
                    
                    $return_value['data'][] = array('code' => $_storeCode, 'label' => $_storeName, 'value' => $_storeId);
                }
                $return_value['state'] = 'OK';
            } else {
                $return_value['state'] = 'KO';
            }
        } else {
            $return_value['state'] = 'ERROR-URI';
        }
        echo $_GET['callback'] . '(' . json_encode($return_value) . ');';
        die;
    }
    
    
    protected function dashboardMain() {
        
        $request = Mage::app()->getRequest();
        
        $isFilter = $request->getParam('store') || $request->getParam('website') || $request->getParam('group');

        $collection = Mage::getResourceModel('reports/order_collection')
            ->calculateSales($isFilter);

        $store_currency = Mage::app()->getStore()->getId();        
        if ($request->getParam('store')) {
            $collection->addFieldToFilter('store_id', $request->getParam('store'));
            $store_currency = $request->getParam('store');
        } else if ($request->getParam('website')){
            $storeIds = Mage::app()->getWebsite($request->getParam('website'))->getStoreIds();
            $collection->addFieldToFilter('store_id', array('in' => $storeIds));
        } else if ($request->getParam('group')){
            $storeIds = Mage::app()->getGroup($request->getParam('group'))->getStoreIds();
            $collection->addFieldToFilter('store_id', array('in' => $storeIds));
        }

        $collection->load();
        $sales = $collection->getFirstItem();
        
        $currency_code = Mage::app()->getStore($store_currency)->getBaseCurrencyCode();
        $rate = Mage::app()->getStore()->getBaseCurrency()->getRate($currency_code);

        $lifetimesale = $this->renderCurrency($sales->getLifetime(), $currency_code, $rate);
        $averageorders = $this->renderCurrency($sales->getAverage(), $currency_code, $rate);
        
        return array("lifetimesale" => $lifetimesale, "averageorders" => $averageorders);
    }
    
}