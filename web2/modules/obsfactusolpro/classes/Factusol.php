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

define('OBS_FACTUSOL_CL_DIR_INT', dirname(__FILE__).'/../customers/');
define('OBS_FACTUSOL_ORD_DIR_INT', dirname(__FILE__).'/../orders/');
define( 'OBS_FACTUSOL_IMP_DIR_INT',	dirname(__FILE__).'/../import/' );
define( 'OBS_FACTUSOL_LOG_DIR_INT',	dirname(__FILE__).'/../logs/' );
define('OBS_CSV_DIR', dirname(__FILE__).'/../csv/');

/** PHPExcel */
include_once('PHPExcel.php');
/** PHPExcel_Writer_Excel5 */
include_once('PHPExcel/Writer/Excel5.php');
	
class Factusol extends ObjectModel {
	
	
	public static function getClientesPendientes() {
		$clientes = 0;
		foreach(scandir(OBS_FACTUSOL_CL_DIR_INT) as $f)
			if(preg_match('/\d+\.txt/i', $f))
				$clientes++;
		return $clientes;
	}
	
	public static function getPedidosPendientes() {
		$pedidos = 0;
		foreach(scandir(OBS_FACTUSOL_ORD_DIR_INT) as $f)
			if(preg_match('/\d+\.txt/i', $f))
				$pedidos++;
		return $pedidos;
	}

	public static function rellenarCeros($number) {
		return sprintf("%06d",  $number);
	}
	
