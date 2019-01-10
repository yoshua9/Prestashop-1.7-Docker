<?php
/**
 * 2011-2014 OBSolutions S.C.P. All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of OBSolutions S.C.P. and its suppliers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to OBSolutions S.C.P.
 * and its suppliers and are protected by trade secret or copyright law.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from OBSolutions S.C.P.
 *
 *  @author    OBSolutions SCP <http://addons.prestashop.com/en/65_obs-solutions>
 *  @copyright 2011-2014 OBSolutions SCP
 *  @license   OBSolutions S.C.P. All Rights Reserved
 *  International Registered Trademark & Property of OBSolutions SCP
 */

define('OBS_FACTUSOL_CL_DIR', dirname(__FILE__).'/../../customers/');
define('OBS_FACTUSOL_ORD_DIR', dirname(__FILE__).'/../../orders/');
define( 'OBS_FACTUSOL_IMP_DIR',	dirname(__FILE__).'/../../import/' );
define( 'OBS_FACTUSOL_CSV_CATEGORIES',	dirname(__FILE__).'/../../csv/FactuSOL_categories.csv' );
define( 'OBS_FACTUSOL_CSV_PRODUCTS',	dirname(__FILE__).'/../../csv/FactuSOL_products.csv' );
define( 'OBS_FACTUSOL_EXPORT_ZIP',	dirname(__FILE__).'/../../export/export_factusol.zip' );
define( 'OBS_FACTUSOL_LOG_DIR',	dirname(__FILE__).'/../../logs/' );

include_once(dirname(__FILE__).'/../../classes/Factusol.php' );

class OBSFactusolproAdminTabController extends ModuleAdminController
{
	public function __construct() {
		
		$this->bootstrap = true;
		
		parent::__construct();
	}
	
	public function postProcess() {
		
		$currentIndex = $this->context->link->getAdminLink('OBSFactusolproAdminTab', true);
		
		if (Tools::isSubmit('submitDeleteImportFiles')){
			
			$fileType = Tools::getValue('factusolFileType');
			
			if($fileType == 'C')
				$path = OBS_FACTUSOL_CL_DIR;
			else
				$path = OBS_FACTUSOL_ORD_DIR;
			
			$files = glob($path.'*'); // get all file names
			foreach($files as $file)
			{ // iterate files
				if(is_file($file) AND !preg_match('/index/', $file))
					unlink($file); // delete file
			}
			
			Tools::redirectAdmin($currentIndex.'&conf=1');
		}
		
		if (Tools::isSubmit('btnCreateXLSfiles')){
			
			Configuration::updateValue('OBS_FACTUSOL_EXPORT_TARIFF_CODE', (int)Tools::getValue('obs_tariff_code', 1));
			Configuration::updateValue('OBS_FACTUSOL_MIN_STOCK', (int)Tools::getValue('obs_min_stock', 2));
			Configuration::updateValue('OBS_FACTUSOL_MAX_STOCK', (int)Tools::getValue('obs_max_stock', 10));
			Configuration::updateValue('OBS_FACTUSOL_WAREHOUSE_CODE', Tools::getValue('obs_warehouse_code', 'GEN'));
			Configuration::updateValue('OBS_FACTUSOL_LANG', (int)Tools::getValue('obs_lang', 1));
			$start_product = (int)Tools::getValue('obs_product_start', 1);
			$end_product = (int)Tools::getValue('obs_product_end', 1000);
			
			Factusol::generateExportFiles($start_product, $end_product);
			Tools::redirectAdmin($currentIndex.'&conf=6');
		}
		
		if (Tools::isSubmit('btnCreateAllOrders')){	  
            $this->createAllOrders();
            Tools::redirectAdmin($currentIndex.'&conf=6');
		}
		
		if (Tools::isSubmit('btnCreateAllCustomers')){		  
            $this->createAllCustomers();
            Tools::redirectAdmin($currentIndex.'&conf=6');
		}
		
		if(Tools::isSubmit('exportSettings')) {
			Configuration::updateValue('OBS_FACTUSOL_SERIE_ID', (int)Tools::getValue('obs_factusol_serie_id', 8));
			Configuration::updateValue('OBS_FIRST_C_FREE_ID', (int)Tools::getValue('first_c_free_id', 0));
			Configuration::updateValue('OBS_FIRST_O_FREE_ID', (int)Tools::getValue('first_o_free_id', 0));
			Configuration::updateValue('OBS_FACTUSOL_SHIPPING', (int)Tools::getValue('obs_factusol_shipping', 0));
			Configuration::updateValue('OBS_FACTUSOL_IVA_1', (int)Tools::getValue('obs_factusol_iva_1', 1));
			Configuration::updateValue('OBS_FACTUSOL_IVA_2', (int)Tools::getValue('obs_factusol_iva_2', 2));
			Configuration::updateValue('OBS_FACTUSOL_IVA_3', (int)Tools::getValue('obs_factusol_iva_3', 3));
			
			//PAYMENT METHODS MODULES
			$paymentModules = Module::getPaymentModules();
			foreach($paymentModules as $payment){
				if(version_compare(_PS_VERSION_,'1.6','<'))
					Configuration::updateValue('OBS_'.Tools::substr(Tools::strtoupper($payment['name']),0,28), (string) Tools::getValue('id_for_'.$payment['name'], ''));
				else 
					Configuration::updateValue('OBS_'.Tools::strtoupper($payment['name']), (string) Tools::getValue('id_for_'.$payment['name'], ''));
			}
			
			Tools::redirectAdmin($currentIndex.'&conf=6');
		}
		else if(Tools::isSubmit('importSettings')) {	
			Configuration::updateValue('OBS_FACTUSOL_IMPORTCSV', (int)Tools::getValue('obs_factusol_importcsv', 0));
			Configuration::updateValue('OBS_FACTUSOL_DISABLE', (int)Tools::getValue('obs_factusol_disable', 0));
			Configuration::updateValue('OBS_FACTUSOL_STOCK', (int)Tools::getValue('obs_factusol_stock', 1));
			Configuration::updateValue('OBS_FACTUSOL_PRICE', (int)Tools::getValue('obs_factusol_price', 1));
			Configuration::updateValue('OBS_FACTUSOL_ORIGEN_CONIVA', (int)Tools::getValue('obs_factusol_origen_coniva', 0));
			Configuration::updateValue('OBS_FACTUSOL_TARIFA', (int)Tools::getValue('obs_factusol_tarifa', '1'));
			Configuration::updateValue('OBS_FACTUSOL_IVA_IMPORT_1', (int)Tools::getValue('obs_factusol_iva_import_1', 1));
			Configuration::updateValue('OBS_FACTUSOL_IVA_IMPORT_2', (int)Tools::getValue('obs_factusol_iva_import_2', 2));
			Configuration::updateValue('OBS_FACTUSOL_IVA_IMPORT_3', (int)Tools::getValue('obs_factusol_iva_import_3', 3));
			
			Tools::redirectAdmin($currentIndex.'&conf=6');
			
		}
	}
	
