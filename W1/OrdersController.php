<?php
namespace AdminPanel\Controller;

use AdminPanel\Controller\AppController;
use AdminPanel\Model\Entity\TransactionType;
use Cake\Core\Configure;
use Cake\I18n\Time;

/**
 * Withdrawals Controller
 * @property \AdminPanel\Model\Table\OrdersTable $Orders
 * @property \AdminPanel\Model\Table\CardsTable $Cards
 * @property \AdminPanel\Model\Table\OrderDetailsTable $OrderDetails
 * @property \AdminPanel\Model\Table\GenerationsTable $Generations
 * @property \AdminPanel\Model\Table\ProductStockMutationTransactionsTable $ProductStockMutationTransactions
 * @property \AdminPanel\Model\Table\ProductsTable $Products
 * @property \AdminPanel\Model\Table\TransactionsTable $Transactions
 * @property \AdminPanel\Model\Table\CashPointsTable $CashPoints
 * @property \AdminPanel\Model\Table\NetworksTable $Networks
 * @property \AdminPanel\Model\Table\CustomersTable $Customers
 * @property \AdminPanel\Model\Table\MaterialTypesTable $MaterialTypes
 * @property \AdminPanel\Model\Table\OrderTypesTable $OrderTypes
 * @property \AdminPanel\Model\Table\OrderStatusesTable $OrderStatuses
 * @property \AdminPanel\Model\Table\OrderProcessesTable $OrderProcesses
 * @property \AdminPanel\Model\Table\OrderSettingsTable $OrderSettings
 * @property \AdminPanel\Model\Table\OrderPrintingsTable $OrderPrintings
 * @property \AdminPanel\Model\Table\PrintersTable $Printers
 * @property \AdminPanel\Model\Table\FormPrintsTable $FormPrints
 * @property \AdminPanel\Model\Table\OrderPressesTable $OrderPresses
 * @property \AdminPanel\Model\Table\PressMachinesTable $PressMachines
 * @property \AdminPanel\Model\Table\FormPressesTable $FormPresses
 * @property \AdminPanel\Model\Table\OrderSizesTable $OrderSizes
 * @property \AdminPanel\Model\Table\OrderTypeProductsTable $OrderTypeProducts
 * @property \AdminPanel\Model\Table\OrderAdditionalPricesTable $OrderAdditionalPrices
 * @property \AdminPanel\Model\Table\MaterialMachineSettingsTable $MaterialMachineSettings
 * @property \AdminPanel\Model\Table\OrderRejectsTable $OrderRejects
 * @property \AdminPanel\Model\Table\OrderReprintsTable $OrderReprints
 * @property \AdminPanel\Model\Table\NotificationsTable $Notifications
 * @property \AdminPanel\Controller\Component\LevelDeterminantComponent $LevelDeterminant
 */
class OrdersController extends AppController
{

    public function initialize()
    {

        parent::initialize();
        $this->loadModel('AdminPanel.Customers');
        $this->loadModel('AdminPanel.Orders');
        $this->loadModel('AdminPanel.OrderDetails');
        $this->loadModel('AdminPanel.Cards');
        $this->loadModel('AdminPanel.Products');
        $this->loadModel('AdminPanel.Transactions');
        $this->loadModel('AdminPanel.Networks');
        $this->loadModel('AdminPanel.CashPoints');
        $this->loadModel('AdminPanel.Generations');
        $this->loadModel('AdminPanel.MaterialTypes');
        $this->loadModel('AdminPanel.OrderTypes');
        $this->loadModel('AdminPanel.OrderStatuses');
        $this->loadModel('AdminPanel.OrderProcesses');
        $this->loadModel('AdminPanel.OrderSettings');
        $this->loadModel('AdminPanel.OrderPrintings');
        $this->loadModel('AdminPanel.Printers');
        $this->loadModel('AdminPanel.FormPrints');
        $this->loadModel('AdminPanel.OrderPresses');
        $this->loadModel('AdminPanel.PressMachines');
        $this->loadModel('AdminPanel.FormPresses');
        $this->loadModel('AdminPanel.OrderSizes');
        $this->loadModel('AdminPanel.OrderTypeProducts');
        $this->loadModel('AdminPanel.OrderAdditionalPrices');
        $this->loadModel('AdminPanel.MaterialMachineSettings');
        $this->loadModel('AdminPanel.OrderRejects');
        $this->loadModel('AdminPanel.OrderReprints');
        $this->loadModel('AdminPanel.Notifications');
        $this->loadComponent('AdminPanel.LevelDeterminant');
    }

    protected function generateRandomString($length = 10) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function print($invoice = null){
        $data = $this->Orders->find()
            ->contain([
            'OrderStatuses',
            'OrderConfirmations' => [
                'Images',
                'CustomerBanks' => [
                    'Banks'
                ]
            ],
            'Customers',
            'Provinces',
            'Cities',
            'Subdistricts',
            'OrderDetails' => [
                'Products'
            ],
        ])->where(['Orders.invoice' => $invoice])->first();

        $this->set(compact('data'));
    }

    public function updateAwb(){

        if ($this->request->is('ajax')) {
            $id = $this->request->getData('id');
            $awb = $this->request->getData('awb');
            $order =  $this->Orders->get($id);
            $order->awb = $awb;
            $this->Orders->save($order);

            $this->Flash->success(__('The order has been update.'));
            $result = ['ok'];
            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }
    }

