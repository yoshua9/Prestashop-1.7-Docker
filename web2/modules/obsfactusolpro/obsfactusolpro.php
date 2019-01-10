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

class obsfactusolpro extends Module {
	public function __construct() {
		$this->name = 'obsfactusolpro';
		parent::__construct ();
		
		$this->tab = 'migration_tools';
		$this->version = '4.4.2';
		$this->author = 'OBSolutions.es';
		$this->module_key = 'd648ceeb156c2887d3aebf974f91604a';
		
		
		$this->displayName = $this->l ( 'FactuSOL Pro Connector' );
		$this->description = $this->l ( 'Allows exporting customers and orders from PrestaShop to FactuSOL and importing full data categories, full data product, prices and stocks from FactuSOL to PrestaShop' );
		$this->confirmUninstall = $this->l ( 'Are you sure you want to delete your details ?' );
		
		$this->_errors = array ();
		
		/* Backward compatibility */
		if (version_compare ( _PS_VERSION_, '1.5', '<' ))
			require (_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/backward.php');
	}
	public function install() {
		include (dirname ( __FILE__ ) . '/sql/install.php');
		
		if (! parent::install () or ! $this->registerHook ( 'createAccount' ) or ! $this->registerHook ( 'orderConfirmation' ) or ! $this->registerHook ( 'updateOrderStatus' ) or ! $this->resetConfigVars ())
			return false;
		
		if (version_compare ( _PS_VERSION_, '1.5', '<' )) {
			if (! $this->addTab ())
				return false;
		} else {
			
			if (! $this->createSubmenu ())
				return false;
		}
		
		chmod ( dirname ( __FILE__ ) . '/orders/', 0777 );
		chmod ( dirname ( __FILE__ ) . '/customers/', 0777 );
		chmod ( dirname ( __FILE__ ) . '/import/', 0777 );
		chmod ( dirname ( __FILE__ ) . '/images/', 0777 );
		chmod ( dirname ( __FILE__ ) . '/logs/', 0777 );
		return true;
	}
	public function uninstall() {
		if (! parent::uninstall () or ! $this->deleteConfigVars ())
			return false;
		
		if (version_compare ( _PS_VERSION_, '1.5', '<' )) {
			if (! $this->deleteTab ())
				return false;
		} else {
			
			if (! $this->deleteSubmenu ())
				return false;
		}
		
		return true;
	}
	public function addTab() {
		$tab = new Tab ();
		$languages = Language::getLanguages ( false );
		foreach ( $languages as $lang )
			$tab->name [$lang ['id_lang']] = 'FactuSOL';
		$tab->class_name = 'OBSFactusolAdminTab14';
		$tab->module = $this->name;
		$tab->id_parent = Tab::getIdFromClassName('AdminParentOrders');
		
		return $tab->save () ? true : false;
	}
	public function deleteTab() {
		if (($factusolTabId = Tab::getIdFromClassName('OBSFactusolAdminTab14')) > 0) {
			$tab = new Tab ( $factusolTabId );
			$tab->delete ();
			return true;
		}
		return false;
	}
	private function createSubmenu() {
		$parentId = Tab::getIdFromClassName('AdminParentOrders');
		$menuName = array (
				'1' => 'FactuSOL PRO' 
		);
		$className = 'OBSFactusolproAdminTab';
		
		$subTab = Tab::getInstanceFromClassName ( $className );
		if (! Validate::isLoadedObject ( $subTab )) {
			$subTab->active = 1;
			$subTab->class_name = $className;
			$subTab->id_parent = $parentId;
			$subTab->module = $this->name;
			$subTab->name = $this->createMultiLangFieldHard ( $menuName );
			return $subTab->save ();
		} elseif ($subTab->id_parent != $parentId) {
			$subTab->id_parent = $parentId;
			return $subTab->save ();
		}
		return true;
	}
	public function resetConfigVars() {
		if (Configuration::updateValue ( 'OBS_FIRST_O_FREE_ID', 0 ) and Configuration::updateValue ( 'OBS_FIRST_C_FREE_ID', 0 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_IVA_1', 1 ) and Configuration::updateValue ( 'OBS_FACTUSOL_IVA_2', 2 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_IVA_3', 3 ) and Configuration::updateValue ( 'OBS_FACTUSOL_IVA_IMPORT_1', 1 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_IVA_IMPORT_2', 2 ) and Configuration::updateValue ( 'OBS_FACTUSOL_IVA_IMPORT_3', 3 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_DISABLE', 0 ) and Configuration::updateValue ( 'OBS_FACTUSOL_STOCK', 1 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_PRICE', 1 ) and Configuration::updateValue ( 'OBS_FACTUSOL_IMPORTCSV', 0 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_SERIE_ID', 8 ) and Configuration::updateValue ( 'OBS_FACTUSOL_TOKEN', Tools::passwdGen ( 25 ) ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_SHIPPING', 0 ) and Configuration::updateValue ( 'OBS_FACTUSOL_ORIGEN_CONIVA', 0 ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_TARIFA', '1' ) and Configuration::updateValue ( 'OBS_FACTUSOL_WAREHOUSE_CODE', 'GEN' ) 
			and Configuration::updateValue ( 'OBS_FACTUSOL_MIN_STOCK', '2' ) and Configuration::updateValue ( 'OBS_FACTUSOL_MAX_STOCK', '10' )
			and Configuration::updateValue ( 'OBS_FACTUSOL_LANG', Configuration::get('PS_LANG_DEFAULT')) and Configuration::updateValue ( 'OBS_FACTUSOL_EXPORT_TARIFF_CODE', '1'))
			return true;
		else
			return false;
	}
	public function deleteConfigVars() {
		if (Configuration::deleteByName ( 'OBS_FIRST_O_FREE_ID' ) and Configuration::deleteByName ( 'OBS_FIRST_C_FREE_ID' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_IVA_1' ) 
		and Configuration::deleteByName ( 'OBS_FACTUSOL_IVA_2' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_IVA_3' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_IVA_IMPORT_1' ) 
		and Configuration::deleteByName ( 'OBS_FACTUSOL_IVA_IMPORT_2' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_IVA_IMPORT_3' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_DISABLE' ) 
		and Configuration::deleteByName ( 'OBS_FACTUSOL_STOCK' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_PRICE' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_IMPORTCSV' ) 
		and Configuration::deleteByName ( 'OBS_FACTUSOL_SERIE_ID' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_TOKEN' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_SHIPPING' ) 
		and Configuration::deleteByName ( 'OBS_FACTUSOL_ORIGEN_CONIVA' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_TARIFA' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_WAREHOUSE_CODE' )
		and Configuration::deleteByName ( 'OBS_FACTUSOL_MIN_STOCK' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_MAX_STOCK' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_LANG' ) and Configuration::deleteByName ( 'OBS_FACTUSOL_EXPORT_TARIFF_CODE' ))
			return true;
		else
			return false;
	}
	public function hookCreateAccount($params) {
		include_once ('classes/Factusol.php');
		
		return Factusol::createCustomerFile ( $params ['newCustomer'] );
	}
	public function hookOrderConfirmation($params) {
		include_once ('classes/Factusol.php');
		
		$order = $params ['objOrder'];
		
		if ($order->valid == '1') {
			if (Factusol::createOrderFile ( $order )) {
				// ACTUALIZAMOS EL CLIENTE POR SI HA CAMBIADO LA DIRECCION / UTILIZAMOS LA DE FACTURACIÓN
				Factusol::createCustomerFile ( new Customer ( $order->id_customer ), $order->id_address_invoice );
				
			}
		}
	}
	public function hookUpdateOrderStatus($params) {
		include_once ('classes/Factusol.php');
		include_once ('classes/OrderLog.php');
		
		// SOLO CREAMOS EL FICHERO DE PEDIDO SI NO SE HA CREADO ANTERIORMENTE
		if (! OrderLog::existsOrderLog ( $params ['id_order'] )) {
			$order = new Order ( $params ['id_order'] );
			$newOrderStatus = $params ['newOrderStatus'];
			if ($newOrderStatus->logable == '1' and $order->valid == '0') {
				if (Factusol::createOrderFile ( $order )) {
					OrderLog::insertOrderLog ( $params ['id_order'] );
					
					// ACTUALIZAMOS EL CLIENTE POR SI HA CAMBIADO LA DIRECCION / UTILIZAMOS LA DE FACTURACIÓN
					Factusol::createCustomerFile ( new Customer ( $order->id_customer ), $order->id_address_invoice );
					
					return true;
				} else {
					return false;
				}
			}
		}
	}
	private static function createMultiLangFieldHard($res) {
		$languages = Language::getLanguages ( false );
		foreach ( $languages as $lang ) {
			if (! array_key_exists ( $lang ['id_lang'], $res ))
				$res [$lang ['id_lang']] = $res ['1'];
		}
		return $res;
	}
	private function deleteSubmenu() {
		$className = 'OBSFactusolproAdminTab';
		$subTab = Tab::getInstanceFromClassName ( $className );
		return $subTab->delete ();
	}
}
