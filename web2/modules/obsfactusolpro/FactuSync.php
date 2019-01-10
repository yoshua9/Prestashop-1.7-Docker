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

include(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/classes/Factusol.php' );
include('OBSLog.php');
set_time_limit(0);

//@ini_set('display_errors', 'on');	

define( 'IMPORT_SQLFILE',	dirname(__FILE__).'/import/factusolweb.sql' );

define( 'FS_TABLE_ARTS',			'F_ART' );
define( 'FS_TABLE_RATES',			'F_LTA' );

define( 'FS_DISABLE', (int) Configuration::get( 'OBS_FACTUSOL_DISABLE' ) );// Indica si los productos que no estan en factusol se deben marcar como no disponibles
define( 'FS_UPDATE_STOCK', (int) Configuration::get( 'OBS_FACTUSOL_STOCK' ) );	// Indica si se debe actualizar el stock de los productos
define( 'FS_UPDATE_PRICE', (int) Configuration::get( 'OBS_FACTUSOL_PRICE' ) );	// Indica si se debe actualizar los precios de los productos
define( 'FS_TARIFA', (int) Configuration::get( 'OBS_FACTUSOL_TARIFA' ) );	// Indica la tarifa escogida

class FactuSync
{
	
	private $db			= null;
	private $data		= null;
	private $log = null;
	private $timing = null;
	private $iniTime = null;
	
	public function Synchronize( )
	{
		
		echo '<html><body style="font-family:arial; font-size: 14px; line-height: 20px">';
		echo '<div style="padding:20px">';
		echo '<img src="views/img/sync.jpg"><br/><br/>';
		
		$this->log = new OBSLog();
		
		$this->log->info( '<b>Importando datos desde Factusol</b>' );
		
		try
		{
			
			$this->getTime();
			
			// Leemos el script SQL
			$this->log->info( '<span style="color:blue">Leyendo el fichero de importación SQL...</span>' );
			$this->ReadSqlFile();
			print('<span style="color:green">OK en '.$this->getTime().'</span><br/>');
			echo '<br/>';
			
			// Ejecutamos script SQL
			$this->log->info( '<span style="color:blue">Ejecutando script SQL...</span>' );
			$this->ParseSql();
			print('<span style="color:green">OK en '.$this->getTime().'</span><br/>');
			echo '<br/>';
			
			// Realizar cambios en los productos Prestashop
			$this->log->info( '<span style="color:blue">Aplicando los cambios en los productos...</span>' );
			$this->ApplyChanges();
			print('<span style="color:green">OK en '.$this->getTime().'</span><br/>');
			echo '<br/>';
			
			// Comprobamos si hay productos nuevos
			$this->log->info( '<span style="color:blue">Comprobando productos nuevos...</span>' );
			$this->GetNewProducts();
			print('<span style="color:green">OK en '.$this->getTime().'</span><br/>');
			echo '<br/>';
			
			// Borramos tablas de factusol
			$this->log->info( '<span style="color:blue">Borrando tablas temporales...</span>' );
			$this->deleteTables();
			print('<span style="color:green">OK en '.$this->getTime().'</span><br/>');
			echo '<br/>';
			
			//FIN
			$this->log->error('<span style="color:blue">Proceso finalizado.</span>');
			
			echo '</div></body></html>';
		}
		catch( Exception $e )
		{
			$this->log->error( $e->getMessage( ));
		}	
	}
		
	private function ReadSqlFile( )
	{
		// Abrimos el fichero para leer
		$f = fopen( IMPORT_SQLFILE, 'r' );
		if( ! $f ) 
			throw new Exception( 'File open error' );
		
		// Cargamos el contenido del fichero
		$this->data = fread( $f, filesize( IMPORT_SQLFILE ) );
		
		// Cerramos el archivo
		fclose( $f );
		
	}
	
	private function ParseSql( )
	{
		
		$sqls = explode(";\r\n", $this->data);
		$sql_count = $sql_ok = 0;
		foreach($sqls as $sql) {
			
			//SE IGNORA LA TABLA DE CLI
			if(preg_match('/INSERT HIGH_PRIORITY INTO F_CLI/', $sql))
				continue;
			
			$sql = str_replace( "`PREC3PCL` Decimal(12,4) NOT NULL, `PREC3PCL` Decimal(12,4) NOT NULL", 
								"`PREC3PCL` Decimal(12,4) NOT NULL", $sql );
			$sql_count++;
			
			$sql = mb_convert_encoding($sql, 'UTF-8', 'ISO-8859-1');
			
			if( $sql AND !Db::getInstance()->execute($sql) )
				$this->log->error( 'Error ejecutando la instrucciÃ³n: '.$sql );
			else $sql_ok++;
		}
		
		$this->log->info( "Se han ejecutado correctamente $sql_ok de $sql_count instrucciones");
	}
	
	private function ApplyChanges()
	{
		
		$products = $this->getProducts();
		
		$total_products = 0;
		$total_combis = 0;
		$total_products_ok = 0;
		$total_combis_ok = 0;
		
		$toBeDisabled = array();

		foreach ($products as $product)
		{
			
			//CONTABILIZAMOS
			if($product['CODART'] != null AND $product['id_product_attribute'] AND $product['attr_reference'] == $product['CODART'] )
				$total_combis++;				
			else if($product['CODART'] != null AND $product['reference'] == $product['CODART'])
				$total_products++;
			else 
				$toBeDisabled[$product['id_product']] = $product;
			
			//ACTUALIZAMOS	
			if( $product['CODART'] != null AND $this->dbProductUpdate($product))
			{
				//CONTABILIZAMOS
				if($product['id_product_attribute'] AND $product['attr_reference'] == $product['CODART'] )
					$total_combis_ok++;
				else if($product['reference'] == $product['CODART'])
					$total_products_ok++;
				
			}
			
		}
		
		//DESACTIVAMOS (SI TOCA)
		if(FS_DISABLE)
			$this->disableProducts($toBeDisabled);
		
		$this->log->info( 'Se han obtenido '.$total_products.' productos y '.$total_combis.' combinaciones a actualizar' );	
		$this->log->info( "Se han actualizado correctamente $total_products_ok de $total_products productos y $total_combis_ok de $total_combis combianciones");
	}
	
	private function getProducts()
	{
		
		$sql = 'SELECT p.id_product,  IFNULL(a.id_product_attribute, 0) as id_product_attribute, p.reference, a.reference as attr_reference, p.price, p.active, p.available_for_order, p.quantity, 
				a.id_product_attribute, SUM(a.quantity) as quantity_comb,
				fa.CODART, fa.USTART, fa.TIVART,
				fr.PRELTA, fr.TARLTA
				FROM '._DB_PREFIX_.'product p 
				LEFT JOIN '._DB_PREFIX_.'product_attribute a 
				 ON( a.id_product = p.id_product)
				'.(FS_DISABLE?'LEFT':'INNER').' JOIN '.FS_TABLE_ARTS.' fa
				 ON ( fa.CODART = IFNULL(IF(a.reference = \'\',NULL,a.reference), p.reference) )
				LEFT JOIN '.FS_TABLE_RATES.' fr 
				 ON ( (fr.ARTLTA = (IFNULL(IF(a.reference = \'\',NULL,a.reference), p.reference))) AND fr.TARLTA = \''.FS_TARIFA.'\' )
				GROUP BY p.reference, a.reference
				ORDER BY p.reference' ;
		
		$result = Db::getInstance()->ExecuteS($sql);

		return $result;
		
	}
	
	private function disableProducts($productsToDisable)
	{
		foreach($productsToDisable as $product)
		{
			$logbase = 	'Producto '.str_pad( $product['reference'], 10 );
			
			$log = $logbase.str_pad( ' Desactivando producto', 30 );
			$ok  = $this->disableProductDB( $product['id_product'] );
			// Guardamos el resultado en el log	 
			$log .= $ok ? '[OK]   ' : '[ERROR]';	
			$this->log->info( $log );

		}
		return true;
	}
	
	private function dbProductUpdate($info) {
		
		if (array_key_exists('id_product', $info) AND (int)($info['id_product']) /*AND Product::existsInDatabase((int)($info['id_product']), 'product')*/)
		{
			$product = new Product((int)($info['id_product']));
			
			//SI EL PRECIO ORIGEN ES CONIVA CALCULAMOS EL IMPORTE SIN IVA
			if(Configuration::get('OBS_FACTUSOL_ORIGEN_CONIVA'))
			{
				$tax = (float) Tax::getProductTaxRate((int)($info['id_product']));
				$taxPercent = 1+ ($tax/100);
			
				$info['PRELTA'] = round((float) $info['PRELTA']/ $taxPercent,6);
			}
		}
		else
			return false;
		
		$res = false;
		
		Context::getContext()->employee = 1;
		Context::getContext()->link = new Link();
			
		//SI EL PRODUCTO ESTA EN FACTUSOL (CODART != NULL) Y LA REFRENCIA CONCIDE CON LA REFERENCIA DE LA COMBINACION - LO TRATAMOS COMO COMBINACION
		if($info['id_product_attribute'] AND $info['CODART'] == $info['attr_reference']){
				
				//Recuperamos los datos de la combinacion que no queremos perder
				$combination = new Combination($info['id_product_attribute']);
		
				if( FS_UPDATE_PRICE ){
					$res = $product->updateAttribute($info['id_product_attribute'], $combination->wholesale_price, $info['PRELTA'], $combination->weight, $combination->unit_price_impact, $combination->ecotax,
							null, $info['attr_reference'], $combination->ean13, $combination->default_on, $combination->location, $combination->upc, $combination->minimal_quantity,
							$combination->available_date, false, array());
				}
				if( FS_UPDATE_STOCK )
					StockAvailable::setQuantity($product->id, $info['id_product_attribute'], (int) $info['USTART']);
			
		
		}
		
		//SI EL PRODUCTO ESTA EN FACTUSOL (CODART != NULL) Y LA REFRENCIA CONCIDE CON LA REFERENCIA DEL PRODUCTO - LO TRATAMOS COMO PRODUCTO
		else if($info['CODART'] == $info['reference'])
		{	
			if( FS_UPDATE_STOCK )
				$product->quantity = (int) $info['USTART'];
			
			if( FS_UPDATE_PRICE )
				$product->price = $info['PRELTA'];
			
			$fieldError = $product->validateFields(false, true);
			if ($fieldError === true)
			{
				// check quantity
				if ($product->quantity == NULL)
					$product->quantity = 0;
					
				// If id product AND id product already in base, trying to update
				if ($product->id /*AND Product::existsInDatabase((int)($product->id),'product')*/)
				{
				
					$product->setFieldsToUpdate(array('quantity' => true, 'price' => true));
					
					$res = $product->update();
					
					if(version_compare(_PS_VERSION_,'1.5','>=')){
						StockAvailable::setQuantity($product->id, 0, $product->quantity);
					}
				}
			}
		}
		
		return $res;
			
	}
		
	private function disableProductDB($id_product)
	{
		$sql = 'UPDATE '._DB_PREFIX_.'product SET active = 0 WHERE id_product = '.(int) $id_product;
		$result = Db::getInstance()->Execute($sql);
		
		if(version_compare(_PS_VERSION_,'1.5','>=')){
			$sql = 'UPDATE '._DB_PREFIX_.'product_shop SET active = 0 WHERE id_product = '.(int) $id_product;
			$result &= Db::getInstance()->Execute($sql);
		}
		return $result;
	}
	
	private function getNewProducts()
	{		
		$sql = 'SELECT fa.CODART, fa.DESART, fa.FAMART, fa.USTART, fa.TIVART, fr.PRELTA, p.id_product, p.reference, p.price, p.active, p.quantity 
				FROM '.FS_TABLE_ARTS.' fa
				LEFT JOIN '._DB_PREFIX_.'product p ON ( fa.CODART = p.reference) 
				LEFT JOIN '._DB_PREFIX_.'product_attribute a ON ( fa.CODART = a.reference)
				LEFT JOIN '.FS_TABLE_RATES.' fr ON ( fa.CODART = fr.ARTLTA AND fr.TARLTA =  \''.FS_TARIFA.'\' ) 
				WHERE fa.CODART !=  \'\'
				AND p.id_product IS NULL AND a.id_product_attribute IS NULL';
		
		$result = Db::getInstance()->ExecuteS($sql);
		
		$new_products = count($result);
		
		if($result AND $new_products){
			
			$this->log->info( 'Hay '.$new_products.' productos o combinaciones de producto nuevos a importar.' );
			
			$categoriesIds = array();
			$productsIds = array();
		
			echo '<ul>';
			
			foreach ($result as $product) 
			{	
				$this->log->info( '<li><em>' . str_pad( $product['CODART'], 10 ).' - '.str_pad( $product['DESART'], 50 ) .
				' ( Precio: '.str_pad( $product['PRELTA'], 10 ).' - Stock: '.$product['USTART'].' )</em></li>' );
				
				$categoriesIds[$product['FAMART']] = $product['FAMART'];
				$productsIds[$product['CODART']] = $product['CODART'];
				
			}
			echo '</ul>';
			
			if(Configuration::get( 'OBS_FACTUSOL_IMPORTCSV' )){
				
				Factusol::createCategoriesImportFile($categoriesIds);
				Factusol::createProductsImportFile($productsIds);
				
				$this->log->info( 'Se han generado ficheros CSV de importación de categorías y productos nuevos, para importarlos acceda a la pestaña FactuSOL del Back-Office de su tienda y siga las instrucciones.' );
			}
		}
		else
			$this->log->info( 'No hay productos o combinaciones de producto nuevos a importar.' );
	}
	
	private function deleteTables()
	{		
		$sql = 'DROP TABLE IF EXISTS `F_AGE`, `F_ALM`, `F_ART`, `F_AUT`, `F_CFG`, `F_CLI`, `F_DES`, `F_DIR`, `F_EMP`, `F_FAC`, `F_FAM`, `F_FPA`, `F_LFA`, `F_LTA`, `F_SEC`, `F_STO`, `F_TAR`, `F_LPC`, `F_PCL`';
	
		$result = Db::getInstance()->Execute($sql);
		
		return $result;
	}
	
	private function getTime()
	{
		$mtime = microtime();
		$mtime = explode(" ",$mtime);
		
		$result = 0;
		$antResult = 0;
		
		if($this->iniTime == null){
			$this->iniTime = $mtime[1] + $mtime[0];
			
		}
		else{ 
			$antResult = $this->timing;
			$result = $mtime[1] + $mtime[0] - $this->iniTime;
			$this->timing = $result;
			
		}
		
		return round($result-$antResult,3).' segundos';
	}
	
}

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/strict.dtd">
	  <html>';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';

$factuSync = new FactuSync();

if(Tools::getValue('token') == Configuration::get( 'OBS_FACTUSOL_TOKEN' ))
	$factuSync->Synchronize();
else 
	echo "FactuSync: Forbidden You don't have permission to access";

echo '</body>';
echo '</html>';

?>