    public function process(){

        if ($this->request->is('ajax')) {
            $this->viewBuilder()->setLayout('ajax');
            $status = $this->request->getData('status');
            $ids = $this->request->getData('ids');
            $awb = $this->request->getData('awb');


            switch ($status) {
                case '3': //success

//                    if(empty($awb)){
//                        $this->Flash->error(__('The order could not be process. Please, enter Airway Bill number.'));
//                        continue;
//                    }

                    foreach($ids as $vals){

                        $order =  $this->Orders->get($vals);

                        if($order->get('order_status_id') != 2){
                            $this->Flash->error(__('The order has been success before, cannot do double process'));
                            continue;
                        }

                        $stockType = $order->stock_type;

                        $success = true;
                        if($success){

                            /**
                             * @var \AdminPanel\Model\Entity\OrderDetail[] $orderDetails
                             */

                            $orderDetails = $this->OrderDetails->find()
                                ->where([
                                    'order_id' => $order->get('id'),
                                ])
                                ->all()->toArray();

                            $idSerials = [];
                            foreach($orderDetails as $orderDetail) {

                                /**
                                 * @var \AdminPanel\Model\Entity\Product $product
                                 */
                                $product = $this->Products->get($orderDetail->product_id);

                                /* bonus pribadi */

                                $description = 'Bonus Pribadi Produk : '.$product->name.', Qty : '.$orderDetail->qty;
                                $amount = $product->bonus_pribadi * $orderDetail->qty;
//                                $this->Transactions->create(
//                                    TransactionType::REWARDMEMBER,
//                                    $order->customer_id,
//                                    $amount,
//                                    $description
//                                );

                                $cashPoint = $this->CashPoints->newEntity([
                                    'product_id' => $product->id,
                                    'customer_id' => $order->customer_id,
                                    'from_customer_id' => $order->customer_id,
                                    'description' => $description,
                                    'cash_point' => $amount,
                                    'confirm_date' => (Time::now())->format('Y-m-d')
                                ]);

                                if($this->CashPoints->save($cashPoint)){

                                    $generasi = $this->Generations->find()
                                        ->where([
                                            'customer_id' => $order->customer_id,
                                            'level <= ' => 3
                                        ])
                                        ->orderAsc('level');


                                    /**
                                     * @var \AdminPanel\Model\Entity\Generation[] $generasi
                                     */
                                    /* bonus level */
                                    foreach ($generasi as $gen){
                                        $description = 'Bonus Strata '.$gen->level. ' Produk : '.$product->name.', Qty : '.$orderDetail->qty;
                                        $amount = $product->{'bonus_strata_'.$gen->level} * $orderDetail->qty;
//                                        $this->Transactions->create(
//                                            TransactionType::REWARDMEMBER,
//                                            $gen->refferal_id,
//                                            $amount,
//                                            $description
//                                        );

                                        $cashPoint = $this->CashPoints->newEntity([
                                            'product_id' => $product->id,
                                            'customer_id' => $gen->refferal_id,
                                            'from_customer_id' => $order->customer_id,
                                            'description' => $description,
                                            'cash_point' => $amount,
                                            'confirm_date' => (Time::now())->format('Y-m-d')
                                        ]);
                                        $this->CashPoints->save($cashPoint);

                                    }
                                }

                            }

                            $order->order_status_id = $status;
                            $order->confirm_date = date('Y-m-d');
                            $this->Orders->save($order);


                            $this->Flash->success(__('The order has been update.'));
                        }else{
                            $this->Flash->error(__('Order total not balace with stock. please add new stock'));
                        }


                    }

                    break;

                default:
                    /* update status */
                    foreach($ids as $vals){
                        $order =  $this->Orders->get($vals);

                        $orderDetails = $this->OrderDetails->find()
                            ->where([
                                'order_id' => $order->get('id'),
                            ])
                            ->all()->toArray();
                        /* pemotongan stock produk ke stokis */
                        foreach($orderDetails as $orderDetail){
                            $this->ProductStockMutationTransactions->create(
                                1,
                                $orderDetail->product_id,
                                $order->supplier_id,
                                $orderDetail->qty,
                                strtolower('penambahan'),
                                'Penambahan kembali stock produk order canceled by admin '.$order->invoice
                            );
                        }
                        $order->order_status_id = $status;
                        $this->Orders->save($order);
                    }
                    $this->Flash->success(__('The order has been update.'));
            }


            $result = ['ok'];
            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }
    }

    public function index()
    {

        if ($this->request->is('ajax')) {
            $this->viewBuilder()->setLayout('ajax');

            $pagination = $this->request->getData('pagination');
            $sort = $this->request->getData('sort');
            $query = $this->request->getData('query');
            $status = $this->request->getData('status');

            $type = [
                'pending' => 'Pending',
                'proses' => 'Proses',
                'selesai' => 'Selesai',
                'ditolak' => 'Ditolak'
            ];
            $status = $type[$status];

            /** custom default query : select, where, contain, etc. **/
            $data = $this->Orders->find('all')
                ->select();
            $data->contain([
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes'
            ]);
            //])->where(['order_status_id' => 0]);

            if ($query && is_array($query)) {
                if (isset($query['generalSearch'])) {
                    $search = $query['generalSearch'];
                    unset($query['generalSearch']);
                    /**
                    custom field for general search
                    ex : 'Users.email LIKE' => '%' . $search .'%'
                     **/
                    $data->where(['OR' => [
                        'Customers.full_name LIKE' => '%' . $search .'%',
                        'Orders.no_po LIKE' => '%' . $search .'%',
                    ]]);
                }
                $data->where($query);
            }
//            if (isset($query['order_status_id'])) {
//                $order_status_id = $query['order_status_id'];
//                $data->where(['Orders.order_status_id' => $order_status_id]);
//            }

            if (isset($status)) {
                $data->where(['OrderStatuses.name' => $status]);
            }

            if (isset($sort['field']) && isset($sort['sort'])) {
                $data->order([$sort['field'] => $sort['sort']]);
            }

            if (isset($pagination['perpage']) && is_numeric($pagination['perpage'])) {
                $data->limit($pagination['perpage']);
            }
            if (isset($pagination['page']) && is_numeric($pagination['page'])) {
                $data->page($pagination['page']);
            }

            $total = $data->count();

//            $webroot = $this->getRequest()->getAttribute('webroot');
//            $data = $data->map(function (\AdminPanel\Model\Entity\Order $row) use ($webroot) {
//				if($row->order_confirmation && $row->order_confirmation->image) {
//					$path = explode(DS, $row->order_confirmation->image->dir);
//					//unset($path[0]);
//                    array_shift($path);
//                    if ($webroot != '/') {
//                        array_unshift(
//                            $path,
//                            trim($webroot, '/')
//                        );
//                    }
//
//
//					$path = implode('/',$path);
//					$row->order_confirmation->image->dir = $path;
//				}
//                return $row;
//            });

            $result = [];
            $result['data'] = $data->toArray();


            $result['meta'] = array_merge((array) $pagination, (array) $sort);
            $result['meta']['total'] = $total;


            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }

        $statusTypes = $this->Orders->OrderStatuses->find('list')->toArray();

        $this->set(compact('statusTypes'));
    }

    public function getPrice()
    {
        $this->getRequest()->allowMethod('post');

        if ($product_id = $this->getRequest()->getData('id')) {
            $product = $this->OrderTypeProducts->find()
                ->select(['price'])
                ->where(['id' => $product_id])
                ->first();

//            $product = $this->OrderTypeProducts->find('', [
//                'fields' => ['price'],
//                'conditions' => ['id' => $product_id]
//            ])->toArray();

            return $this->getResponse()->withType('application/json')
                ->withStringBody(json_encode($product));
        }


    }

    public function getType()
    {
        $this->getRequest()->allowMethod('post');

        if ($order_type_id = $this->getRequest()->getData('id')) {
            $products = $this->OrderTypeProducts->find('list', [
                'keyField' => 'id',
                'valueField' => function (\AdminPanel\Model\Entity\OrderTypeProduct $orderTypeProduct) {
                    return $orderTypeProduct->get('name');
                }
            ])
                ->where([
                    'order_type_id' => $order_type_id
                ])
                ->toArray();

            return $this->getResponse()->withType('application/json')
                ->withStringBody(json_encode($products));
        }


    }