	public function ajaxProcess(){
		
		if(Tools::getValue('type') == 'categories')
		{
			$destPath = _PS_ADMIN_DIR_."/import";
			if(rename(OBS_FACTUSOL_CSV_CATEGORIES, $destPath."/FactuSOL_categories.csv"))
				echo "OK";
			else
				echo "KO";
		}
		if(Tools::getValue('type') == 'products')
		{
			$destPath = _PS_ADMIN_DIR_."/import";
			if(rename(OBS_FACTUSOL_CSV_PRODUCTS, $destPath."/FactuSOL_products.csv"))
				echo "OK";
			else
				echo "KO";
		}
		
	}
	
	public function createAllOrders(){
		
		
		
		$start_p = Tools::getValue('start_p');
        $end_p = Tools::getValue('end_p');
        $where = '';
        if(!empty($start_p) && !empty($end_p) && (int) $start_p == (int)$end_p)
        	$where = " o.`id_order` = " . (int)$start_p;
        elseif(!empty($start_p) && !empty($end_p) && (int) $start_p < (int)$end_p)
        	$where = " o.`id_order` >= " . (int)$start_p . " AND o.`id_order` <= " . (int)$end_p;
        else
        	return false;

        $query = "SELECT * FROM `"._DB_PREFIX_."orders` AS o WHERE o.`valid` = 1 AND " .$where;

        if($rs = Db::getInstance()->ExecuteS($query)) {
            foreach($rs AS $row) {
                $order = new Order($row['id_order']);
                if(!Validate::isLoadedObject($order))
                    continue;
           		if(!empty($order)){
					Factusol::createOrderFile($order);
					Factusol::createCustomerFile(new Customer($order->id_customer), $order->id_address_invoice);
                }
            }
            return true;
        } else {
			$this->warning[] = $this->l('There were no orders available or have already been sent. Check the history.');
            return false;
        }    
	}
	
