/*
* 
*  2011-2014 OBSolutions S.C.P.  
*  All Rights Reserved.
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
*  @author OBSolutions SCP <http://addons.prestashop.com/en/65_obs-solutions>
*  @copyright  2011-2014 OBSolutions SCP
*  @license    OBSolutions S.C.P. All Rights Reserved
*  International Registered Trademark & Property of OBSolutions SCP
* 
*/
function saveFileToImportDir(type)
{
	
	var Url = window.location+"&ajax";
	var Params = "type="+type;
	
	$.ajax({
		type: 'POST',
		headers: { "cache-control": "no-cache" },
		url: Url,
		async: true,
		cache: false,
		data: Params,
		success: function(RespTxt)
		{
			 if(RespTxt != 'KO'){
			     
				 if(type == 'categories'){
					 $("#csv").val('FactuSOL_categories.csv');
					 $("#entity").val('0');
				 }
				 else{
					 $("#csv").val('FactuSOL_products.csv');
					 $("#entity").val('1');
				 }
				 return $("#importCSV").submit();
				 
			  }
			 else{
				 alert("Se ha producido un error al mover el fichero a la carpeta de importación.");
			 }
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			alert("TECHNICAL ERROR: \n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus);
		}
	});
	
	return false;

}

function deleteExportFiles(type)
{
	var tipo = '';
	
	if(type == 'C')
		tipo = 'Clientes';
	else
		tipo = 'Pedidos';
	
	if(confirm("¿Está seguro que desea borrar los ficheros de "+tipo+" a exportar?"))
	{
		$('#factusolFileType').val(type);
		return $("#deleteExportFilesForm").submit();
	}
	else return false;

}