	public static function getTaxIdByRate($rate, $active = 1)
	{
	    $tax = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT t.`id_tax`
			FROM `'._DB_PREFIX_.'tax` t
			LEFT JOIN `'._DB_PREFIX_.'tax_lang` tl ON (t.id_tax = tl.id_tax)
			WHERE t.`deleted` = 0 AND t.`rate` = '.(float)($rate).
			($active == 1 ? ' AND t.`active` = 1' : ''));
		return $tax ? (int)($tax['id_tax']) : false;
	}
	
	public static function createCustomerFile($customer, $id_invoice_address = 0){
		if(!Validate::isLoadedObject($customer) OR !isset($customer->id))
			return false;

		if($id_invoice_address)
			$address = new Address($id_invoice_address);
		else	
			$address = new Address(AddressCore::getFirstCustomerAddressId($customer->id));
		
		$ID = self::rellenarCeros(Configuration::get('OBS_FIRST_C_FREE_ID')+(int)$customer->id);
		
		if($address->company AND trim($address->company) != '')
			$SNOMCFG = trim(self::convert_to_iso($address->company));
		else
			$SNOMCFG = trim(self::convert_to_iso($customer->firstname).' '.self::convert_to_iso($customer->lastname));
		
		$SDOMCFG = trim(self::convert_to_iso($address->address1).' '.self::convert_to_iso($address->address2));
		$SCPOCFG = trim($address->postcode);
		$SPOBCFG = trim(self::convert_to_iso($address->city));
		$SNIFCFG = trim($address->dni);
		$STELCFG = trim((($address->phone)?$address->phone:$address->phone_mobile));
		$SEMACFG = trim($customer->email);
		
		
		//PROVINCIA
		$SPROCFG = "";
		$provincia_id = (int) $address->id_state;
		if($provincia_id != null AND $provincia_id != 0)
			$SPROCFG = self::convert_to_iso(State::getNameById($provincia_id));
		
		$fileContent  = $ID."\\\n";
		$fileContent  .= "SNOMCFG:".$SNOMCFG."\n";
		$fileContent  .= "SDOMCFG:".$SDOMCFG."\n";
		$fileContent  .= "SCPOCFG:".$SCPOCFG."\n";
		$fileContent  .= "SPOBCFG:".$SPOBCFG."\n";
		$fileContent  .= "SPROCFG:".$SPROCFG."\n";
		$fileContent  .= "SNIFCFG:".$SNIFCFG."\n";
		$fileContent  .= "STELCFG:".$STELCFG."\n";
		$fileContent  .= "SEMACFG:".$SEMACFG."\n";
		$fileContent  .= "Terminado:Alta Cliente\n";
	
		if(file_put_contents(OBS_FACTUSOL_CL_DIR_INT.$customer->id.'.txt', $fileContent) !== false) {
			chmod(OBS_FACTUSOL_CL_DIR_INT.$customer->id.'.txt', 0776);
			return true;
		} else
			return false;
	}
	
	public static function createOrderFile($objOrder){
		
		if(!Validate::isLoadedObject($objOrder) OR !isset($objOrder->id))
			return false;
		/*if(version_compare(_PS_VERSION_,'1.5','<'))
			$currentOrderState = OrderHistory::getLastOrderState($objOrder->id);
		else
			$currentOrderState = new OrderState($objOrder->current_state, 1);
		if (!$customStatus AND $currentOrderState->template == 'shipped')
			$estado = '2';*/
		
		$total_paid = 0;
		if($objOrder->total_paid_real == 0)
			$total_paid = $objOrder->total_paid;
		else
			$total_paid = $objOrder->total_paid_real;
		
		$customerId = self::rellenarCeros(Configuration::get('OBS_FIRST_C_FREE_ID')+(int)$objOrder->id_customer);
		$total_sin_ge = (float)($total_paid)-(float)($objOrder->total_shipping);
		$shipping = self::convert_to_iso('Gastos de envío: ').($objOrder->total_shipping/(1+$objOrder->carrier_tax_rate/100)).'+IVA = '.$objOrder->total_shipping;
	
		//Payment method
		$paymentId = '';
		if(version_compare(_PS_VERSION_,'1.6','<')){
			if(Configuration::get('OBS_'.Tools::substr(Tools::strtoupper($objOrder->module),0,28)))
				$paymentId = Configuration::get('OBS_'.Tools::substr(Tools::strtoupper($objOrder->module),0,28));
		}
		else{
			
			if(Configuration::get('OBS_'.Tools::strtoupper($objOrder->module)))
				$paymentId = Configuration::get('OBS_'.Tools::strtoupper($objOrder->module));
		}
		
		$taxes = Tax::getTaxes();
		$PIVA1PCL = 21;
		$PIVA2PCL = 10;
		$PIVA3PCL = 4;
		foreach($taxes as $tax) {
			if($tax['id_tax'] == Configuration::get('OBS_FACTUSOL_IVA_1'))
				$PIVA1PCL = (int)$tax['rate'];
			if($tax['id_tax'] == Configuration::get('OBS_FACTUSOL_IVA_2'))
				$PIVA2PCL = (int)$tax['rate'];
			if($tax['id_tax'] == Configuration::get('OBS_FACTUSOL_IVA_3'))
				$PIVA3PCL = (int)$tax['rate'];
		}
		
		$IIVA1PCL = 0;
		$IIVA2PCL = 0;
		$IIVA3PCL = 0;
		
		$IDTO1PCL = 0;
		$IDTO2PCL = 0;
		$IDTO3PCL = 0;
		
		$TOTALBASEIVA1 = 0;
		$TOTALBASEIVA2 = 0;
		$TOTALBASEIVA3 = 0;
		
		//Recargos de equivalencia (Compatibilidad con módulo de alabazweb)
		$IREC1PCL = 0;
		$IREC2PCL = 0;
		$IREC3PCL = 0;
		$RECPCL = 0;
		
		$productData = '';
		$i=1;
		
		//var_dump($objOrder);die();
		
		$PDISCOUNT = 0;
		$PDTO = 0;
		if((float)($objOrder->total_discounts) > 0){
			if($objOrder->total_discounts_tax_incl > $objOrder->total_discounts_tax_excl){
				if(Configuration::get('OBS_FACTUSOL_SHIPPING'))
					$PDISCOUNT = round((float)($objOrder->total_discounts) / (float)($objOrder->total_products_wt+$objOrder->total_shipping_tax_incl), 6);
				else
					$PDISCOUNT = round((float)($objOrder->total_discounts) / (float)($objOrder->total_products_wt), 6);
			}
			else
			{
				if(Configuration::get('OBS_FACTUSOL_SHIPPING'))
					$PDISCOUNT = round((float)($objOrder->total_discounts) / (float)($objOrder->total_products+$objOrder->total_shipping_tax_excl), 6);
				else
					$PDISCOUNT = round((float)($objOrder->total_discounts) / (float)($objOrder->total_products), 6);
			}
			$PDTO = $PDISCOUNT*100;
		
		}
		
		foreach($objOrder->getProducts() as $p) {
			
			
			if($p['product_attribute_id'] != '' AND (int) $p['product_attribute_id'] > 0)
			{
				$combination = new Combination($p['product_attribute_id']);
				$p['product_reference'] = $combination->reference;
				
			}
			
			$TOTIVALPC = (((float)$p['unit_price_tax_incl'] - ((float)$p['unit_price_tax_excl']))*(float)$p['product_quantity']);
			$TOTLPC = round((float)$p['unit_price_tax_excl']*(float)$p['product_quantity'], 6);
			
			//SI NO TIENE DESCUENTO
			$price = $p['unit_price_tax_excl'];
			$discount_percent = 0;
			
			//SI TIENE DESCUENTO
			if(array_key_exists('reduction_percent', $p) AND (float) $p['reduction_percent'] > 0)
			{
				$price = $p['original_product_price'];
				$discount_percent = $p['reduction_percent'];
			}
			
			//Si tiene recargos de equivalencia
			$TOTRECLPC = 0;
			if(array_key_exists('extra_tax', $p) AND $p['extra_tax'] > 0)
			{
				$TOTRECLPC = round(((float)($p['unit_price_tax_excl'] ))*(float)($p['product_quantity'])*((float)($p['extra_tax'])/100), 6);
				$TOTIVALPC = round(((float)($p['unit_price_tax_excl'] ))*(float)($p['product_quantity'])*((float)($p['tax_rate']-$p['extra_tax'])/100), 6);
			}
			
			
			$IVALPC = '0'; //default value
			if((self::getTaxIdByRate($p['tax_rate']) == Configuration::get('OBS_FACTUSOL_IVA_1'))
				OR (array_key_exists('extra_tax', $p) AND self::getTaxIdByRate($p['tax_rate']-$p['extra_tax']) == Configuration::get('OBS_FACTUSOL_IVA_1'))) {
				$IVALPC = '0';
				$IIVA1PCL += $TOTIVALPC;
				$TOTALBASEIVA1 += $TOTLPC;
				$IREC1PCL += $TOTRECLPC;
				
			} elseif((self::getTaxIdByRate($p['tax_rate']) == Configuration::get('OBS_FACTUSOL_IVA_2'))
				OR (array_key_exists('extra_tax', $p) AND self::getTaxIdByRate($p['tax_rate']-$p['extra_tax']) == Configuration::get('OBS_FACTUSOL_IVA_2'))) {
				
				$IVALPC = '1';
				$IIVA2PCL += $TOTIVALPC;
				$TOTALBASEIVA2 += $TOTLPC;
				$IREC2PCL += $TOTRECLPC;
				
			} elseif((self::getTaxIdByRate($p['tax_rate']) == Configuration::get('OBS_FACTUSOL_IVA_3'))
				OR (array_key_exists('extra_tax', $p) AND self::getTaxIdByRate($p['tax_rate']-$p['extra_tax']) == Configuration::get('OBS_FACTUSOL_IVA_3'))) {
				$IVALPC = '2';
				$IIVA3PCL += $TOTIVALPC;
				$TOTALBASEIVA3 += $TOTLPC;
				$IREC3PCL += $TOTRECLPC;
			} else{
				$IIVA1PCL += $TOTIVALPC;
				$TOTALBASEIVA1 += $TOTLPC;
				$IREC1PCL += $TOTRECLPC;
			}
			
			//Marcar si tiene recargo de equivalencia
			if ($TOTRECLPC > 0) 
				$RECPCL = '1';
				
			
			//NUEMERO DE SERIE PARA LOS PEDIDOS DE INTERNET CONFIGURADO EN FACTUSOL
			$serieId = 8;
			if(Configuration::get('OBS_FACTUSOL_SERIE_ID'))
				$serieId = Configuration::get('OBS_FACTUSOL_SERIE_ID');
				
			$productData  .= "TIPLPC:".$serieId."\n";
			$productData  .= "CODLPC:".$objOrder->id."\n";
			$productData  .= "POSLPC:".$i."\n";
			$productData  .= "ARTLPC:".$p['product_reference']."\n";
			$productData  .= "DESLPC:".self::convert_to_iso($p['product_name'])."\n";
			$productData  .= "CANLPC:".$p['product_quantity']."\n";
			$productData  .= "DT1LPC:".$discount_percent."\n";
			$productData  .= "PRELPC:".$price."\n";
			$productData  .= "TOTLPC:".$TOTLPC."\n";
			$productData  .= "IVALPC:".$IVALPC."\n";
			$productData  .= "IINLPC:0\n";

			$i++;
		}
		
		//Incluir empaque regalo
		if((float)$objOrder->total_wrapping_tax_excl > 0)
		{
				
			//Total sin iva
			$TOTLPC = round((float)$objOrder->total_wrapping_tax_excl, 4);
			//Precio unitario (como son los empaques son los mismos que el total sin iva)
			$price = $TOTLPC;
			//Total de iva
			$TOTIVALPC = round((float)($objOrder->total_wrapping_tax_incl) - (float)($objOrder->total_wrapping_tax_excl), 4);
				
			//21 iva forzado
			$IVALPC = '0';
			$IIVA1PCL += $TOTIVALPC;
			$TOTALBASEIVA1 += $TOTLPC;
				
			$descEmpaque = 'Envoltorio de regalo';
				
			$productData  .= "TIPLPC:".$serieId."\n";
			$productData  .= "CODLPC:".$objOrder->id."\n";
			$productData  .= "POSLPC:".$i."\n";
			$productData  .= "ARTLPC:EMPAQUE\n";
			$productData  .= "DESLPC:".mb_convert_encoding($descEmpaque, 'ISO-8859-1')."\n";
			$productData  .= "CANLPC:1\n";
			$productData  .= "DT1LPC:0\n";
			$productData  .= "PRELPC:".$price."\n";
			$productData  .= "TOTLPC:".$TOTLPC."\n";
			$productData  .= "IVALPC:".$IVALPC."\n";
			$productData  .= "IINLPC:0\n";
		}
		
		//Incluir gastos de envio si se ha demandado
		if(Configuration::get('OBS_FACTUSOL_SHIPPING'))
		{
			
			//$shipping = utf8_decode('Gastos de envío: ').($objOrder->total_shipping/(1+$objOrder->carrier_tax_rate/100)).'+IVA = '.$objOrder->total_shipping;
			$shipping = "";
			
			//Total sin iva
			$TOTLPC = $objOrder->total_shipping_tax_excl;
			//Precio unitario (como son los gastos de envio son los mismos que el total sin iva
			$price = $TOTLPC;
			//Total de iva
			$TOTIVALPC = $objOrder->total_shipping_tax_incl - $objOrder->total_shipping_tax_excl;
			
			//Si tiene recargos de equivalencia
			$TOTRECLPC = 0;
			if(array_key_exists('extra_tax', $p) AND $p['extra_tax'] > 0) //$p tiene valor de la última vuelta del bucle de productos
			{
				$TOTRECLPC = round($objOrder->total_shipping_tax_excl * 0.052, 6);
				$TOTIVALPC = round(($objOrder->total_shipping_tax_excl)*((float)($objOrder->carrier_tax_rate-5.20)/100), 6);
			}
			
			$IVALPC = '0'; //default value
			if(self::getTaxIdByRate($objOrder->carrier_tax_rate) == Configuration::get('OBS_FACTUSOL_IVA_1')
				OR self::getTaxIdByRate($objOrder->carrier_tax_rate - 5.20) ==  Configuration::get('OBS_FACTUSOL_IVA_1')) {
				$IVALPC = '0';
				$IIVA1PCL += $TOTIVALPC;
				$TOTALBASEIVA1 += $TOTLPC;
				$IREC1PCL += $TOTRECLPC;
				
			} elseif(self::getTaxIdByRate($objOrder->carrier_tax_rate) == Configuration::get('OBS_FACTUSOL_IVA_2')) {
			
				$IVALPC = '1';
				$IIVA2PCL += $TOTIVALPC;
				$TOTALBASEIVA2 += $TOTLPC;
			
			} elseif(self::getTaxIdByRate($objOrder->carrier_tax_rate) == Configuration::get('OBS_FACTUSOL_IVA_3')) {
				$IVALPC = '2';
				$IIVA3PCL += $TOTIVALPC;
				$TOTALBASEIVA3 += $TOTLPC;
			} else{
				$IIVA1PCL += $TOTIVALPC;
				$TOTALBASEIVA1 += $TOTLPC;
			}
			
			$productData  .= "TIPLPC:".$serieId."\n";
			$productData  .= "CODLPC:".$objOrder->id."\n";
			$productData  .= "POSLPC:".$i."\n";
			$productData  .= "ARTLPC:GASTOS\n";
			$productData  .= "DESLPC:".self::convert_to_iso('Gastos envío')."\n";
			$productData  .= "CANLPC:1\n";
			$productData  .= "DT1LPC:0\n";
			$productData  .= "PRELPC:".$price."\n";
			$productData  .= "TOTLPC:".$TOTLPC."\n";
			$productData  .= "IVALPC:".$IVALPC."\n";
			$productData  .= "IINLPC:0\n";
			
			$i++;
		}
		
		//Incluir recargos
		if(isset($objOrder->payment_fee) AND $objOrder->payment_fee > 0)
		{
			
			//Total sin iva
			$TOTLPC = round($objOrder->payment_fee/(1+$objOrder->payment_fee_rate/100),6);
			//Precio unitario (como son los recargos son los mismos que el total sin iva
			$price = $TOTLPC;
			//Total de iva
			$TOTIVALPC = round((float)($objOrder->payment_fee) - $TOTLPC, 6);
			
			$IVALPC = '3'; //default value: exento
			if(self::getTaxIdByRate($objOrder->payment_fee_rate) == Configuration::get('OBS_FACTUSOL_IVA_1')) {
				$IVALPC = '0';
				$IIVA1PCL += $TOTIVALPC;
				$TOTALBASEIVA1 += $TOTLPC;
			} elseif(self::getTaxIdByRate($objOrder->payment_fee_rate) == Configuration::get('OBS_FACTUSOL_IVA_2')) {
			
				$IVALPC = '1';
				$IIVA2PCL += $TOTIVALPC;
				$TOTALBASEIVA2 += $TOTLPC;
			
			} elseif(self::getTaxIdByRate($objOrder->payment_fee_rate) == Configuration::get('OBS_FACTUSOL_IVA_3')) {
				$IVALPC = '2';
				$IIVA3PCL += $TOTIVALPC;
				$TOTALBASEIVA3 += $TOTLPC;
			} else{
				$IIVA1PCL += $TOTIVALPC;
				$TOTALBASEIVA1 += $TOTLPC;
			}
			
			$productData  .= "TIPLPC:".$serieId."\n";
			$productData  .= "CODLPC:".$objOrder->id."\n";
			$productData  .= "POSLPC:".$i."\n";
			$productData  .= "ARTLPC:RECARGO\n";
			$productData  .= "DESLPC:".self::convert_to_iso('Recargo '.$objOrder->payment)."\n";
			$productData  .= "CANLPC:1\n";
			$productData  .= "DT1LPC:0\n";
			$productData  .= "PRELPC:".$price."\n";
			$productData  .= "TOTLPC:".$TOTLPC."\n";
			$productData  .= "IVALPC:".$IVALPC."\n";
			$productData  .= "IINLPC:0\n";
		}
		
		//Si hay descuentos hay tenerlos en cuenta
		$IDTO1PCL = round($TOTALBASEIVA1 * $PDISCOUNT, 6);
		$IDTO2PCL = round($TOTALBASEIVA2 * $PDISCOUNT, 6);
		$IDTO3PCL = round($TOTALBASEIVA3 * $PDISCOUNT, 6);
		$IIVA1PCL = round($IIVA1PCL-$IIVA1PCL * $PDISCOUNT, 6);
		$IIVA2PCL = round($IIVA2PCL-$IIVA2PCL * $PDISCOUNT, 6);
		$IIVA3PCL = round($IIVA3PCL-$IIVA3PCL * $PDISCOUNT, 6);
		
		$fileContent   = "TIPPCL:".$serieId."\n";
		$fileContent  .= "CODPCL:".$objOrder->id."\n";
		$fileContent  .= "REFPCL:WEB ".$objOrder->id."\n";
		$fileContent  .= "FECPCL:".Tools::substr($objOrder->date_add, 0, 10)."\n";
		$fileContent  .= "AGEPCL:0\n";
		$fileContent  .= "CLIPCL:".$customerId."\n";
		$fileContent  .= "DIRPCL:0\n";
		$fileContent  .= "TIVPCL:0\n";
		$fileContent  .= "REQPCL:".$RECPCL."\n";
		$fileContent  .= "ALMPCL:\n";
		$fileContent  .= "NET1PCL:".$TOTALBASEIVA1."\n";
		$fileContent  .= "NET2PCL:".$TOTALBASEIVA2."\n";
		$fileContent  .= "NET3PCL:".$TOTALBASEIVA3."\n";
		$fileContent  .= "NET4PCL:0\n";
		$fileContent  .= "PDTO1PCL:".$PDTO."\n";
		$fileContent  .= "PDTO2PCL:".$PDTO."\n";
		$fileContent  .= "PDTO3PCL:".$PDTO."\n";
		$fileContent  .= "PDTO4PCL:0\n";
		$fileContent  .= "IDTO1PCL:".$IDTO1PCL."\n";
		$fileContent  .= "IDTO2PCL:".$IDTO2PCL."\n";
		$fileContent  .= "IDTO3PCL:".$IDTO3PCL."\n";
		$fileContent  .= "IDTO4PCL:0\n";
		$fileContent  .= "PPPA1PCL:0\n";
		$fileContent  .= "PPPA2PCL:0\n";
		$fileContent  .= "PPPA3PCL:0\n";
		$fileContent  .= "PPPA4PCL:0\n";
		$fileContent  .= "IPPA1PCL:0\n";
		$fileContent  .= "IPPA2PCL:0\n";
		$fileContent  .= "IPPA3PCL:0\n";
		$fileContent  .= "IPPA4PCL:0\n";
		$fileContent  .= "PFIN1PCL:0\n";
		$fileContent  .= "PFIN2PCL:0\n";
		$fileContent  .= "PFIN3PCL:0\n";
		$fileContent  .= "PFIN4PCL:0\n";
		$fileContent  .= "IFIN1PCL:0\n";
		$fileContent  .= "IFIN2PCL:0\n";
		$fileContent  .= "IFIN3PCL:0\n";
		$fileContent  .= "IFIN4PCL:0\n";
		$fileContent  .= "BAS1PCL:".round($TOTALBASEIVA1-$IDTO1PCL, 6)."\n";
		$fileContent  .= "BAS2PCL:".round($TOTALBASEIVA2-$IDTO2PCL, 6)."\n";
		$fileContent  .= "BAS3PCL:".round($TOTALBASEIVA3-$IDTO3PCL, 6)."\n";
		$fileContent  .= "BAS4PCL:0\n";
		$fileContent  .= "PIVA1PCL:".$PIVA1PCL."\n";
		$fileContent  .= "PIVA2PCL:".$PIVA2PCL."\n";
		$fileContent  .= "PIVA3PCL:".$PIVA3PCL."\n";
		$fileContent  .= "IIVA1PCL:".$IIVA1PCL."\n";
		$fileContent  .= "IIVA2PCL:".$IIVA2PCL."\n";
		$fileContent  .= "IIVA3PCL:".$IIVA3PCL."\n";
		$fileContent  .= "PREC1PCL:5.20\n";
		$fileContent  .= "PREC2PCL:1.40\n";
		$fileContent  .= "PREC3PCL:0.5\n";
		$fileContent  .= "IREC1PCL:".$IREC1PCL."\n";
		$fileContent  .= "IREC2PCL:".$IREC2PCL."\n";
		$fileContent  .= "IREC3PCL:".$IREC3PCL."\n";
		
		if(Configuration::get('OBS_FACTUSOL_SHIPPING'))
			$fileContent  .= "TOTPCL:".$total_paid."\n";
		else
			$fileContent  .= "TOTPCL:".$total_sin_ge."\n";
		
		$fileContent  .= "FOPPCL:".$paymentId."\n";
		$fileContent  .= "OB1PCL:".Tools::substr(Tools::stripslashes($objOrder->getFirstMessage()),0,100)."\n";
		$fileContent  .= "OB2PCL:".$shipping."\n";
		$fileContent  .= "PPOPCL:\n";
		$fileContent  .= "ESTPCL:0\n";

		$fileContent .= $productData;
		
		$fileContent  .= "DOCPAGO:\n";
		$fileContent  .= "STATUS:OK";
		
		$fName='pedidofactusolweb'.$serieId.self::rellenarCeros((Configuration::get('OBS_FIRST_O_FREE_ID')+$objOrder->id));
				
		if(file_put_contents(OBS_FACTUSOL_ORD_DIR_INT.$fName.'.txt', $fileContent) !== false) {
			chmod(OBS_FACTUSOL_ORD_DIR_INT.$fName.'.txt', 0776);
			return true;
		} else
			return false;
	}
	
	public static function createCategoriesImportFile($catIds)
	{
		$filename = 'FactuSOL_categories.csv';
		$sep = ";";
		$headers="ID;Active (0/1);Name *;Parent category;Root category (0/1);Description;Meta title;Meta keywords;Meta description;URL rewritten;Image URL".PHP_EOL;
		$file_path=OBS_CSV_DIR.$filename;

		$filedata = $headers;
					
		
		$secciones = Db::getInstance()->executeS("
			SELECT * FROM `F_SEC`
		");
		
		foreach ($secciones as $sec){
			
			$filedata .=  'ignore'.$sep; 		//ID
			$filedata .=  '1'.$sep; 			//Active (0/1)
			$filedata .= '"'.$sec['DESSEC'].'"'.$sep;	//Name *
			$filedata .= 'Home'.$sep;			//Parent category
			$filedata .= '0'.$sep;				//Root category (0/1)
			$filedata .= '"'.$sec['DESSEC'].'"'.$sep;	//Description
			$filedata .= '"'.$sec['DESSEC'].'"'.$sep;	//Meta title 
			$filedata .= '"'.$sec['DESSEC'].'"'.$sep;	//Meta Keywords
			$filedata .= '"'.$sec['DESSEC'].'"'.$sep;	//Meta Description
			$filedata .= Tools::str2url($sec['DESSEC']).$sep;					//URL rewrite
			$filedata .= ''.$sep;				//Image URL
			$filedata .=  PHP_EOL;
			
			
		}
		
		$categorias = Db::getInstance()->executeS("
			SELECT * FROM `F_FAM`, `F_SEC`
			WHERE `SECFAM` = `CODSEC` AND `CODFAM` IN ( '".implode("','", array_map("pSQL",$catIds))."' )"
		);
		
		foreach ($categorias as $cat){
				
			$filedata .=  'ignore'.$sep; 		//ID
			$filedata .=  '1'.$sep; 			//Active (0/1)
			$filedata .= '"'.$cat['DESFAM'].'"'.$sep;	//Name *
			$filedata .= '"'.$cat['DESSEC'].'"'.$sep;	//Parent category
			$filedata .= '0'.$sep;				//Root category (0/1)
			$filedata .= '"'.$cat['DESFAM'].'"'.$sep;	//Description
			$filedata .= '"'.$cat['DESFAM'].'"'.$sep;	//Meta title
			$filedata .= '"'.$cat['DESFAM'].'"'.$sep;	//Meta Keywords
			$filedata .= '"'.$cat['DESFAM'].'"'.$sep;	//Meta Description
			$filedata .= Tools::str2url($cat['DESFAM']).$sep;				//URL rewrite
			$filedata .= ''.$sep;				//Image URL
			$filedata .=  PHP_EOL;
				
				
		}
		
		file_put_contents($file_path,utf8_decode($filedata));
	}
	
	public static function createProductsImportFile($prodReferences)
	{
		$filename = 'FactuSOL_products.csv';
		$sep = ";";
		//var_dump(_PS_VERSION_, version_compare(_PS_VERSION_,'1.6','>='));
		//Para versiones 1.6
		if (version_compare(_PS_VERSION_,'1.6','>='))
			$headers="ID;Active (0/1);Name *;Categories (x,y,z...);Price tax excluded or Price tax included;Tax rules ID;Wholesale price;On sale (0/1);Discount amount;Discount percent;Discount from (yyyy-mm-dd);Discount to (yyyy-mm-dd);Reference #;Supplier reference #;Supplier;Manufacturer;EAN13;UPC;Ecotax;Width;Height;Depth;Weight;Quantity;Minimal quantity;Visibility;Additional shipping cost;Unity;Unit price ratio;Short description;Description;Tags (x,y,z...);Meta title;Meta keywords;Meta description;URL rewritten;Text when in stock;Text when backorder allowed;Available for order (0 = No, 1 = Yes);Product available date;Product creation date;Show price (0 = No, 1 = Yes);Image URLs (x,y,z...);Delete existing images (0 = No, 1 = Yes);Feature(Name:Value:Position);Available online only (0 = No, 1 = Yes);Condition;Customizable (0 = No, 1 = Yes);Uploadable files (0 = No, 1 = Yes);Text fields (0 = No, 1 = Yes);Out of stock;ID / Name of shop;Advanced stock management;Depends On Stock;Warehouse".PHP_EOL;
		
		else
			$headers="id;Active (0/1);Name*;Categories (x,y,z,...);Price tax excl. Or Price tax excl;Tax rules id;Wholesale price;On sale (0/1);Discount amount;Discount percent;Discount from (yyy-mm-dd);Discount to (yyy-mm-dd);Reference #;Supplier reference #;Supplier;Manufacturer;EAN13;UPC;Ecotax;Weight;Quantity;Short description;Description;Tags (x,y,z,...);Meta-title;Meta-keywords;Meta-description;URL rewritten;Text when in-stock;Text if back-order allowed;Image URLs (x,y,z,...)".PHP_EOL;
		
		$file_path=OBS_CSV_DIR.$filename;
	
		$filedata = $headers;
		
		//IVAS TIENDA
		$iva = array();
		$iva['0'] = Configuration::get('OBS_FACTUSOL_IVA_IMPORT_1');
		$iva['1'] = Configuration::get('OBS_FACTUSOL_IVA_IMPORT_2');
		$iva['2'] = Configuration::get('OBS_FACTUSOL_IVA_IMPORT_3');
		
		//URL imagenes
		$obsfactusol_img_url = Tools::getShopDomain(true, true).__PS_BASE_URI__.'modules/obsfactusolpro/images/';
		
		$productos = Db::getInstance()->executeS("
			SELECT * FROM `F_ART`, `F_LTA`, `F_FAM`
			WHERE `CODART` = `ARTLTA`
				AND `TARLTA` = ".(int)Configuration::get('OBS_FACTUSOL_TARIFA')."
				AND `FAMART` = `CODFAM`
				AND `CODART` IN ( '".implode("','", array_map("pSQL",$prodReferences))."' )"
		);
		
	
		foreach ($productos as $prod){
	
			//PRECIOS CON IVA EN FACTUSOL - LE RESTAMOS EL IVA
			$precioOrigen = $prod['PRELTA'];
			if(Configuration::get('OBS_FACTUSOL_ORIGEN_CONIVA'))
			{
				$tax = new Tax($iva[$prod['TIVART']]);
				$taxPercent = 1+ ($tax->rate/100);
					
				$precioOrigen = round((float) $precioOrigen/ $taxPercent,6);
				
			}
			$margen = $prod['MARLTA']/100 + 1;
			$precioCoste = round((float) $precioOrigen/ $margen,6);
			
			/*//Buscamos la categoría por nombre
			$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
			$category = Category::searchByName($lang->id, $prod['DESFAM'], false);
			
			if($category)
				$productCategory = $category[0]['id_category'];
			else 
				$productCategory = 'Home';*/
			
			$productCategory = $prod['DESFAM'];
			
			$filedata .=  'ignore'.$sep; 				//ID
			$filedata .=  '0'.$sep; 					//Active (0/1)
			$filedata .= '"'.str_replace('"', '',$prod['DESART']).'"'.$sep;			//Name *
			$filedata .= '"'.str_replace('"', '',$productCategory).'"'.$sep;			//Categories (x,y,z...)
			$filedata .= $precioOrigen.$sep;			//Price tax excluded or Price tax included
			$filedata .= $iva[$prod['TIVART']].$sep;	//Tax rules ID
			$filedata .= $precioCoste.$sep;				//Wholesale price
			$filedata .= '0'.$sep;						//On sale
			$filedata .= ''.$sep;						//Discount amount
			$filedata .= ''.$sep;						//Discount percent
			$filedata .= ''.$sep;						//Discount from
			$filedata .= ''.$sep;						//Discount to
			$filedata .= '"'.str_replace('"', '',$prod['CODART']).'"'.$sep;			//Reference
			$filedata .= ''.$sep;						//Supplier reference #
			$filedata .= ''.$sep;						//Supplier
			$filedata .= ''.$sep;						//Manufacturer
			$filedata .= '"'.$prod['EANART'].'"'.$sep;			//EAN13
			$filedata .= ''.$sep;						//UPC
			$filedata .= ''.$sep;						//Ecotax
			
			//Para versiones 1.6
			if (version_compare(_PS_VERSION_,'1.6','>='))
			{
				$filedata .= ''.$sep;						//Width
				$filedata .= ''.$sep;						//Height
				$filedata .= ''.$sep;						//Depth
			}
			$filedata .= ''.$sep;						//Weight
			$filedata .= $prod['USTART'].$sep;			//Quantity
			
			//Para versiones 1.6
			if (version_compare(_PS_VERSION_,'1.6','>='))
			{
				$filedata .= '1'.$sep;						//Minimal Quantity
				$filedata .= ''.$sep;						//Visibility
				$filedata .= ''.$sep;						//Additional Shipping cost
				$filedata .= ''.$sep;						//Unity
				$filedata .= ''.$sep;						//Unit price rate
			}
			
			$filedata .= '"'.str_replace('"', '&quot;',$prod['DESART']).'"'.$sep;			//Short description
			$filedata .= '"'.str_replace('"', '&quot;',$prod['DEWART']).'"'.$sep;			//Description
			$filedata .= ''.$sep;						//Tags
			$filedata .= '"'.str_replace('"', '&quot;',$prod['DESART']).'"'.$sep;			//Meta title
			$filedata .= ''.$sep;						//Meta keywords
			$filedata .= '"'.str_replace('"', '&quot;',$prod['DESART']).'"'.$sep;			//Meta description
			$filedata .= Tools::str2url($prod['DESART']).$sep;			//URL rewritte
			$filedata .= 'En stock'.$sep;				//Text when in stock
			$filedata .= 'Current supply. Ordering availlable'.$sep;				//Text when backorder allowed
			
			//Para versiones 1.6
			if (version_compare(_PS_VERSION_,'1.6','>='))
			{
				$filedata .= '1'.$sep;						//Available for order (0 = No, 1 = Yes)
				$filedata .= date('Y-m-d').$sep;			//Product available date
				$filedata .= date('Y-m-d').$sep;			//Product creation date
				$filedata .= '1'.$sep;						//Show price (0=No, 1=Yes)
			}
			
			if($prod['IMGART'])
				$filedata .= $obsfactusol_img_url.$prod['IMGART'].$sep;	//Image URLs (x,y,z...)
			else
				$filedata .= ''.$sep;	//Image URLs (x,y,z...)
			
			//Para versiones 1.6
			if (version_compare(_PS_VERSION_,'1.6','>='))
			{
				$filedata .= '0'.$sep;						//Delete existing images (0 = No, 1 = Yes)
				$filedata .= ''.$sep;						//Feature(Name:Value:Position)
				$filedata .= '0'.$sep;						//Available online only (0 = No, 1 = Yes)
			}
			
			//Para versiones 1.6
			if (version_compare(_PS_VERSION_,'1.6','>='))
			{
				$filedata .= 'new'.$sep;					//Condition
				$filedata .= '0'.$sep.'0'.$sep.'0'.$sep.'0'.$sep; 	//Other fields
				$filedata .= '0'.$sep.'0'.$sep.'0'.$sep.'0';	//Other fields
				
			}
			
			$filedata .=  PHP_EOL;
	
		}
		
		file_put_contents($file_path,utf8_decode($filedata));
	}
	
	private static function convert_to_iso($string)
	{
		if(is_callable('mb_convert_encoding'))
			return mb_convert_encoding($string, 'ISO-8859-1');
		else
			return $string;
	}
	
	public static function generateExportFiles($start_product, $end_product)
	{
		
		$warehouse_code = Configuration::get('OBS_FACTUSOL_WAREHOUSE_CODE');
		$min_stock = Configuration::get('OBS_FACTUSOL_MIN_STOCK');
		$max_stock = Configuration::get('OBS_FACTUSOL_MAX_STOCK');
		$lang = Configuration::get('OBS_FACTUSOL_LANG');
		$tariff_code = Configuration::get('OBS_FACTUSOL_EXPORT_TARIFF_CODE');
		
		$art_lines = array();
		$lta_lines = array();
		$sto_lines = array();
		$pro_lines = array();
		$fam_lines = array();
		$sec_lines = array();
		
		$product_category = array();
		$product_supplier = array();
		
		$products = self::getProducts($lang, $start_product, $end_product, 'id_product', 'asc');
		foreach($products as $product)
		{
			//IVAS
			if($product['rate'] == 21)
				$iva = 0;
			else if($product['rate'] == 10)
				$iva = 1;
			else if($product['rate'] == 4)
				$iva = 2;
			//var_dump($products);die;
			$prod = new Product($product['id_product']);
			$attributes = $prod->getAttributeCombinations(1);
			//var_dump($attributes);die;
			if($attributes AND count($attributes))
			{
				foreach($attributes as $attr)
				{
					if($attr['reference'])
					{
						if(!array_key_exists($product['id_product'].'#'.$attr['id_product_attribute'], $art_lines))
						{
							//ARRAY ARTICULOS (ART)
							$art_lines[$product['id_product'].'#'.$attr['id_product_attribute']] = array(
								'codigo' => Tools::substr($attr['reference'], 0, 13),
								'codigo_bar' => Tools::substr($attr['ean13'], 0, 13),
								'codigo_equi' => Tools::substr('', 0, 18),
								'codigo_corto' => Tools::substr(0, 0, 5),
								'familia' => (int) $product['id_category_default'],
								'desc_corta' => Tools::substr(html_entity_decode(strip_tags($product['name'].' '.$attr['group_name'].':'.$attr['attribute_name'])), 0, 50),
								'desc_etiq' => Tools::substr('', 0, 30),
								'desc_tick' => Tools::substr('', 0, 20),
								'proveedor' => (int) $product['id_supplier'],
								'tipo_iva' => $iva,
								'precio_costo' => $attr['wholesale_price'],
								'descuento_1' => 0,
								'descuento_2' => 0,
								'descuento_3' => 0,
								'fecha_alta' => '',
								'max_dto_apl' => 0,
								'ubicacion' => '',
								'unidades_linea' => 0,
								'unidades_bulto' => 0,
								'dimensiones' => '',
								'msj_emergente' => '',
								'observaciones' => '',
								'no_utilizar' => 0,
								'no_imprimir' => 0,
								'art_comp' => 0,
								'camp_prog_1' => '',
								'camp_prog_2' => '',
								'camp_prog_3' => '',
								'ref_proveedor' => Tools::substr($attr['supplier_reference'], 0, 30),
								'desc_larga' => Tools::substr(html_entity_decode(strip_tags($product['description'])), 0, 6500),
								'portes' => 0,
								'cuenta_ventas' => '',
								'cuenta_compras' => '',
								'cant_defecto_sal' => 0,
								'imagen' => '',
								'subir_internet' => 1,
								'desc_web' => Tools::substr($product['description'], 0, 6500),
								'msj_emergente_web' => '',
								'control_stock' => 1,
								'imagen_web' => '',
								'control_stock_web' => 0,
								'ultima_mod' => '',
								'peso' => $product['weight'],
								'cod_fabricante' => $product['id_manufacturer'],
								'art_concatenado' => '',
								'garantia' => '',
								'unidad_medida' => '0',
								'movisol' => '0',
								'desligar' => '0'
							);
							
							//calcular margen
							$margen = 0;
							if($attr['wholesale_price']>0)
								$margen = (($attr['price']/ $attr['wholesale_price'])-1)*100;
														
							//ARRAY TARIFAS (LTA)
							$lta_lines[$product['id_product'].'#'.$attr['id_product_attribute']] = array(
								
									'A_codigo_tarifa' => $tariff_code,
									'B_articulo' => Tools::substr($attr['reference'], 0, 13),
									'C_margen' => $margen,
									'D_precio' => $attr['price']
							);
							
							//ARRAY STOCK (STO)
							$sto_lines[$product['id_product'].'#'.$attr['id_product_attribute']] = array(
							
									'A_codigo_articulo' => Tools::substr($attr['reference'], 0, 13),
									'B_codigo_almacen' => $warehouse_code,
									'C_stock_min' => $min_stock,
									'D_stock_max' => $max_stock,
									'E_stock_actual' => $attr['quantity'],
									'F_stock_actual' => $attr['quantity']
							);
						}
						else{
							$art_lines[$product['id_product'].'#'.$attr['id_product_attribute']]['desc_corta'] .= Tools::substr(html_entity_decode(strip_tags(' '.$attr['group_name'].':'.$attr['attribute_name'])), 0, 50);
						}
					}
						
				}
					
				
			}
			else
			{
				if($product['reference'])
				{
					//ARRAY ARTICULOS (ART)
					$art_lines[$product['id_product']] = array(
							'codigo' => Tools::substr($product['reference'], 0, 13),
							'codigo_bar' => Tools::substr($product['ean13'], 0, 13),
							'codigo_equi' => Tools::substr('', 0, 18),
							'codigo_corto' => Tools::substr(0, 0, 5),
							'familia' => (int) $product['id_category_default'],
							'desc_corta' => Tools::substr(html_entity_decode(strip_tags($product['name'])), 0, 50),
							'desc_etiq' => Tools::substr('', 0, 30),
							'desc_tick' => Tools::substr('', 0, 20),
							'proveedor' => (int) $product['id_supplier'],
							'tipo_iva' => $iva,
							'precio_costo' => $product['wholesale_price'],
							'descuento_1' => 0,
							'descuento_2' => 0,
							'descuento_3' => 0,
							'fecha_alta' => '',
							'max_dto_apl' => 0,
							'ubicacion' => '',
							'unidades_linea' => 0,
							'unidades_bulto' => 0,
							'dimensiones' => '',
							'msj_emergente' => '',
							'observaciones' => '',
							'no_utilizar' => 0,
							'no_imprimir' => 0,
							'art_comp' => 0,
							'camp_prog_1' => '',
							'camp_prog_2' => '',
							'camp_prog_3' => '',
							'ref_proveedor' => Tools::substr($product['supplier_reference'], 0, 30),
							'desc_larga' => Tools::substr(html_entity_decode(strip_tags($product['description'])), 0, 6500),
							'portes' => 0,
							'cuenta_ventas' => '',
							'cuenta_compras' => '',
							'cant_defecto_sal' => 0,
							'imagen' => '',
							'subir_internet' => 1,
							'desc_web' => Tools::substr($product['description'], 0, 6500),
							'msj_emergente_web' => '',
							'control_stock' => 1,
							'imagen_web' => '',
							'control_stock_web' => 2,
							'ultima_mod' => '',
							'peso' => $product['weight'],
							'cod_fabricante' => $product['id_manufacturer'],
							'art_concatenado' => '',
							'garantia' => '',
							'unidad_medida' => 0,
							'movisol' => 0,
							'desligar' => 0
						);
					
					//calcular margen
					$margen = 0;
					if($product['wholesale_price']>0)
						$margen = (($product['price']/ $product['wholesale_price'])-1)*100;
					
					//ARRAY TARIFAS (LTA)
					$lta_lines[$product['id_product']] = array(
			
							'A_codigo_tarifa' => $tariff_code,
							'B_articulo' => Tools::substr($product['reference'], 0, 13),
							'C_margen' => $margen,
							'D_precio' => $product['price']
					);
					
					//ARRAY STOCK (STO)
					$product_stock = StockAvailable::getQuantityAvailableByProduct($product['id_product']);
					$sto_lines[$product['id_product']] = array(
								
							'A_codigo_articulo' => Tools::substr($product['reference'], 0, 13),
							'B_codigo_almacen' => $warehouse_code,
							'C_stock_min' => $min_stock,
							'D_stock_max' => $max_stock,
							'E_stock_actual' => $product_stock,
							'F_stock_actual' => $product_stock
					);
					
				}
			}
			
			//CACHE DE CATEGORÍAS
			if(!array_key_exists($product['id_category_default'], $product_category))
			{
				$product_category[$product['id_category_default']] = new Category($product['id_category_default'], $lang);
			
				//ARRAY FAMILIAS (FAM)
				$fam_lines[$product['id_category_default']] = array(
				
						'A_codigo_fam' => (int) $product['id_category_default'],
						'B_descripcion' => $product_category[$product['id_category_default']]->name,
						'C_seccion' => 'PSS',
						'D_texto_predef' => $product_category[$product['id_category_default']]->name,
						'E_cuenta_compras' => '',
						'F_cuenta_ventas' => '',
						'G_subir_internet' => '1'
							
				);
			}
			//CACHE DE PROVEEDORES
			if($product['id_supplier'])
			{
				if(!array_key_exists($product['id_supplier'], $product_supplier))
				{
					$product_supplier[$product['id_supplier']]['object'] = new Supplier($product['id_supplier'], $lang);
					
					$id_address = Address::getAddressIdBySupplierId($product['id_supplier']);
					
					$address = false;
					$state = false;
					if ($id_address > 0) {
						$address = new Address((int)$id_address);
						$state = new State((int)$address->id_state);
					}
					
					$product_supplier[$product['id_supplier']]['address'] = $address;
					$product_supplier[$product['id_supplier']]['state'] = $state;
				
					$domicilio = $poblacion = $provincia = $codigo_postal = $telefono = '';
					if($product_supplier[$product['id_supplier']]['address'])
					{
						$domicilio = Tools::substr($product_supplier[$product['id_supplier']]['address']->address1.' '.$product_supplier[$product['id_supplier']]['address']->address2,0,100);
						$poblacion = Tools::substr($product_supplier[$product['id_supplier']]['address']->city,0,30);
						$codigo_postal = Tools::substr($product_supplier[$product['id_supplier']]['address']->postcode,0,10);
						$telefono = Tools::substr($product_supplier[$product['id_supplier']]['address']->phone,0,30);
					}
					$provincia = '';
					if($product_supplier[$product['id_supplier']]['state'])
						$provincia = Tools::substr($product_supplier[$product['id_supplier']]['state']->name,0,40);
					
					//ARRAY PROVEEDORES (PRO)
					$pro_lines[$product['id_supplier']] = array(
								
							'A_codigo' => (int) $product['id_supplier'],
							'B_codigo_contab' => (int) $product['id_supplier'],
							'C_tipo' => '0',
							'D_nif' => '',
							'E_nombre_fiscal' => Tools::substr($product_supplier[$product['id_supplier']]['object']->name,0,50),
							'F_nombre_com' => Tools::substr($product_supplier[$product['id_supplier']]['object']->name,0,50),
							'G_domicilio' => $domicilio,
							'H_poblacion' => $poblacion,
							'I_codigo_postal' => $codigo_postal,
							'J_provincia' => $provincia,
							'L_telefono' => $telefono
					
					);
				
				}
			}
			
		}

		//ARRAY SECCION
		$sec_lines = array(
			'A_codigo' => 'PSS',
			'B_descripcion' => 'Productos tienda Prestashop',
			'C_subir_internet' => '1'
		);
		
		self::arrayDataToXLS('ART', $art_lines);
		self::arrayDataToXLS('LTA', $lta_lines);
		self::arrayDataToXLS('STO', $sto_lines);
		self::arrayDataToXLS('TAR', array());
		self::arrayDataToXLS('FAM', $fam_lines);
		self::arrayDataToXLS('PRO', $pro_lines);
		self::arrayDataToXLS('SEC', $sec_lines);
		
		//ALL FILES ZIPPED
		$zip = new ZipArchive();
		$res = $zip->open(dirname(__FILE__)."/../export/export_factusol.zip", ZipArchive::CREATE);
		if ($res === TRUE) {
			$zip->addFile(dirname(__FILE__)."/../temp/ART.xls", 'ART.xls');
			$zip->addFile(dirname(__FILE__)."/../temp/LTA.xls", 'LTA.xls');
			$zip->addFile(dirname(__FILE__)."/../temp/STO.xls", 'STO.xls');
			$zip->addFile(dirname(__FILE__)."/../temp/TAR.xls", 'TAR.xls');
			$zip->addFile(dirname(__FILE__)."/../temp/FAM.xls", 'FAM.xls');
			$zip->addFile(dirname(__FILE__)."/../temp/PRO.xls", 'PRO.xls');
			$zip->addFile(dirname(__FILE__)."/../temp/SEC.xls", 'SEC.xls');
			$zip->close();
				
		}
	}
	
	private static function arrayDataToXLS($filename, $array_data)
	{
		$objPHPExcel = new PHPExcel();
		
		$objPHPExcel->getProperties()->setCreator("Maarten Balliauw");
		$objPHPExcel->getProperties()->setLastModifiedBy("Maarten Balliauw");
		$objPHPExcel->getProperties()->setTitle("Office Test Document");
		$objPHPExcel->getProperties()->setSubject("Office Test Document");
		$objPHPExcel->getProperties()->setDescription("Test document for Office, generated using PHP classes.");
		$objPHPExcel->getProperties()->setKeywords("office php");
		$objPHPExcel->getProperties()->setCategory("Test result file");
		
		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet()->setTitle('FactuSOL');
		
		if($filename == 'ART' OR $filename == 'STO')
			$objPHPExcel->getActiveSheet()->fromArray($array_data, NULL, 'A1', null, array('A'));
		else if($filename == 'LTA')
			$objPHPExcel->getActiveSheet()->fromArray($array_data, NULL, 'A1', null, array('B'));
		else
			$objPHPExcel->getActiveSheet()->fromArray($array_data, NULL, 'A1');
		
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save(dirname(__FILE__)."/../temp/".$filename.".xls");
		
	}
	
	/**
	 * Get all available products
	 *
	 * @param int $id_lang Language id
	 * @param int $start Start number
	 * @param int $limit Number of products to return
	 * @param string $order_by Field for ordering
	 * @param string $order_way Way for ordering (ASC or DESC)
	 * @return array Products details
	 */
	private static function getProducts($id_lang, $from_id, $to_id, $order_by, $order_way, $id_category = false,
			$only_active = false, Context $context = null)
	{
		if (!$context) {
			$context = Context::getContext();
		}
	
		$front = true;
		if (!in_array($context->controller->controller_type, array('front', 'modulefront'))) {
			$front = false;
		}
	
		if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way)) {
			die(Tools::displayError());
		}
		if ($order_by == 'id_product' || $order_by == 'price' || $order_by == 'date_add' || $order_by == 'date_upd') {
			$order_by_prefix = 'p';
		} elseif ($order_by == 'name') {
			$order_by_prefix = 'pl';
		} elseif ($order_by == 'position') {
			$order_by_prefix = 'c';
		}
	
		if (strpos($order_by, '.') > 0) {
			$order_by = explode('.', $order_by);
			$order_by_prefix = $order_by[0];
			$order_by = $order_by[1];
		}
		$sql = 'SELECT p.*, product_shop.*, pl.* , m.`name` AS manufacturer_name, s.`name` AS supplier_name
				FROM `'._DB_PREFIX_.'product` p
				'.Shop::addSqlAssociation('product', 'p').'
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product` '.Shop::addSqlRestrictionOnLang('pl').')
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
				LEFT JOIN `'._DB_PREFIX_.'supplier` s ON (s.`id_supplier` = p.`id_supplier`)'.
					($id_category ? 'LEFT JOIN `'._DB_PREFIX_.'category_product` c ON (c.`id_product` = p.`id_product`)' : '').'
				WHERE 
					p.`id_product` >= '.(int) $from_id.' AND p.`id_product` <= '.(int) $to_id.'
					AND pl.`id_lang` = '.(int)$id_lang.
					($id_category ? ' AND c.`id_category` = '.(int)$id_category : '').
					($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '').
					($only_active ? ' AND product_shop.`active` = 1' : '').'
				ORDER BY '.(isset($order_by_prefix) ? pSQL($order_by_prefix).'.' : '').'`'.pSQL($order_by).'` '.pSQL($order_way);
		$rq = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
		if ($order_by == 'price') {
			Tools::orderbyPrice($rq, $order_way);
		}
	
		foreach ($rq as &$row) {
			$row = Product::getTaxesInformations($row);
		}
	
		return ($rq);
	}
	
}
?>