    public function add()
    {
        $order = $this->Orders->newEntity();
        if ($this->request->is('post')) {
            $validator = $this->Orders->getValidator();
            $validator->notBlank('quantity')
                ->numeric('quantity')
                ->greaterThan('quantity', '0', 'Kuantitas harus lebih besar dari 0')
                ->notBlank('customer_id', 'Kolom pelanggan tidak boleh kosong')
                ->notBlank('material_type_id', 'Kolom jenis bahan tidak boleh kosong')
                ->notBlank('order_type_id', 'Kolom jenis pesanan tidak boleh kosong');

            $size = $this->request->getData('size');
            $qty = $this->request->getData('quantity');

            $validator_size = $this->OrderSizes->getValidator();
            $validator_size->notBlank('name');
            $validator_size->notBlank('qty')
                ->add('qty', 'counter', [
                'rule' => function($values) use($size,$qty){
                    $totalsize = 0;
                    foreach($size as $val){
                        $totalsize += $val['qty'];
                    }

                    return $qty >= $totalsize;
                },'message' => 'total size melebihi kuantitas'
            ]);
            $validator->addNestedMany('size', $validator_size);
            $order = $this->Orders->patchEntity($order, $this->request->getData(), ['associated'=>['Customers', 'OrderStatuses', 'OrderProcesses', 'OrderTypes', 'MaterialTypes']]);
            $order->date_po = date('Y-m-d', strtotime($this->request->getData(['date_po']), true));
            $order->dateline = date('Y-m-d', strtotime($this->request->getData(['dateline']), true));
            $order->tipe = $this->request->getData(['tiperadio']);
            $order->order_status_id = 2;
            $order->order_process_id = 3;
            $errors = $validator->validate($this->request->getData());

            if ($this->Orders->save($order)) {
                $order_id = $order->id;
                if(!empty($this->request->getData('size'))){
                    foreach ($this->request->getData('size') as $size){
                        $order_size = $this->OrderSizes->newEntity();
                        $order_size = $this->OrderSizes->patchEntity($order_size, $this->request->getData());
                        $order_size->order_id = $order_id;
                        $order_size->name = $size['name'];
                        $order_size->qty = $size['qty'];
                        $this->OrderSizes->save($order_size);
                    }
                }

                //Biaya Tambahan
                /*
                if(!empty($this->request->getData('order_additional_price'))){
                    foreach ($this->request->getData('order_additional_price') as $addprice){
                        $order_additional_price = $this->OrderAdditionalPrices->newEntity();
                        $order_additional_price = $this->OrderAdditionalPrices->patchEntity($order_additional_price, $this->request->getData());
                        $order_additional_price->order_id = $order_id;
                        $order_additional_price->description = $addprice['description'];
                        $order_additional_price->price = $addprice['price'];
                        $this->OrderAdditionalPrices->save($order_additional_price);
                    }
                }
                */
                //End

                $notifications = $this->Notifications->newEntity();
                $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                $notifications->controller = 'orders';
                $notifications->action = 'detail';
                $notifications->parameter = $order_id;
                $notifications->message = 'Order Baru Dengan Nomor PO '.$order->no_po;
                $notifications->icon = 'flaticon-file';
                $notifications->status = 0;
                $notifications->created = date('Y-m-d H:i:s');
                if ($this->Notifications->save($notifications)){
                    $notifications2 = $this->Notifications->newEntity();
                    $notifications2 = $this->Notifications->patchEntity($notifications2, $this->request->getData());
                    $notifications2->controller = 'orders';
                    $notifications2->action = 'detail';
                    $notifications2->parameter = $order_id;
                    $notifications2->message = 'Order Dengan Nomor PO '.$order->no_po.' Telah Sampai Menuju Proses Setting.';
                    $notifications2->icon = 'flaticon-cogwheel';
                    $notifications2->status = 0;
                    $notifications2->created = date('Y-m-d H:i:s');
                    if ($this->Notifications->save($notifications)) {
                        $this->Flash->success(__('Pesanan baru berhasil disimpan.'));
                        //$this->addStocks($product->id);
                        return $this->redirect(['action' => 'index']);
                    }
                }
            }
            $this->set('size', $size);
        }
        $check_order = $this->Orders->find()
            ->order(['id' => 'DESC'])
            ->first();

        if($check_order){
            $before_id = $check_order->id + 1;
            $nomor_po = 'Po.00000' . $before_id;
            $nomor_inv = 'INV.00000' . $before_id;
        }else{
            $nomor_po = 'Po.000001';
            $nomor_inv = 'INV.000001';
        }
        $listCustomers = $this->Customers->find('list', [
            'keyField' => 'id',
            'valueField' => 'full_name'
        ], ['limit' => 200]);
        $materialTypes = $this->MaterialTypes->find('list', ['limit' => 200]);
        $orderTypes = $this->OrderTypes->find('list', ['limit' => 200]);
        $orderProcesses = $this->OrderProcesses->find('list', ['limit' => 200]);
        $orderProducts = $this->OrderTypeProducts->find('list', ['limit' => 200]);
        $this->set(compact('order', 'listCustomers','materialTypes', 'orderTypes', 'orderProcesses', 'nomor_po', 'nomor_inv', 'orderProducts'));
    }