	public function createAllCustomers() {
		$start = Tools::getValue('start_c');
        $end = Tools::getValue('end_c');
        $where = '';
        if(!empty($start) && !empty($end) && (int) $start == (int)$end)
        	$where = " c.`id_customer` = " . (int)$start;
        elseif(!empty($start) && !empty($end) && (int) $start < (int)$end)
        	$where = " c.`id_customer` >= " . (int)$start . " AND c.`id_customer` <= " . (int)$end;
        else
        	return false;
        
        $query = "SELECT * FROM `"._DB_PREFIX_."customer` AS c WHERE c.`deleted` = 0 AND ".$where;

        if($rs = Db::getInstance()->ExecuteS($query)) {
            foreach($rs AS $row) {
                $customer = new Customer($row['id_customer']);
                if(!Validate::isLoadedObject($customer))
                    continue;
                if(!empty($customer))
					Factusol::createCustomerFile($customer);                      
            }
            return true;
        } else {
            $this->warning[] = $this->l('There were no orders available or have already been sent. Check the history.');
            return false;
        }    
	}
	

		
	public function initContent()
	{
		
		if($this->ajax) {
		 	self::ajaxProcess();
		} else {
		
			$output = '<script type="text/javascript" src="../modules/obsfactusolpro/views/js/functions.js"></script>';
			
			parent::initContent();
			
			$output .= '
			<style> label{ text-align: left; width: 450px; } .ml5 {margin-left: 5px;} .big{font-size:18px; font-weight: bolder; color: black; float:right;}</style>
			<h2>'.$this->l('Update Data').'</h2>';
			
			if(version_compare(_PS_VERSION_,'1.5','>=')){
				
				$regType = Configuration::get('PS_REGISTRATION_PROCESS_TYPE');
				
				if($regType == '0')
					$output .='
					<p style="clear: both"></p>'.$this->setStartWrapper('../modules/obsfactusolpro/views/img/AdminTracking.gif', $this->l('WARNING')).'
						<p style="color:red; font-weight:bold">'.$this->l('You have configured the customer registration in 2 steps.').'</p>
						<p style="color:red;">'.$this->l('For proper data export of new registered customers is advisable to check in one single step, since otherwise it will have the addresses of their customers.').'</p>
						<p style="color:red;">'.$this->l('To change the settings access to tab Settings-> Customers-> Type registration process and select the Standard.').'</p>
						<div class="clear">&nbsp;</div>
					'.$this->setEndWrapper().'
					<br/>';
				
			}
			
			$output .= $this->setStartWrapper('../modules/obsfactusolpro/views/img/AdminInformation.gif', $this->l('Pending Customers and Orders to export')).'
				<label class="control-label col-lg-4 " style="margin-left:10px">'.$this->l('New orders pending to sync:').'<span class="big">'.Factusol::getPedidosPendientes( ).' </span> <a style="font-size:10px" href="#" onclick="deleteExportFiles(\'P\');"><img src="/img/admin/delete.gif" alt="Vaciar cola" title="Vaciar cola"/></a></label>
				<label class="control-label col-lg-4 " style="margin-left:10px">'.$this->l('New clients pending to sync:').'<span class="big">'.Factusol::getClientesPendientes( ).'</span> <a style="font-size:10px" href="#"  onclick="deleteExportFiles(\'C\');"><img src="/img/admin/delete.gif" alt="Vaciar cola" title="Vaciar cola"/></a></label>
				<div class="clear">&nbsp;</div>
			'.$this->setEndWrapper().'
			<br/>';
			
			$output .= '<form name="deleteExportFilesForm" id="deleteExportFilesForm" action="'.$this->context->link->getAdminLink('OBSFactusolproAdminTab', true).'" method="POST">
			
						<input type="hidden" id="factusolFileType" name="factusolFileType" value=""/>	
						<input type="hidden" name="submitDeleteImportFiles" value="1"/>
			
						</form>';
			
			$output .= $this->setStartWrapper('../modules/obsfactusolpro/views/img/AdminInformation.gif', $this->l('CSV files to import from FactuSOL to Prestashop'));
	
			if(file_exists(OBS_FACTUSOL_CSV_CATEGORIES) OR file_exists(OBS_FACTUSOL_CSV_PRODUCTS))
			{
				$output .= '<form name="importCSV" id="importCSV" action="'.$this->context->link->getAdminLink('AdminImport', true).'" method="POST">
						
							<input type="hidden" name="entity" id="entity" value=""/>
							<input type="hidden" name="file" value=""/>
							<input type="hidden" name="csv" id="csv" value=""/>
							<input type="hidden" name="iso_lang" value="es"/>
							<input type="hidden" name="convert" value="on"/>
							<input type="hidden" name="separator" value=";"/>
							<input type="hidden" name="multiple_value_separator" value=","/>
							<input type="hidden" name="match_ref" value="on"/>
							<input type="hidden" name="submitImportFile" value="1"/>
						
							</form>';
			
				//var_dump(_PS_ADMIN_DIR_);die;
				if(file_exists(OBS_FACTUSOL_CSV_CATEGORIES)){
					
					$lasttime = date("d/m/Y H:i:s", filemtime(OBS_FACTUSOL_CSV_CATEGORIES));
					$output .= '<span style="color:red">Hay nuevo fichero de categorías a importar con fecha <em>'.$lasttime.'</em>&nbsp;&nbsp; <a href="" onclick="return saveFileToImportDir(\'categories\');">Importar categorías</a><br/><br/>';
				}
				
				if(file_exists(OBS_FACTUSOL_CSV_PRODUCTS))
				{
					$lasttime = date("d/m/Y H:i:s", filemtime(OBS_FACTUSOL_CSV_PRODUCTS));
					$output .= '<span style="color:red">Hay nuevo fichero de productos a importar con fecha <em>'.$lasttime.'</em>&nbsp;&nbsp; <a href="" onclick="return saveFileToImportDir(\'products\');">Importar productos</a><br/><br/>';
				}
			}
			else
				$output .= $this->l('No new categories or products to be imported.');
				
			$output .= '	<div class="clear">&nbsp;</div>
			'.$this->setEndWrapper().'
			<br/>';
			
			$output .= $this->getXLSFormContent();
			$output .= '<br/>';
	
			$output .= $this->getOrdersFormContent();
			$output .= '<br/>';
			$output .= $this->getCustomersFormContent();
			
			$output .= '
			<h2>'.$this->l('Configuration').'</h2>
			'.$this->setStartWrapper('../modules/obsfactusolpro/views/img/AdminInformation.gif', $this->l('FTP Folders')).'
	
				<p>'.$this->l('Use this folders to configure Factusol import. Folders must be writable.').'</p>
				'.$this->l('Orders save folder:').' '.(is_writable(OBS_FACTUSOL_ORD_DIR)?'<span style="color:green;">'.$this->l('writable').'</span>':'<span style="color:red;">'.$this->l('Warning: may not be writable').'</span>').'
				<div class="margin-form">
					'.OBS_FACTUSOL_ORD_DIR.'
				</div>
				<br/>
				'.$this->l('Customers save folder:').' '.(is_writable(OBS_FACTUSOL_CL_DIR)?'<span style="color:green;">'.$this->l('writable').'</span>':'<span style="color:red;">'.$this->l('Warning: may not be writable').'</span>').'
				<div class="margin-form">
					'.OBS_FACTUSOL_CL_DIR.'
				</div>
				<br/>
				'.$this->l('Import file save folder:').' '.(is_writable(OBS_FACTUSOL_IMP_DIR)?'<span style="color:green;">'.$this->l('writable').'</span>':'<span style="color:red;">'.$this->l('Warning: may not be writable').'</span>').'
				<div class="margin-form">
					'.OBS_FACTUSOL_IMP_DIR.'
				</div>
				<br/>
				'.$this->l('Logs files save folder:').' '.(is_writable(OBS_FACTUSOL_LOG_DIR)?'<span style="color:green;">'.$this->l('writable').'</span>':'<span style="color:red;">'.$this->l('Warning: may not be writable').'</span>').'
				<div class="margin-form">
					'.OBS_FACTUSOL_LOG_DIR.'
				</div>
			'.$this->setEndWrapper().'
	        <br/>';
			$output .= $this->getExportFormContent();
			$output .= '<br/>';
			$output .= $this->getImportFormContent();
			
			if (version_compare(_PS_VERSION_,'1.6','<')){
	 	 		$output .= $this->boFooter14();
	 	 	}
	 	 	else{
	 	 		$output .= $this->boFooter16();
	 	 	}	
			
			$this->content .= $output;
			$this->context->smarty->assign('content', $this->content);
		}
		
	} 	
	
