<?php

include_once 'PHPExcel/IOFactory.php';

class Excel {
	
	private $excelObject;
	private $currentRow;
	
	public function __construct($filename, $hoja = 0) {
		try {
			$this->excelObject = PHPExcel_IOFactory::load($filename);
			$this->excelObject->setActiveSheetIndex($hoja);
			$this->currentRow = 0;
		} catch(Exception $e) {
			Tools::displayError('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
		}
	}
	
	public function getRow() {
		$sheet = $this->excelObject->getActiveSheet();
		
		$this->currentRow++;
		
		$maxCol = PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());
		$maxRow = $sheet->getHighestRow();
		if($maxCol == 0 OR $this->currentRow > $maxRow)
			return false;
		
		$row = array();
		for($currentCol=0;$currentCol<$maxCol;$currentCol++) {
			$cell = $sheet->getCellByColumnAndRow($currentCol, $this->currentRow);
			if ($cell->getValue() instanceof PHPExcel_RichText)
				$value = $cell->getValue()->getPlainText();
			else
				$value = $cell->getCalculatedValue();
				
			//$style = $this->excelObject->getCellXfByIndex($cell->getXfIndex());
			//$value = PHPExcel_Style_NumberFormat::toFormattedString($value, $style->getNumberFormat()->getFormatCode());
			$row[] = $value;
		}
		
		return $row;
	}
	
	public function toArray() {
		return $this->excelObject->getActiveSheet()->toArray(null,true,true,true);
	}
	
}