    public function detail($id = null)
    {
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderSettings',
                'OrderPrintings',
                'OrderPresses',
                'OrderSizes',
                'OrderAdditionalPrices',
                'OrderTypeProducts'
            ]
        ]);
        if(!empty($order->order_printings)){
            $form_prints = $this->FormPrints->find('all')
                ->where(['FormPrints.order_printing_id' => $order->order_printings[0]->id])->toArray();
        }else{
            $form_prints = '';
        }

        if(!empty($order->order_presses)){
            $form_presses = $this->FormPresses->find('all')
                ->where(['FormPresses.order_press_id' => $order->order_presses[0]->id])->toArray();
        }else{
            $form_presses = '';
        }


        $order_reprint = [];
        if($order->order_sizes){
            foreach($order->order_sizes as $k => $order_size){
                $reprint = $this->OrderReprints->find()
                    ->where(['OrderReprints.order_size_id' => $order_size->id])
                    ->first();
                if($reprint){
                    $order_reprint[$k]['id'] = $reprint->id;
                    $order_reprint[$k]['order_size_id'] = $reprint->order_size_id;
                    $order_reprint[$k]['size_name'] = $reprint->size_name;
                    $order_reprint[$k]['qty'] = $reprint->qty;
                    $order_reprint[$k]['status_reprint'] = $reprint->status_reprint;
                }
            }
        }

        $order_reject = [];
        if($order->order_sizes){
            foreach($order->order_sizes as $k => $order_size){
                $reject = $this->OrderRejects->find()
                    ->where(['OrderRejects.order_size_id' => $order_size->id])
                    ->first();
                if($reject){
                    $order_reject[$k]['id'] = $reject->id;
                    $order_reject[$k]['order_size_id'] = $reject->order_size_id;
                    $order_reject[$k]['size_name'] = $reject->size_name;
                    $order_reject[$k]['qty'] = $reject->qty;
                }
            }
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            //debug($this->request->getData());
            //exit;
            if(!empty($this->request->getData('order_size'))){
                foreach ($this->request->getData('order_size') as $ordersize){
                    if(!empty($ordersize['price']) && !empty($ordersize['total'])){
                        $order_size = $this->OrderSizes->get($ordersize['id']);
                        $order_size = $this->OrderSizes->patchEntity($order_size, $this->request->getData());
                        $order_size->price = $ordersize['price'];
                        $order_size->total = $ordersize['total'];
                        $this->OrderSizes->save($order_size);
                    }
                }
            }
            $total_add_price = 0;
            $set_price = 0;
            $final_price = 0;
            if(!empty($this->request->getData('order_additional_price'))){
                if(!empty($this->request->getData('order_additional_price')[0]['description']) && !empty($this->request->getData('order_additional_price')[0]['price'])){
                    foreach ($this->request->getData('order_additional_price') as $addprice){
                        $order_additional_price = $this->OrderAdditionalPrices->newEntity();
                        $order_additional_price = $this->OrderAdditionalPrices->patchEntity($order_additional_price, $this->request->getData());
                        $order_additional_price->order_id = $order->id;
                        $order_additional_price->description = $addprice['description'];
                        $order_additional_price->price = $addprice['price'];
                        $this->OrderAdditionalPrices->save($order_additional_price);

                        $total_add_price += $addprice['price'];
                    }
                }
            }

            if($this->request->getData(['order_status_id']) == 3){
                if(!empty($this->request->getData(['estimated_price']))){
                    $total_price_db = 0;
                    if($this->request->getData(['order_price'])){
                        foreach($this->request->getData(['order_price']) as $order_add){
                            if($order_add['status'] == 1){
                                if(is_array($order_add) && isset($order_add['id'])){
                                    $order_add_price = $this->OrderAdditionalPrices->get($order_add['id']);
                                    $this->OrderAdditionalPrices->delete($order_add_price);
                                }
                            }else{
                                $total_price_db += $order_add['price'];
                            }
                        }
                    }
                    $final_price = ($total_add_price + $this->request->getData('estimated_price') + $total_price_db) - $this->request->getData('discount');
                    $set_price = 1;

                    $order = $this->Orders->patchEntity($order, $this->request->getData(), ['associated'=>['Customers', 'OrderStatuses', 'OrderProcesses', 'OrderTypes', 'MaterialTypes']]);
                    $order->final_price = $final_price;
                    $order->set_price = $set_price;
                    $order->dateline = date('Y-m-d', strtotime($this->request->getData(['dateline']), true));
                    if ($this->Orders->save($order)) {

                        $this->Flash->success(__('Pesanan berhasil di update.'));

                        return $this->redirect(['action' => 'index']);
                    }
                    $this->Flash->error(__('Pesanan gagal disimpan, silahkan coba lagi.'));
                }else{
                    $this->Flash->error(__('Pesanan gagal disimpan, silahkan isi kolom harga terlebih dahulu.'));
                }
            }else{
                if(!empty($this->request->getData(['estimated_price']))){
                    $set_price = 1;
                }else{
                    $set_price = 0;
                }
                $total_price_db = 0;
                if($this->request->getData(['order_price'])){
                    foreach($this->request->getData(['order_price']) as $order_add){
                        if($order_add['status'] == 1){
                            if(is_array($order_add) && isset($order_add['id'])){
                                $order_add_price = $this->OrderAdditionalPrices->get($order_add['id']);
                                $this->OrderAdditionalPrices->delete($order_add_price);
                            }
                        }else{
                            $total_price_db += $order_add['price'];
                        }
                    }
                }

                $final_price = ($total_add_price + $this->request->getData('estimated_price') + $total_price_db) - $this->request->getData('discount');
                $order = $this->Orders->patchEntity($order, $this->request->getData(), ['associated'=>['Customers', 'OrderStatuses', 'OrderProcesses', 'OrderTypes', 'MaterialTypes']]);
                $order->final_price = $final_price;
                $order->set_price = $set_price;
                $order->dateline = date('Y-m-d', strtotime($this->request->getData(['dateline']), true));
                if ($this->Orders->save($order)) {

                    $this->Flash->success(__('Pesanan berhasil di update.'));

                    return $this->redirect(['action' => 'index']);
                }
                $this->Flash->error(__('Pesanan gagal disimpan, silahkan coba lagi.'));
            }

            /*
            $order = $this->Orders->patchEntity($order, $this->request->getData(), ['associated'=>['Customers', 'OrderStatuses', 'OrderProcesses', 'OrderTypes', 'MaterialTypes']]);
            $order->final_price = $final_price;
            $order->set_price = $set_price;
            $order->dateline = date('Y-m-d', strtotime($this->request->getData(['dateline']), true));
            if ($this->Orders->save($order)) {

                $this->Flash->success(__('Pesanan berhasil di update.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('Pesanan gagal disimpan, silahkan coba lagi.'));
            */
        }

        $listCustomers = $this->Customers->find('all')->toArray();
        $materialTypes = $this->MaterialTypes->find('all')->toArray();
        $orderStatuses = $this->OrderStatuses->find('list', [
            'conditions' => ['OrderStatuses.id IN' => [2, 3]],
            'limit' => 200]
        );
        $orderProcesses = $this->OrderProcesses->find('list', [
            'conditions' => ['OrderProcesses.id !=' => 1],
            'limit' => 200
        ]);
        $press_machines = $this->PressMachines->find('all');
        $printers = $this->Printers->find('all');
        $this->set(compact('order', 'press_machines', 'form_prints', 'printers', 'form_presses', 'orderStatuses', 'orderProcesses', 'order_reprint', 'order_reject', 'listCustomers', 'materialTypes'));
    }


    public function edit($id = null)
    {
        $order_type = $this->OrderTypes->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $order_type = $this->OrderTypes->patchEntity($order_type, $this->request->getData());
            if ($this->OrderTypes->save($order_type)) {
                $this->Flash->success(__('The order type has been saved.'));

                return $this->redirect(['action' => 'orderType']);
            }
            $this->Flash->error(__('The order type could not be saved. Please, try again.'));
        }
        $this->set(compact('order_type'));
    }


    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $order_type = $this->OrderTypes->get($id);
        try {
            if ($this->OrderTypes->delete($order_type)) {
                $this->Flash->success(__('The order type has been deleted.'));
            } else {
                $this->Flash->error(__('The order type could not be deleted. Please, try again.'));
            }
        } catch (Exception $e) {
            $this->Flash->error(__('The order type could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'orderType']);
    }

    public function setting()
    {

        if ($this->request->is('ajax')) {
            $this->viewBuilder()->setLayout('ajax');

            $pagination = $this->request->getData('pagination');
            $sort = $this->request->getData('sort');
            $query = $this->request->getData('query');
            $status = $this->request->getData('status');


            /** custom default query : select, where, contain, etc. **/
            $data = $this->Orders->find('all')
                ->select();
            $data->contain([
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes'
            ])->where(['order_process_id' => 3]);

            if ($query && is_array($query)) {
                if (isset($query['generalSearch'])) {
                    $search = $query['generalSearch'];
                    unset($query['generalSearch']);
                    /**
                    custom field for general search
                    ex : 'Users.email LIKE' => '%' . $search .'%'
                     **/
                    $data->where(['OR' => [
                        'Customers.full_name LIKE' => '%' . $search .'%',
                        'Orders.no_po LIKE' => '%' . $search .'%',
                    ]]);
                }
                $data->where($query);
            }

            if (isset($status)) {
                $data->where(['OrderStatuses.name' => $status]);
            }

            if (isset($sort['field']) && isset($sort['sort'])) {
                $data->order([$sort['field'] => $sort['sort']]);
            }

            if (isset($pagination['perpage']) && is_numeric($pagination['perpage'])) {
                $data->limit($pagination['perpage']);
            }
            if (isset($pagination['page']) && is_numeric($pagination['page'])) {
                $data->page($pagination['page']);
            }

            $total = $data->count();

            $result = [];
            $result['data'] = $data->toArray();


            $result['meta'] = array_merge((array) $pagination, (array) $sort);
            $result['meta']['total'] = $total;


            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }

//        $statusTypes = $this->Orders->OrderStatuses->find('list')->toArray();
//
//        $this->set(compact('statusTypes'));
    }

    public function updateSetting($id = null)
    {
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderTypeProducts'
            ]
        ]);
        $order_setting = $this->OrderSettings->newEntity();
        if ($this->request->is(['patch', 'post', 'put'])) {
//            $validator = $this->OrderSettings->getValidator('default');
//            $validator->add('image_design', 'mime', [
//                'rule' => function($value) {
//                    $mime = mime_content_type($value['tmp_name']);
//                    return in_array($mime, [
//                        'image/png',
//                        'image/jpeg',
//                        'image/gif',
//                    ]);
//                },
//                'message' => 'Not valid file type'
//            ]);
//            $validator->add('file_design', 'mime', [
//                'rule' => function($value) {
//                    $mime = mime_content_type($value['tmp_name']);
//                    /*
//                    return in_array($mime, [
//                        'image/png',
//                        'image/jpeg',
//                        'image/gif',
//                    ]);
//                    */
//                },
//                'message' => 'Not valid file type'
//            ]);
            $order_setting = $this->OrderSettings->patchEntity($order_setting, $this->request->getData(), ['associated'=>['Orders']]);
            $order_setting->order_id = $order->id;
            $order_setting->operator = $this->request->getData(['operator_setting']);
            if ($this->OrderSettings->save($order_setting)) {
                $order = $this->Orders->patchEntity($order, $this->request->getData());
                $order->order_process_id = 4;
                if($this->Orders->save($order)) {

                    $notifications = $this->Notifications->newEntity();
                    $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                    $notifications->controller = 'orders';
                    $notifications->action = 'detail';
                    $notifications->parameter = $order->id;
                    $notifications->message = 'Order Dengan Nomor PO '.$order->no_po.' Dalam Proses Printing';
                    $notifications->icon = 'flaticon2-print';
                    $notifications->status = 0;
                    $notifications->created = date('Y-m-d H:i:s');
                    if ($this->Notifications->save($notifications)){
                        $this->Flash->success(__('Pesanan berhasil di update.'));

                        return $this->redirect(['action' => 'setting']);
                    }
                }
            }
            $this->Flash->error(__('Pesanan gagal disimpan, silahkan coba lagi.'));
        }

        $orderProcesses = $this->OrderProcesses->find('list', [
            'conditions' => ['OrderProcesses.id' => 4],
            'limit' => 200
        ]);
        $this->set(compact('order', 'order_setting', 'orderProcesses'));
    }

    public function printing()
    {

        if ($this->request->is('ajax')) {
            $this->viewBuilder()->setLayout('ajax');

            $pagination = $this->request->getData('pagination');
            $sort = $this->request->getData('sort');
            $query = $this->request->getData('query');
            $status = $this->request->getData('status');


            /** custom default query : select, where, contain, etc. **/
            $data = $this->Orders->find('all')
                ->select();
            $data->contain([
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes'
            ])->where(['order_process_id IN' => [4,6]]);

            if ($query && is_array($query)) {
                if (isset($query['generalSearch'])) {
                    $search = $query['generalSearch'];
                    unset($query['generalSearch']);
                    /**
                    custom field for general search
                    ex : 'Users.email LIKE' => '%' . $search .'%'
                     **/
                    $data->where(['OR' => [
                        'Customers.full_name LIKE' => '%' . $search .'%',
                        'Orders.no_po LIKE' => '%' . $search .'%',
                    ]]);
                }
                $data->where($query);
            }

            if (isset($status)) {
                $data->where(['OrderStatuses.name' => $status]);
            }

            if (isset($sort['field']) && isset($sort['sort'])) {
                $data->order([$sort['field'] => $sort['sort']]);
            }

            if (isset($pagination['perpage']) && is_numeric($pagination['perpage'])) {
                $data->limit($pagination['perpage']);
            }
            if (isset($pagination['page']) && is_numeric($pagination['page'])) {
                $data->page($pagination['page']);
            }

            $total = $data->count();

            $result = [];
            $result['data'] = $data->toArray();


            $result['meta'] = array_merge((array) $pagination, (array) $sort);
            $result['meta']['total'] = $total;


            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }

//        $statusTypes = $this->Orders->OrderStatuses->find('list')->toArray();
//
//        $this->set(compact('statusTypes'));
    }

    public function updatePrinting($id = null)
    {
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderSettings',
                'OrderPrintings',
                'OrderSizes',
                'OrderTypeProducts'
            ]
        ]);
        if($order->order_process_id == 4){
            $order_printing = $this->OrderPrintings->newEntity();
            $form_prints = '';
            $order_reprint = '';
        }else if($order->order_process_id == 6){
            $order_printing = $this->OrderPrintings->find()
                ->where(['OrderPrintings.id' => $order->order_printings[0]->id])
                ->select();
            $form_prints = $this->FormPrints->find('all')
                ->where(['FormPrints.order_printing_id' => $order->order_printings[0]->id])->toArray();
            $order_reprint = [];
            if($order->order_sizes){
                foreach($order->order_sizes as $k => $order_size){
                    $reprint = $this->OrderReprints->find()
                        ->where(['OrderReprints.order_size_id' => $order_size->id])
                        ->first();
                    if($reprint){
                        $order_reprint[$k]['id'] = $reprint->id;
                        $order_reprint[$k]['order_size_id'] = $reprint->order_size_id;
                        $order_reprint[$k]['size_name'] = $reprint->size_name;
                        $order_reprint[$k]['qty'] = $reprint->qty;
                        $order_reprint[$k]['status_reprint'] = $reprint->status_reprint;
                    }
                }
            }
//            $order_reprint = [];
//            $reprint = $this->OrderReprints->find('all')
//                ->select()->toArray();
//            foreach ($reprint as $k => $item) {
//                $order_reprint[$k]['id'] = $item->id;
//                $order_reprint[$k]['order_size_id'] = $item->order_size_id;
//                $order_reprint[$k]['size_name'] = $item->size_name;
//                $order_reprint[$k]['qty'] = $item->qty;
//                $order_reprint[$k]['status_reprint'] = $item->status_reprint;
//            }
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            if($order->order_process_id == 4){
                $order_printing = $this->OrderPrintings->patchEntity($order_printing, $this->request->getData(), ['associated'=>['Orders', 'FormPrints']]);
                $order_printing->order_id = $order->id;
                $order_printing->operator = $this->request->getData(['operator_printing']);
                $order_printing->tipe = $this->request->getData(['tiperadio']);
                $order_printing->uk_total = $this->request->getData(['uk_total']) ? $this->request->getData(['uk_total']) : '' ;
                //$order_printing->roll_total = $this->request->getData(['roll_total']) ? $this->request->getData(['roll_total']) : '' ;
                //$order_printing->price = $this->request->getData(['tiperadio']) == 'persize' ? $this->request->getData(['price_persize']) : $this->request->getData(['price_roll']);
                if ($this->OrderPrintings->save($order_printing)) {
                    $order_id = $order_printing->id;
                    $query = $this->FormPrints->query();

                    foreach($this->request->getData(['printer']) as $form){
                        if($form['form_print']['printer_id'])
                            $form_print = $this->FormPrints->newEntity();
                        $form_print = $this->FormPrints->patchEntity($form_print, $this->request->getData(), ['associated'=>['OrderPrintings', 'Printers']]);
                        $form_print->order_printing_id = $order_id;
                        $form_print->printer_id = isset($form['form_print']['printer_id']) ? $form['form_print']['printer_id'] : 'off';
                        $form_print->unit_print = isset($form['form_print']['unit_print']) ? $form['form_print']['unit_print'] : 'off';
                        $form_print->cek_file = isset($form['form_print']['cek_file']) ? $form['form_print']['cek_file'] : 'off';
                        $form_print->cek_kertas = isset($form['form_print']['cek_kertas']) ? $form['form_print']['cek_kertas'] : 'off';
                        $form_print->cek_setting_bahan = isset($form['form_print']['cek_setting_bahan']) ? $form['form_print']['cek_setting_bahan'] : 'off';
                        $form_print->cek_nozzle = isset($form['form_print']['cek_nozzle']) ? $form['form_print']['cek_nozzle'] : 'off';
                        $form_print->cek_tinta = isset($form['form_print']['cek_tinta']) ? $form['form_print']['cek_tinta'] : 'off';
                        $form_print->cek_banding = isset($form['form_print']['cek_banding']) ? $form['form_print']['cek_banding'] : 'off';
                        $form_print->cek_kertas_rollan = isset($form['form_print']['cek_kertas_rollan']) ? $form['form_print']['cek_kertas_rollan'] : 'off';
                        $form_print->qc = isset($form['form_print']['qc']) ? $form['form_print']['qc'] : 'off';

                        $query
                            ->insert(['order_printing_id', 'printer_id', 'unit_print', 'cek_file', 'cek_kertas', 'cek_setting_bahan', 'cek_nozzle', 'cek_tinta', 'cek_banding', 'cek_kertas_rollan', 'qc'])


                            ->values([
                                'order_printing_id' => $order_id,
                                'printer_id' => $form_print->printer_id,
                                'unit_print' => $form_print->unit_print,
                                'cek_file' => $form_print->cek_file,
                                'cek_kertas' => $form_print->cek_kertas,
                                'cek_setting_bahan' => $form_print->cek_setting_bahan,
                                'cek_nozzle' => $form_print->cek_nozzle,
                                'cek_tinta' => $form_print->cek_tinta,
                                'cek_banding' => $form_print->cek_banding,
                                'cek_kertas_rollan' => $form_print->cek_kertas_rollan,
                                'qc' => $form_print->qc
                            ]);
                    }
                    if($query->execute()){
                        if(!empty($this->request->getData('order_size'))){
                            foreach ($this->request->getData('order_size') as $ordersize){
                                $order_size = $this->OrderSizes->get($ordersize['id']);
                                $order_size = $this->OrderSizes->patchEntity($order_size, $this->request->getData());
                                $order_size->uk = $ordersize['uk'];
                                $order_size->total_uk = $ordersize['total_uk'];
                                $this->OrderSizes->save($order_size);
                            }
                        }
                        $order = $this->Orders->patchEntity($order, $this->request->getData());
                        $order->order_process_id = 5;
                        if($this->Orders->save($order)) {

                            $notifications = $this->Notifications->newEntity();
                            $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                            $notifications->controller = 'orders';
                            $notifications->action = 'detail';
                            $notifications->parameter = $order->id;
                            $notifications->message = 'Order Dengan Nomor PO '.$order->no_po.' Dalam Proses Press';
                            $notifications->icon = 'flaticon-tabs';
                            $notifications->status = 0;
                            $notifications->created = date('Y-m-d H:i:s');
                            if ($this->Notifications->save($notifications)){
                                $this->Flash->success(__('Pesanan berhasil di update.'));

                                return $this->redirect(['action' => 'printing']);
                            }
                        }
                    }
                }
            }else if($order->order_process_id == 6){
                if(!empty($this->request->getData('order_print'))){
                    foreach ($this->request->getData('order_print') as $orderreprint){
                        if(is_array($orderreprint) && isset($orderreprint['id'])){
                            $order_reprint = $this->OrderReprints->get($orderreprint['id']);
                            $order_reprint = $this->OrderReprints->patchEntity($order_reprint, $orderreprint);
                            $order_reprint->status_reprint = $orderreprint['status_reprint'];
                            $this->OrderReprints->save($order_reprint);
                        }
                    }
                }
                $order = $this->Orders->patchEntity($order, $this->request->getData());
                $order->order_process_id = 5;
                if($this->Orders->save($order)) {

                    $notifications = $this->Notifications->newEntity();
                    $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                    $notifications->controller = 'orders';
                    $notifications->action = 'detail';
                    $notifications->parameter = $order->id;
                    $notifications->message = 'Order Dengan Nomor PO '.$order->no_po.' Dalam Proses Press';
                    $notifications->icon = 'flaticon-tabs';
                    $notifications->status = 0;
                    $notifications->created = date('Y-m-d H:i:s');
                    if ($this->Notifications->save($notifications)){
                        $this->Flash->success(__('Pesanan berhasil di update.'));

                        return $this->redirect(['action' => 'printing']);
                    }
                }
            }

            $this->Flash->error(__('Pesanan gagal disimpan, silahkan coba lagi.'));
        }
        $orderProcesses = $this->OrderProcesses->find('list', [
            'conditions' => ['OrderProcesses.id' => 5],
            'limit' => 200
        ]);
        $printers = $this->Printers->find('all');
        $this->set(compact('order', 'order_printing', 'orderProcesses', 'printers', 'form_prints', 'order_reprint'));
    }

    public function press()
    {

        if ($this->request->is('ajax')) {
            $this->viewBuilder()->setLayout('ajax');

            $pagination = $this->request->getData('pagination');
            $sort = $this->request->getData('sort');
            $query = $this->request->getData('query');
            $status = $this->request->getData('status');


            /** custom default query : select, where, contain, etc. **/
            $data = $this->Orders->find('all')
                ->select();
            $data->contain([
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes'
            ])->where(['order_process_id' => 5]);

            if ($query && is_array($query)) {
                if (isset($query['generalSearch'])) {
                    $search = $query['generalSearch'];
                    unset($query['generalSearch']);
                    /**
                    custom field for general search
                    ex : 'Users.email LIKE' => '%' . $search .'%'
                     **/
                    $data->where(['OR' => [
                        'Customers.full_name LIKE' => '%' . $search .'%',
                        'Orders.no_po LIKE' => '%' . $search .'%',
                    ]]);
                }
                $data->where($query);
            }

            if (isset($status)) {
                $data->where(['OrderStatuses.name' => $status]);
            }

            if (isset($sort['field']) && isset($sort['sort'])) {
                $data->order([$sort['field'] => $sort['sort']]);
            }

            if (isset($pagination['perpage']) && is_numeric($pagination['perpage'])) {
                $data->limit($pagination['perpage']);
            }
            if (isset($pagination['page']) && is_numeric($pagination['page'])) {
                $data->page($pagination['page']);
            }

            $total = $data->count();

            $result = [];
            $result['data'] = $data->toArray();


            $result['meta'] = array_merge((array) $pagination, (array) $sort);
            $result['meta']['total'] = $total;


            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }

//        $statusTypes = $this->Orders->OrderStatuses->find('list')->toArray();
//
//        $this->set(compact('statusTypes'));
    }

    public function updatePress($id = null)
    {
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderSettings',
                'OrderPrintings',
                'OrderSizes',
                'OrderPresses',
                'OrderTypeProducts'
            ]
        ]);
        if(!empty($order->order_presses)){
            $order_press = $this->OrderPresses->find()
                ->where(['OrderPresses.id' => $order->order_presses[0]->id])
                ->select()->toArray();
            $form_presses = $this->FormPresses->find('all')
                ->where(['FormPresses.order_press_id' => $order->order_presses[0]->id])->toArray();

            $order_reprint = [];
            if($order->order_sizes){
                foreach($order->order_sizes as $k => $order_size){
                    $reprint = $this->OrderReprints->find()
                        ->where(['OrderReprints.order_size_id' => $order_size->id])
                        ->first();
                    if($reprint){
                        $order_reprint[$k]['id'] = $reprint->id;
                        $order_reprint[$k]['order_size_id'] = $reprint->order_size_id;
                        $order_reprint[$k]['size_name'] = $reprint->size_name;
                        $order_reprint[$k]['qty'] = $reprint->qty;
                        $order_reprint[$k]['status_reprint'] = $reprint->status_reprint;
                    }
                }
            }

//            $order_reprint = [];
//            $reprint = $this->OrderReprints->find('all')
//                ->select()->toArray();
//            foreach ($reprint as $k => $item) {
//                $order_reprint[$k]['id'] = $item->id;
//                $order_reprint[$k]['order_size_id'] = $item->order_size_id;
//                $order_reprint[$k]['size_name'] = $item->size_name;
//                $order_reprint[$k]['qty'] = $item->qty;
//                $order_reprint[$k]['status_reprint'] = $item->status_reprint;
//            }
        }else{
            $form_presses = '';
            $order_reprint = '';
            $order_press = $this->OrderPresses->newEntity();
        }

        $form_prints = $this->FormPrints->find('all')
            ->where(['FormPrints.order_printing_id' => $order->order_printings[0]->id])->toArray();

        if ($this->request->is(['patch', 'post', 'put'])) {
            if(empty($form_presses)){
                $order_press = $this->OrderPresses->patchEntity($order_press, $this->request->getData(), ['associated'=>['Orders', 'FormPresses']]);
                $order_press->order_id = $order->id;
                //$order_press->description = $this->request->getData(['description_press']);
                $order_press->operator = $this->request->getData(['operator_press']);
                if ($this->OrderPresses->save($order_press)) {
                    $order_id = $order_press->id;
                    $query = $this->FormPresses->query();

                    foreach($this->request->getData(['press']) as $form){
                        $form_press = $this->FormPresses->newEntity();
                        $form_press = $this->FormPresses->patchEntity($form_press, $this->request->getData(), ['associated'=>['OrderPresses', 'PressMachines']]);
                        $form_press->order_press_id = $order_id;
                        $form_press->press_machine_id = isset($form['form_press']['press_machine_id']) ? $form['form_press']['press_machine_id'] : 'off';
                        $form_press->unit_press = isset($form['form_press']['unit_press']) ? $form['form_press']['unit_press'] : 'off';
                        $form_press->cek_bahan = isset($form['form_press']['cek_bahan']) ? $form['form_press']['cek_bahan'] : 'off';
                        $form_press->cek_hasil_print = isset($form['form_press']['cek_hasil_print']) ? $form['form_press']['cek_hasil_print'] : 'off';
                        $form_press->cek_banding = isset($form['form_press']['cek_banding']) ? $form['form_press']['cek_banding'] : 'off';
                        $form_press->qc = isset($form['form_press']['qc']) ? $form['form_press']['qc'] : 'off';

                        $query
                            ->insert(['order_press_id', 'press_machine_id', 'unit_press', 'cek_bahan', 'cek_hasil_print', 'cek_banding', 'qc'])
                            ->values([
                                'order_press_id' => $order_id,
                                'press_machine_id' => $form_press->press_machine_id,
                                'unit_press' => $form_press->unit_press,
                                'cek_bahan' => $form_press->cek_bahan,
                                'cek_hasil_print' => $form_press->cek_hasil_print,
                                'cek_banding' => $form_press->cek_banding,
                                'qc' => $form_press->qc
                            ]);
                    }
                    if($query->execute()){
                        if($this->request->getData(['reject']) == 'on'){
                            if($order->tipe == 'per_pcs'){
                                foreach ($this->request->getData(['order_reject']) as $reject){
                                    $getSize = $this->OrderSizes->find()
                                        ->where([
                                            'OrderSizes.order_id' => $order->id,
                                            'OrderSizes.name LIKE' => '%'.$reject['size_name_reject'].'%'
                                        ])
                                        ->first();
                                    if(!empty($reject['qty_reject'])){
                                        $order_reject = $this->OrderRejects->newEntity();
                                        $order_reject = $this->OrderRejects->patchEntity($order_reject, $this->request->getData());
                                        $order_reject->order_id = $order->id;
                                        $order_reject->order_size_id = $getSize->id;
                                        $order_reject->size_name = $reject['size_name_reject'];
                                        $order_reject->qty = $reject['qty_reject'];
                                        $this->OrderRejects->save($order_reject);
                                    }
                                }
                                $desc = $this->request->getData(['description_reject']);
                            }else{
                                foreach ($this->request->getData(['order_reject_roll']) as $reject){
                                    $getSize = $this->OrderSizes->find()
                                        ->where([
                                            'OrderSizes.order_id' => $order->id,
                                            'OrderSizes.name LIKE' => '%'.$reject['size_name_reject'].'%'
                                        ])
                                        ->first();
                                    if(!empty($reject['qty_reject'])){
                                        $order_reject = $this->OrderRejects->newEntity();
                                        $order_reject = $this->OrderRejects->patchEntity($order_reject, $this->request->getData());
                                        $order_reject->order_id = $order->id;
                                        $order_reject->order_size_id = $getSize->id;
                                        $order_reject->size_name = $reject['size_name_reject'];
                                        $order_reject->qty = $reject['qty_reject'];
                                        $this->OrderRejects->save($order_reject);
                                    }
                                }
                                $desc = $this->request->getData(['description_reject_roll']);
                            }
                            $order = $this->Orders->patchEntity($order, $this->request->getData());
                            $order->reject = 1;
                            $order->keterangan_reject = $desc;
                            $order->total_bahan_reject = $this->request->getData(['total_reject']);
                            $order->order_process_id = 7;
                            $order->order_status_id = 2;
                            if($this->Orders->save($order)) {

                                $notifications = $this->Notifications->newEntity();
                                $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                                $notifications->controller = 'orders';
                                $notifications->action = 'detail';
                                $notifications->parameter = $order->id;
                                $notifications->message = 'Order Dengan Nomor PO '.$order->no_po.' Telah Selesai.';
                                $notifications->icon = 'flaticon2-checkmark';
                                $notifications->status = 0;
                                $notifications->created = date('Y-m-d H:i:s');
                                if ($this->Notifications->save($notifications)){
                                    $this->Flash->success(__('Pesanan berhasil di update.'));

                                    return $this->redirect(['action' => 'press']);
                                }
                            }
                        }else if($this->request->getData(['reprint']) == 'on'){
                            foreach ($this->request->getData(['order_reprint']) as $reprint){
                                $getSize = $this->OrderSizes->find()
                                    ->where([
                                        'OrderSizes.order_id' => $order->id,
                                        'OrderSizes.name LIKE' => '%'.$reprint['size_name_reprint'].'%'
                                    ])
                                    ->first();
                                if(!empty($reprint['qty_reprint'])){
                                    $order_reprint = $this->OrderReprints->newEntity();
                                    $order_reprint = $this->OrderReprints->patchEntity($order_reprint, $this->request->getData());
                                    $order_reprint->order_size_id = $getSize->id;
                                    $order_reprint->size_name = $reprint['size_name_reprint'];
                                    $order_reprint->qty = $reprint['qty_reprint'];
                                    $this->OrderReprints->save($order_reprint);
                                }
                            }
                            $desc = $this->request->getData(['description_reprint']);
                            $order = $this->Orders->patchEntity($order, $this->request->getData());
                            $order->reprint = 1;
                            $order->keterangan_reprint = $desc;
                            $order->order_process_id = 6;
                            if($this->Orders->save($order)) {

                                $notifications = $this->Notifications->newEntity();
                                $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                                $notifications->controller = 'orders';
                                $notifications->action = 'detail';
                                $notifications->parameter = $order->id;
                                $notifications->message = 'Order Dengan Nomor PO '.$order->no_po.' Dalam Proses Reprint.';
                                $notifications->icon = 'flaticon2-printer';
                                $notifications->status = 0;
                                $notifications->created = date('Y-m-d H:i:s');
                                if ($this->Notifications->save($notifications)){
                                    $this->Flash->success(__('Pesanan berhasil di update menjadi reprint.'));

                                    return $this->redirect(['action' => 'press']);
                                }
                            }
                        }else{
                            $order = $this->Orders->patchEntity($order, $this->request->getData());
                            $order->order_process_id = 7;
                            $order->order_status_id = 2;
                            if($this->Orders->save($order)) {

                                $notifications = $this->Notifications->newEntity();
                                $notifications = $this->Notifications->patchEntity($notifications, $this->request->getData());
                                $notifications->controller = 'orders';
                                $notifications->action = 'detail';
                                $notifications->parameter = $order->id;
                                $notifications->message = 'Order Dengan Nomor PO '.$order->no_po.' Telah Selesai.';
                                $notifications->icon = 'flaticon2-checkmark';
                                $notifications->status = 0;
                                $notifications->created = date('Y-m-d H:i:s');
                                if ($this->Notifications->save($notifications)){
                                    $this->Flash->success(__('Pesanan berhasil di update.'));

                                    return $this->redirect(['action' => 'press']);
                                }
                            }
                        }
                    }
                }
                $this->Flash->error(__('Pesanan gagal disimpan, silahkan coba lagi.'));
            }else{
                $order = $this->Orders->patchEntity($order, $this->request->getData());
                $order->order_process_id = 7;
                $order->order_status_id = 2;
                if($this->Orders->save($order)) {
                    $this->Flash->success(__('Pesanan berhasil di update.'));

                    return $this->redirect(['action' => 'press']);
                }
            }
        }

        $orderProcesses = $this->Orders->OrderProcesses->find('list', [
            'conditions' => ['OrderProcesses.id IN' => [6, 7]],
        ]);
        $order_sizes = $this->OrderSizes->find()
            ->where(['OrderSizes.order_id' => $order->id])
            ->select()->toArray();
        $press_machines = $this->PressMachines->find('all');
        $printers = $this->Printers->find('all');
        $material_machine_setting = $this->MaterialMachineSettings->find()
            ->where(['MaterialMachineSettings.material_type_id' => $order->material_type->id])
            ->first();
        $this->set(compact('order', 'order_press', 'orderProcesses', 'press_machines', 'form_prints', 'printers', 'material_machine_setting', 'order_reprint', 'form_presses', 'order_sizes'));
    }

    public function document()
    {

        if ($this->request->is('ajax')) {
            $this->viewBuilder()->setLayout('ajax');

            $pagination = $this->request->getData('pagination');
            $sort = $this->request->getData('sort');
            $query = $this->request->getData('query');
            $status = $this->request->getData('status');


            /** custom default query : select, where, contain, etc. **/
            $data = $this->Orders->find('all')
                ->select();
            $data->contain([
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes'
            ])->where(['order_process_id' => 7]);

            if ($query && is_array($query)) {
                if (isset($query['generalSearch'])) {
                    $search = $query['generalSearch'];
                    unset($query['generalSearch']);
                    /**
                    custom field for general search
                    ex : 'Users.email LIKE' => '%' . $search .'%'
                     **/
                    $data->where(['OR' => [
                        'Customers.username LIKE' => '%' . $search .'%',
                        'Orders.no_po LIKE' => '%' . $search .'%',
                    ]]);
                }
                $data->where($query);
            }

            if (isset($status)) {
                $data->where(['OrderStatuses.name' => $status]);
            }

            if (isset($sort['field']) && isset($sort['sort'])) {
                $data->order([$sort['field'] => $sort['sort']]);
            }

            if (isset($pagination['perpage']) && is_numeric($pagination['perpage'])) {
                $data->limit($pagination['perpage']);
            }
            if (isset($pagination['page']) && is_numeric($pagination['page'])) {
                $data->page($pagination['page']);
            }

            $total = $data->count();

            $result = [];
            $result['data'] = $data->toArray();


            $result['meta'] = array_merge((array) $pagination, (array) $sort);
            $result['meta']['total'] = $total;


            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }

//        $statusTypes = $this->Orders->OrderStatuses->find('list')->toArray();
//
//        $this->set(compact('statusTypes'));
    }

    public function detailDocument($id = null)
    {
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderSettings',
                'OrderPrintings',
                'OrderPresses'
            ]
        ]);
        $form_prints = $this->FormPrints->find('all')
            ->where(['FormPrints.order_printing_id' => $order->order_printings[0]->id])->toArray();
        $form_presses = $this->FormPresses->find('all')
            ->where(['FormPresses.order_press_id' => $order->order_presses[0]->id])->toArray();

        $press_machines = $this->PressMachines->find('all');
        $printers = $this->Printers->find('all');
        $this->set(compact('order', 'press_machines', 'form_prints', 'printers', 'form_presses'));
    }

    public function suratJalan($id = null)
    {
        $this->viewBuilder()->setLayout('invoice');
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderSettings',
                'OrderPrintings',
                'OrderPresses'
            ]
        ]);
        $form_prints = $this->FormPrints->find('all')
            ->where(['FormPrints.order_printing_id' => $order->order_printings[0]->id])->toArray();
        $form_presses = $this->FormPresses->find('all')
            ->where(['FormPresses.order_press_id' => $order->order_presses[0]->id])->toArray();

        $press_machines = $this->PressMachines->find('all');
        $printers = $this->Printers->find('all');
        $this->set(compact('order', 'press_machines', 'form_prints', 'printers', 'form_presses'));
    }

    public function invoice($id = null)
    {
        $this->viewBuilder()->setLayout('invoice');
        $order = $this->Orders->get($id, [
            'contain' => [
                'Customers',
                'OrderTypes',
                'OrderStatuses',
                'OrderProcesses',
                'MaterialTypes',
                'OrderSettings',
                'OrderPrintings',
                'OrderPresses'
            ]
        ]);
        $form_prints = $this->FormPrints->find('all')
            ->where(['FormPrints.order_printing_id' => $order->order_printings[0]->id])->toArray();
        $form_presses = $this->FormPresses->find('all')
            ->where(['FormPresses.order_press_id' => $order->order_presses[0]->id])->toArray();

        $press_machines = $this->PressMachines->find('all');
        $printers = $this->Printers->find('all');
        $this->set(compact('order', 'press_machines', 'form_prints', 'printers', 'form_presses'));
    }
}