	private function getXLSFormContent(){
	
		$langs = Language::getLanguages(true);
		
		$fields_form = array(
				'form' => array(
						'legend' => array(
								'title' => $this->l('XLS files to export products from Prestashop to FactuSOL (Zipped):'),
								'icon' => 'icon-wrench'
						),
						'input' => array(
								array(
										'type' => 'text',
										'label' => $this->l('Warehouse code'),
										'hint' => $this->l('Enter Factusol warehouse code where you want the stock register'),
										'size' => 45,
										'name' => 'obs_warehouse_code',
								),
								array(
										'type' => 'text',
										'label' => $this->l('Tariff code'),
										'hint' => $this->l('Enter FactuSOL products tariff code'),
										'size' => 45,
										'name' => 'obs_tariff_code',
								),
								array(
										'type' => 'text',
										'label' => $this->l('Minimum products stock'),
										'hint' => $this->l('Enter minimum products stock for FactuSOL products'),
										'size' => 45,
										'name' => 'obs_min_stock',
								),
								array(
										'type' => 'text',
										'label' => $this->l('Maximum products stock'),
										'hint' => $this->l('Enter maximum products stock for FactuSOL products'),
										'size' => 45,
										'name' => 'obs_max_stock',
								),
								array(
										'type' => 'select',
										'label' => $this->l('Language'),
										'hint' => $this->l('Enter language for product names and descriptions'),
										'name' => 'obs_lang',
										'options' => array(
												'query' => $langs,
												'id' => 'id_lang',
												'name' => 'name')
								),
								array(
										'type' 	=> 'text',
										'label' => $this->l('Product ID from'),
										'hint' 	=> $this->l('Start product exportation'),
										'size'	=> 45,
										'name'	=> 'obs_product_start'	 		
								),
								array(
										'type' 	=> 'text',
										'label' => $this->l('Product ID to'),
										'hint' 	=> $this->l('End product exportation'),
										'size'	=> 45,
										'name'	=> 'obs_product_end'
								),
								array(
										'type' => 'free',
										'label' => $this->l('XLS export files (zipped):'),
										'hint' => $this->l('Download XLS FactuSOL import files (zipped)'),
										'name' => 'obs_xls_files',
								),
									
									
						),
						'submit' => array(
								'title' => $this->l('Create XLS export files'),
						)
				),
		);
	
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$this->fields_form = array();
		$helper->id = '1';
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnCreateXLSfiles';
		$helper->currentIndex = $this->context->link->getAdminLink('OBSFactusolproAdminTab', false);
		$helper->token = Tools::getAdminTokenLite('OBSFactusolproAdminTab');
		$helper->tpl_vars = array(
				'fields_value' => $this->getXLSFilesFieldsValues(),
				'languages' => $this->context->controller->getLanguages(),
				'id_language' => $this->context->language->id
		);
	
		return $helper->generateForm(array($fields_form));
	}
	
