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

class OrderLog extends ObjectModel{
	
	
    public static function insertOrderLog($orderId){
    	
    	$sql = "	INSERT INTO `"._DB_PREFIX_."obsfactusolpro_order_log`
						(`order_id`, `date_add`)
					VALUES
						(".(int)$orderId.",
						NOW())
		
					";
    	
    	return Db::getInstance()->Execute($sql);
    }
    
    public static function deleteOrderLog($orderId){
    	 
    	$sql = "	DELETE FROM `"._DB_PREFIX_."obsfactusolpro_order_log`
						WHERE `order_id` = ".(int)$orderId."
					";
    	 
    	return Db::getInstance()->Execute($sql);
    }
    
    public static function existsOrderLog($orderId){
    	 
    	$sql = "	SELECT * FROM `"._DB_PREFIX_."obsfactusolpro_order_log`
					WHERE `order_id` = ".(int)$orderId."
    
					";
    	 
    	return Db::getInstance()->getRow($sql);
    }
    
}

?>