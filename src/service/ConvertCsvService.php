<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\service;

class ConvertCsvService {
	public function getExportCsv(string $csvFileName, array $expectedHeaders, string $csvSeparator = ','): array
	{
	    if (file_exists($csvFileName) === false) {
	        throw new \Exception(basename($csvFileName) . ' does not exists');
	    }
	
	    $fileHandler = fopen($csvFileName, 'r');
	    $csvLines = [];
	    do {
	        $csvLine = fgetcsv($fileHandler, 0, $csvSeparator);
	        if ($csvLine === false) {
	            break;
	        }
	
	        $csvLines[] = $csvLine;
	    } while (true);
	    fclose($fileHandler);
	
	    if ($csvLines === [] || count($csvLines) === 1) {
	        throw new \Exception('Empty csv found');
	    }
	    echo 'Found '.(count($csvLines) - 1).' rows'.PHP_EOL;
	
	    $csvHeaders = array_shift($csvLines);
	    if ($csvHeaders !== $expectedHeaders) {
	        throw new \Exception('CSV has a different format than expected');
	    }
	
	    $csvBody = [];
	    foreach ($csvLines as $index => $csvLine) {
	        if (count($csvLine) !== count($csvHeaders)) {
	            throw new \Exception('error reading #'.$index.': '.json_encode($csvLine));
	        }
	
	        foreach ($csvLine as &$csvField) {
	            $csvField = $this->trimFieldValue($csvField);
	        }
	        unset($csvField);
	
	        $csvBody[] = array_combine($csvHeaders, $csvLine);
	    }
	
	    return $csvBody;
	}
	
	/**
	 * @param  string|array<string> $field
	 * @return string|array<string>
	 */
	public function trimFieldValue($field)
	{
	    if (is_array($field)) {
	        foreach ($field as &$subField) {
	            $subField = $this->trimFieldValue($subField);
	        }
	        unset($subField);
	    }
	    else {
	        $field = trim($field);
	    }
	
	    return $field;
	}
	
	public function convertFieldToAmount(string $field): string
	{
	    return str_replace(',', '.', $field);
	}
	
	public function convertFieldToArray(string $field): array
	{
	    return explode(',', $field);
	}
	
	public function createImportCsv(array $csvBody): string
	{
	    $csvHeaders = array_keys($csvBody[0]);
	
	    $fileHandler = fopen('php://temp', 'rw');
	    fputcsv($fileHandler, $csvHeaders, $delimiter = "\t");
	    foreach ($csvBody as $csvRow) {
	        fputcsv($fileHandler, $csvRow, $delimiter = "\t");
	    }
	
	    rewind($fileHandler);
	    $csvContents = stream_get_contents($fileHandler);
	    fclose($fileHandler);
	
	    return $csvContents;
	}
}