	public function getXLSFilesFieldsValues()
	{
		$zipfile_txt = $this->l('No XLS files generated');
		
		if(file_exists(OBS_FACTUSOL_EXPORT_ZIP)){
				
			$lasttime = date("d/m/Y H:i:s", filemtime(OBS_FACTUSOL_EXPORT_ZIP));
			$zipfile_txt = '<span style="color:red">Fichero de exportación generado en fecha <em>'.$lasttime.'</em>&nbsp;&nbsp; <a href="'.'http://'.ShopUrl::getMainShopDomain(Context::getContext()->shop->id).__PS_BASE_URI__.'modules/obsfactusolpro/export/export_factusol.zip">Descargar</a><br/><br/>';
		}
		$inputsDefaultVaules =  array(
				'obs_lang' => Tools::getValue('obs_lang', Configuration::get('OBS_FACTUSOL_LANG')),
				'obs_xls_files' => $zipfile_txt,
				'obs_tariff_code' => Tools::getValue('obs_tariff_code', Configuration::get('OBS_FACTUSOL_EXPORT_TARIFF_CODE')),
				'obs_min_stock' => Tools::getValue('obs_min_stock', Configuration::get('OBS_FACTUSOL_MIN_STOCK')),
				'obs_max_stock' => Tools::getValue('obs_max_stock', Configuration::get('OBS_FACTUSOL_MAX_STOCK')),
				'obs_warehouse_code' => Tools::getValue('obs_warehouse_code', Configuration::get('OBS_FACTUSOL_WAREHOUSE_CODE')),
				'obs_product_start' => Tools::getValue('obs_product_start', 1),
				'obs_product_end' => Tools::getValue('obs_product_end', 1000)
		);
	
		return $inputsDefaultVaules;
	
	}
		
