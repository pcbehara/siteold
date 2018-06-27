<?php

/**
 * @package            Joomla
 * @subpackage         Membership Pro
 * @author             Tuan Pham Ngoc
 * @copyright          Copyright (C) 2010 - 2016 Ossolution Team
 * @license            GNU/GPL, see LICENSE.php
 */
class OSMembershipHelperData
{
	/**
	 * Get data from excel file using PHPExcel library
	 *
	 * @param $file
	 *
	 * @return array
	 */
	public static function getDataFromFile($file)
	{
		require_once JPATH_ROOT . '/libraries/osphpexcel/PHPExcel.php';

		$data = array();

		$reader = PHPExcel_IOFactory::load($file);

		if ($reader instanceof PHPExcel_Reader_CSV)
		{
			$reader->setDelimiter(',');
		}

		$rows = $reader->getActiveSheet()->toArray(null, true, true, true);

		if (count($rows) > 1)
		{
			for ($i = 2, $n = count($rows); $i <= $n; $i++)
			{
				$row = array();

				foreach ($rows[1] as $key => $fieldName)
				{
					$fieldName       = trim($fieldName);
					$row[$fieldName] = $rows[$i][$key];
				}

				$data[] = $row;
			}
		}

		return $data;
	}

	/**
	 * Export the given data to Excel
	 *
	 * @param array  $fields
	 * @param array  $rows
	 * @param string $filename
	 * @param array  $headers
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function excelExport($fields, $rows, $filename, $headers = array())
	{
		$config   = OSMembershipHelper::getConfig();
		$fileType = empty($config->export_data_format) ? 'xlsx' : $config->export_data_format;

		if ($fileType == 'csv')
		{
			static::csvExport($fields, $rows, $filename, $headers);

			return;
		}

		require_once JPATH_ROOT . '/libraries/osphpexcel/PHPExcel.php';

		$exporter    = new PHPExcel();
		$user        = JFactory::getUser();
		$createdDate = JFactory::getDate('now', JFactory::getConfig()->get('offset'))->format('Y-m-d');

		//Set properties Excel
		$exporter->getProperties()
			->setCreator($user->name)
			->setLastModifiedBy($user->name);

		//Set some styles and layout for Excel file
		$borderedCenter = new PHPExcel_Style();
		$borderedCenter->applyFromArray(
			array(
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
					'vertical'   => PHPExcel_Style_Alignment::VERTICAL_CENTER,
				),
				'font'      => array(
					'name' => 'Times New Roman', 'bold' => false, 'italic' => false, 'size' => 11
				),
				'borders'   => array(
					'bottom' => array('style' => PHPExcel_Style_Border::BORDER_THIN),
					'right'  => array('style' => PHPExcel_Style_Border::BORDER_THIN),
					'top'    => array('style' => PHPExcel_Style_Border::BORDER_THIN),
					'left'   => array('style' => PHPExcel_Style_Border::BORDER_THIN),
				)
			)
		);

		$borderedLeft = new PHPExcel_Style();
		$borderedLeft->applyFromArray(
			array(
				'alignment' => array(
					'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
				),
				'font'      => array(
					'name' => 'Times New Roman', 'bold' => false, 'italic' => false, 'size' => 11
				),
				'borders'   => array(
					'bottom' => array('style' => PHPExcel_Style_Border::BORDER_THIN),
					'right'  => array('style' => PHPExcel_Style_Border::BORDER_THIN),
					'top'    => array('style' => PHPExcel_Style_Border::BORDER_THIN),
					'left'   => array('style' => PHPExcel_Style_Border::BORDER_THIN),
				)
			)
		);

		$sheet    = $exporter->setActiveSheetIndex(0);
		$column   = 'A';
		$rowIndex = '1';

		if (empty($headers))
		{
			$headers = $fields;
		}

		foreach ($headers as $header)
		{
			$sheet->setCellValue($column . $rowIndex, $header);
			$sheet->getColumnDimension($column)->setAutoSize(true);
			$column++;
		}

		$rowIndex++;

		foreach ($rows as $row)
		{
			$column = 'A';

			foreach ($fields as $field)
			{
				$cellData = isset($row->{$field}) ? $row->{$field} : '';
				$sheet->setCellValue($column . $rowIndex, $cellData);
				$sheet->getColumnDimension($column)->setAutoSize(true);
				$column++;
			}

			$rowIndex++;
		}

		switch ($fileType)
		{
			case 'csv' :
				$writer = 'CSV';
				break;
			case 'xls' :
				$writer = 'Excel5';
				break;
			case 'xlsx' :
				$writer = 'Excel2007';
				break;
			default :
				$writer = 'Excel2007';
				break;
		}

		header('Content-Type: application/vnd.ms-exporter');
		header('Content-Disposition: attachment;filename=' . $filename . '_on_' . $createdDate . '.' . $fileType);
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($exporter, $writer);

		if ($fileType == 'csv')
		{
			/* @var PHPExcel_Writer_CSV $objWriter */
			$objWriter->setDelimiter(',');
		}

		$objWriter->save('php://output');

		JFactory::getApplication()->close();
	}

	/**
	 * Export the given data to CSV, this method will be used as a backup in case we have performance issue with PHPExcel on large dataset
	 *
	 * @param array  $fields
	 * @param array  $rows
	 * @param string $filename
	 * @param array  $headers
	 *
	 * @return void
	 */
	public static function csvExport($fields, $rows, $filename, $headers = array())
	{
		$browser   = JFactory::getApplication()->client->browser;
		$mime_type = ($browser == JApplicationWebClient::IE || $browser == JApplicationWebClient::OPERA) ? 'application/octetstream' : 'application/octet-stream';

		$createdDate = JFactory::getDate('now', JFactory::getConfig()->get('offset'))->format('Y-m-d');
		$filename    = $filename . '_on_' . $createdDate;

		header('Content-Encoding: UTF-8');
		header('Content-Type: ' . $mime_type . ' ;charset=UTF-8');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		if ($browser == JApplicationWebClient::IE)
		{
			header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
		}
		else
		{
			header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
			header('Pragma: no-cache');
		}

		$fp = fopen('php://output', 'w');
		fwrite($fp, "\xEF\xBB\xBF");

		if (empty($headers))
		{
			$headers = $fields;
		}

		fputcsv($fp, $headers, ',');

		foreach ($rows as $row)
		{
			$values = array();
			foreach ($fields as $field)
			{
				$values[] = isset($row->{$field}) ? $row->{$field} : '';
			}
			fputcsv($fp, $values, ',');
		}

		fclose($fp);

		JFactory::getApplication()->close();
	}
}