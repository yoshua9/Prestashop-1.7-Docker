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

class OBSLog{
	
	public $dirname;
	public $filename;
	
	public function __construct($filename = 'log.log', $dirname = 'logs/')
	{
		$this->dirname = $dirname;
		$this->filename = $filename;
	}
	
	public function mensaje($mensaje,$level){
		echo $mensaje."<br/>";
    	$archivo = $this->dirname.$this->filename;
        $manejador = fopen($archivo,'a');
        fwrite($manejador,"[".date("r")."] -> $level: $mensaje\n");
        fclose($manejador);
    }
	public function error($mensaje){
	        $level='ERROR';
	        $this->mensaje($mensaje,$level);
	}
    public function info($mensaje){
        $level='INFO';
        $this->mensaje($mensaje,$level);
    }
    public function clean()
    {
    	foreach (glob(dirname(__FILE__)."/".$this->dirname."*.log") as $nombre_archivo) {
    		if (file_exists($nombre_archivo)) {
    			$unMes = 30*24*60*60;
    			$timeToDelete = time()-$unMes;
    			$filetime = filemtime($nombre_archivo);
    			
    			if($filetime <= $timeToDelete)
    				unlink($nombre_archivo);
			    
			}
	    }
    	
    }
    
}

?>