	private function getOrdersFormContent(){
	
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Order files manual generation:'),
					'icon' => 'icon-wrench'
				),
				'input' => array(
					
					array(
						'type' => 'text',
						'label' => $this->l('Order ID from'),
						'size' => 45,
						'name' => 'start_p',
					),
					array(
						'type' => 'text',
						'label' => $this->l('to'),
						'name' => 'end_p',
					),
					
					
					
				),
				'submit' => array(
					'title' => $this->l('Create FactuSOL order files'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnCreateAllOrders';
		$helper->currentIndex = $this->context->link->getAdminLink('OBSFactusolproAdminTab', false);
		$helper->token = Tools::getAdminTokenLite('OBSFactusolproAdminTab');
		$helper->tpl_vars = array(
			'fields_value' => $this->getOrdersFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}
	
	public function getOrdersFieldsValues()
	{
		return array(
			'start_p' => Tools::getValue('start_p', '1'),
			'end_p' => Tools::getValue('end_p', '200')
		);
	}
	
	private function getCustomersFormContent(){
		
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Customer files manual generation:'),
					'icon' => 'icon-wrench'
				),
				'input' => array(
					
					array(
						'type' => 'text',
						'label' => $this->l('Cusomter ID from'),
						'name' => 'start_c',
					),
					array(
						'type' => 'text',
						'label' => $this->l('to'),
						'name' => 'end_c',
					),
					
					
					
				),
				'submit' => array(
					'title' => $this->l('Create FactuSOL customers files'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnCreateAllCustomers';
		$helper->currentIndex = $this->context->link->getAdminLink('OBSFactusolproAdminTab', false);
		$helper->token = Tools::getAdminTokenLite('OBSFactusolproAdminTab');
		$helper->tpl_vars = array(
			'fields_value' => $this->getCustomersFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}
	
	public function getCustomersFieldsValues()
	{
		return array(
			'start_c' => Tools::getValue('start_c', '1'),
			'end_c' => Tools::getValue('end_c', '200')
		);
	}
	
	private function getExportFormContent(){

		$taxes = Tax::getTaxes();
		$serieIds = array();
		
		for($i=1;$i<10;$i++)
		{
			$serieIds[$i]['id'] = (int)$i;
			$serieIds[$i]['name'] = (int)$i;
		}
		
		//PAYMENT METHODS MODULES
		$paymentModules = PaymentModule::getInstalledPaymentModules();
		//var_dump($paymentModules);die;
		$paymentInputs = array();
		$paymentDefaultValues = array();
		foreach($paymentModules as $payment){
			
			$paymentInputs[] = array(
						'type' => 'text',
						'label' => $this->l('FactuSOL payment ID for').' \''.$payment['name'].'\':',
						'hint' => $this->l('Input FatuSOL payment ID for Prestashop payment method').' \''.$payment['name'].'\'',
 						'name' => 'id_for_'.$payment['name'],
						'maxlength' => 3,
					);
			
			if(version_compare(_PS_VERSION_,'1.6','<'))
				$paymentDefaultValues['id_for_'.$payment['name']] = Tools::getValue('id_for_'.$payment['name'], Configuration::get('OBS_'.Tools::substr(Tools::strtoupper($payment['name']),0,28)));
			else
				$paymentDefaultValues['id_for_'.$payment['name']] = Tools::getValue('id_for_'.$payment['name'], Configuration::get('OBS_'.Tools::strtoupper($payment['name'])));
			
			
		}
		
		$exportInputs = array(
					
					array(
							'type' => 'select',
							'label' => $this->l('Serie ID:'),
							'hint' => $this->l('Select serie ID for internet orders configured in FactuSOL customer orders tab'),
							'name' => 'obs_factusol_serie_id',
							'options' => array(
									'query' => $serieIds,
									'id' => 'id',
									'name' => 'name'
							),
					),
				
					array(
						'type' => 'text',
						'label' => $this->l('First Free Order ID:'),
						'hint' => $this->l('Order Id from which it is to begin imported in Factusol'),
 						'name' => 'first_o_free_id',
					),
					array(
						'type' => 'text',
						'label' => $this->l('First Free Customer ID:'),
						'hint' => $this->l('Customer Id from which it is to begin imported in Factusol'),
						'name' => 'first_c_free_id',
					),
						
					array(
							'type' => (version_compare(_PS_VERSION_,'1.6','<'))?'radio':'switch',
							'label' => $this->l('Include shipping costs:'),
							'hint' => $this->l('Activate this field if you want to include shipping costs in orders'),
							'class' => 't',
							'name' => 'obs_factusol_shipping',
							'is_bool' => true,
							'values' => array(
									array(
											'id' => 'shipping_active_on',
											'value' => 1,
											'label' => $this->l('Enabled')
									),
									array(
											'id' => 'shipping_active_off',
											'value' => 0,
											'label' => $this->l('Disabled')
									)
							),
					),
						
					array(
						'type' => 'select',
						'label' => $this->l('IVA Standard:'),
						'name' => 'obs_factusol_iva_1',
						'options' => array(
						'query' => $taxes,
						'id' => 'id_tax',
						'name' => 'rate'
						),
					),
					
					array(
						'type' => 'select',
						'label' => $this->l('IVA Reducido:'),
						'name' => 'obs_factusol_iva_2',
						'options' => array(
						'query' => $taxes,
						'id' => 'id_tax',
						'name' => 'rate'
						),
					),
					
					array(
						'type' => 'select',
						'label' => $this->l('IVA Superreducido:'),
						'name' => 'obs_factusol_iva_3',
						'options' => array(
						'query' => $taxes,
						'id' => 'id_tax',
						'name' => 'rate'
						),
					));
		
		$inputs = array_merge($exportInputs, $paymentInputs);
		
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Customers and orders export settings'),
					'icon' => 'icon-wrench'
				),
				'input' => 
					
					
					$inputs
					
					
				,
				'submit' => array(
					'title' => $this->l('Save Export Settings'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'exportSettings';
		$helper->currentIndex = $this->context->link->getAdminLink('OBSFactusolproAdminTab', false);
		$helper->token = Tools::getAdminTokenLite('OBSFactusolproAdminTab');
		$helper->tpl_vars = array(
			'fields_value' => $this->getExportFieldsValues($paymentDefaultValues),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}
	
	public function getExportFieldsValues($paymentDefaultValues)
	{
		
		$inputsDefaultVaules =  array(
			'obs_factusol_serie_id' => Tools::getValue('obs_factusol_serie_id', Configuration::get('OBS_FACTUSOL_SERIE_ID')),
			'first_o_free_id' => Tools::getValue('first_o_free_id', Configuration::get('OBS_FIRST_O_FREE_ID')),
			'first_c_free_id' => Tools::getValue('first_c_free_id', Configuration::get('OBS_FIRST_C_FREE_ID')),
			'obs_factusol_shipping' => Tools::getValue('obs_factusol_shipping', Configuration::get('OBS_FACTUSOL_SHIPPING')),
			'obs_factusol_iva_1' => Tools::getValue('obs_factusol_iva_1', Configuration::get('OBS_FACTUSOL_IVA_1')),
			'obs_factusol_iva_2' => Tools::getValue('obs_factusol_iva_2', Configuration::get('OBS_FACTUSOL_IVA_2')),
			'obs_factusol_iva_3' => Tools::getValue('obs_factusol_iva_3', Configuration::get('OBS_FACTUSOL_IVA_3')));
		
		return array_merge($inputsDefaultVaules, $paymentDefaultValues);
		
	}
	
	private function getImportFormContent(){

		$taxRules = TaxRulesGroup::getTaxRulesGroups(true); 
		
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Products stocks and prices import settings'),
					'icon' => 'icon-wrench'
				),
				'input' => array(
					
					array(
						'type' => (version_compare(_PS_VERSION_,'1.6','<'))?'radio':'switch',
						'label' => $this->l('Update product PRICES:'),
						'hint' => $this->l('Activate this field if you want to import prices'),
						'class' => 't',
						'name' => 'obs_factusol_price',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'prices_active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'prices_active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						),
					),
					
					array(
						'type' => (version_compare(_PS_VERSION_,'1.6','<'))?'radio':'switch',
						'label' => $this->l('Update product STOCKS:'),
						'hint' => $this->l('Activate this field if you want to import stocks'),
						'class' => 't',
						'name' => 'obs_factusol_stock',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'stocks_active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'stocks_active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						),
					),
					
					array(
							'type' => (version_compare(_PS_VERSION_,'1.6','<'))?'radio':'switch',
							'label' => $this->l('Origin prices with tax included:'),
							'hint' => $this->l('Activate this field if your products have tax included in FactuSOL'),
							'class' => 't',
							'name' => 'obs_factusol_origen_coniva',
							'is_bool' => true,
							'values' => array(
									array(
											'id' => 'origen_active_on',
											'value' => 1,
											'label' => $this->l('Enabled')
									),
									array(
											'id' => 'origen_active_off',
											'value' => 0,
											'label' => $this->l('Disabled')
									)
							),
					),
						
					array(
								'type' => (version_compare(_PS_VERSION_,'1.6','<'))?'radio':'switch',
								'label' => $this->l('Generate CSV Import files:'),
								'hint' => $this->l('Activate this field if you want to import new categories and products from FactuSOL to PrestaShop'),
								'class' => 't',
								'name' => 'obs_factusol_importcsv',
								'is_bool' => true,
								'values' => array(
										array(
												'id' => 'csv_active_on',
												'value' => 1,
												'label' => $this->l('Enabled')
										),
										array(
												'id' => 'csv_active_off',
												'value' => 0,
												'label' => $this->l('Disabled')
										)
								),
						),
						
					array(
						'type' => (version_compare(_PS_VERSION_,'1.6','<'))?'radio':'switch',
						'label' => $this->l('DISABLE unsearched products:'),
						'hint' => $this->l('Activate this field if you want to disable unsearched products from FactuSOL'),
						'class' => 't',
						'name' => 'obs_factusol_disable',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'disable_active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'disable_active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						),
					),
					
					array(
						'type' => 'text',
						'hint' => $this->l('Set ID TARIFA configured on FactuSOL for all your products'),
						'label' => $this->l('ID TARIFA factusol:'),
						'name' => 'obs_factusol_tarifa'
					),
						
					array(
							'type' => 'select',
							'label' => $this->l('IVA Standard:'),
							'name' => 'obs_factusol_iva_import_1',
							'options' => array(
									'query' => $taxRules,
									'id' => 'id_tax_rules_group',
									'name' => 'name'
							),
					),
						
					array(
							'type' => 'select',
							'label' => $this->l('IVA Reducido:'),
							'name' => 'obs_factusol_iva_import_2',
							'options' => array(
									'query' => $taxRules,
									'id' => 'id_tax_rules_group',
									'name' => 'name'
							),
					),
						
					array(
							'type' => 'select',
							'label' => $this->l('IVA Superreducido:'),
							'name' => 'obs_factusol_iva_import_3',
							'options' => array(
									'query' => $taxRules,
									'id' => 'id_tax_rules_group',
									'name' => 'name'
							),
				
						
					),
					array(
						'type' => 'free',
						'hint' => $this->l('URL que debe indicar en las Configuraciones técnicas de Internet de su factuSOL en la casilla "Ruta de pagina de activacion de actualizacion"'),
						'label' => $this->l('Sync URL:'),
						'name' => 'obs_factusol_urlsync'
					),
					
					
					
					
				),
				'submit' => array(
					'title' => $this->l('Save Import Settings'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'importSettings';
		$helper->currentIndex = $this->context->link->getAdminLink('OBSFactusolproAdminTab', false);
		$helper->token = Tools::getAdminTokenLite('OBSFactusolproAdminTab');
		$helper->tpl_vars = array(
			'fields_value' => $this->getImportFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}
	
	public function getImportFieldsValues()
	{
		return array(
			'obs_factusol_importcsv' => Tools::getValue('obs_factusol_importcsv', Configuration::get('OBS_FACTUSOL_IMPORTCSV')),
			'obs_factusol_price' => Tools::getValue('obs_factusol_price', Configuration::get('OBS_FACTUSOL_PRICE')),
			'obs_factusol_stock' => Tools::getValue('obs_factusol_stock', Configuration::get('OBS_FACTUSOL_STOCK')),
			'obs_factusol_disable' => Tools::getValue('obs_factusol_disable', Configuration::get('OBS_FACTUSOL_DISABLE')),
			'obs_factusol_tarifa' => Tools::getValue('obs_factusol_tarifa', Configuration::get('OBS_FACTUSOL_TARIFA')),
			'obs_factusol_origen_coniva' => Tools::getValue('obs_factusol_origen_coniva', Configuration::get('OBS_FACTUSOL_ORIGEN_CONIVA')),
			'obs_factusol_iva_import_1' => Tools::getValue('obs_factusol_iva_import_1', Configuration::get('OBS_FACTUSOL_IVA_IMPORT_1')),
			'obs_factusol_iva_import_2' => Tools::getValue('obs_factusol_iva_import_2', Configuration::get('OBS_FACTUSOL_IVA_IMPORT_2')),
			'obs_factusol_iva_import_3' => Tools::getValue('obs_factusol_iva_import_3', Configuration::get('OBS_FACTUSOL_IVA_IMPORT_3')),
			'obs_factusol_urlsync' => 'http://'.ShopUrl::getMainShopDomain(Context::getContext()->shop->id).__PS_BASE_URI__.'modules/obsfactusolpro/FactuSync.php?token='.Configuration::get('OBS_FACTUSOL_TOKEN')
		);
	}	
	
	private function setStartWrapper($iconLink, $title){
		
		if(version_compare(_PS_VERSION_,'1.6','<')){
			
			return '<fieldset>
 	 					<legend><img src="'.$iconLink.'" alt="" title="" /> '.$title.'</legend>';
		}
		else{
			return '<div class="panel" id="fieldset_0">
             		<div class="panel-heading">
                		<img src="'.$iconLink.'" alt="" title="" /> '.$title.'
                    </div>';
			
		}
	}
	
	private function setEndWrapper(){
		
		if(version_compare(_PS_VERSION_,'1.6','<')){
			
			return '</fieldset>';
		}
		else{
			return '</div>';
		}
	}
	
	private function boFooter14(){
	
		$output = '';
	
		$output .='<p style="clear: both"></p>
 	 	<fieldset style="width:450px; height:140px; float:left;">
 	 	<legend><img src="../img/admin/pdf.gif" alt="" title="" /> '.$this->l('Instructions').'</legend>';
	
		$locale = Language::getIsoById($this->context->cookie->id_lang);
	
		if($locale == 'es' AND $locale == 'ca' AND $locale == 'gl')
			$locale = 'es';
		else
			$locale = 'en';
	
		 
		$output .='<p>'.$this->l('Check the instructions manual here').':';
	
		if(file_exists(dirname(__FILE__).'/../../docs/readme_en.pdf'))
			$output .='<br/><br/> <a href="'.$this->module->getPathUri().'docs/readme_en.pdf" target="_blank">'.$this->l('English version manual').'</a>';
		if(file_exists(dirname(__FILE__).'/../../docs/readme_es.pdf'))
			$output .='<br/><br/> <a href="'.$this->module->getPathUri().'docs/readme_es.pdf" target="_blank">'.$this->l('Spanish version manual').'</a>';
	
		$output .='</p>
 	 	</fieldset>
 	 	<fieldset style="margin-left: 500px;">
 	 	<legend><img src="../img/admin/medal.png" alt="" title="" /> '.$this->l('Developed by').'</legend>';
	
		$output .='
 	 	<div style="width: 330px; margin: 0 auto; padding:10px;">
 	 	<a href="http://addons.prestashop.com/'.$locale.'/65_obs-solutions" target="_blank"><img src="'.$this->module->getPathUri().'views/img/logo.obsolutions.png" alt="'.$this->l('Developed by').' OBSolutions" title="'.$this->l('Developed by').' OBSolutions"/></a>
 	 	</div>
 	 	<p style="text-align:center"><a href="http://addons.prestashop.com/'.$locale.'/65_obs-solutions" target="_blank">'.$this->l('See all our modules on PrestaShop Addons clicking here').'</a></p>
	
 	 	</fieldset>
 	 	';
	
		return $output;
	}
	
	private function boFooter16(){
	
		$output = '';
	
		$output .='<p style="clear: both"></p>
		<div class="panel" id="fieldset_0" style="width:500px; height:164px; float:left;">
             <div class="panel-heading">
                <img src="../img/admin/pdf.gif" alt="" title="" /> '.$this->l('Instructions').'
                        </div>
 	 	';
	
		$locale = Language::getIsoById($this->context->cookie->id_lang);
	
		if($locale == 'es' AND $locale == 'ca' AND $locale == 'gl')
			$locale = 'es';
		else
			$locale = 'en';
	
		$output .='<p>'.$this->l('Check the instructions manual here').':';
	
		if(file_exists(dirname(__FILE__).'/../../docs/readme_en.pdf'))
			$output .='<br/><br/> <a href="'.$this->module->getPathUri().'docs/readme_en.pdf" target="_blank">'.$this->l('English version manual').'</a>';
		if(file_exists(dirname(__FILE__).'/../../docs/readme_es.pdf'))
			$output .='<br/><br/> <a href="'.$this->module->getPathUri().'docs/readme_es.pdf" target="_blank">'.$this->l('Spanish version manual').'</a>';
	
		$output .='</p>
 	 	</div>
 	 	<div class="panel" id="fieldset_0" style="margin-left: 520px;">
             <div class="panel-heading">
                <img src="../img/admin/medal.png" alt="" title="" /> '.$this->l('Developed by').'
                        </div>
 	 	';
	
		$output .='
 	 	<div style="width: 330px; margin: 0 auto; padding:10px;">
 	 	<a href="http://addons.prestashop.com/'.$locale.'/65_obs-solutions" target="_blank"><img style="height:50px;" src="'.$this->module->getPathUri().'views/img/logo.obsolutions.png" alt="'.$this->l('Developed by').' OBSolutions" title="'.$this->l('Developed by').' OBSolutions"/></a>
 	 	</div>
 	 	<p style="text-align:center"><a href="http://addons.prestashop.com/'.$locale.'/65_obs-solutions" target="_blank">'.$this->l('See all our modules on PrestaShop Addons clicking here').'</a></p>
	
 	 	</div>
 	 	';
	
		return $output;
	}
	
}
?>