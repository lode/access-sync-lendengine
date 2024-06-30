<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\service;

use Symfony\Component\Console\Output\OutputInterface;

class ConvertCsvService {
	public function requireInputCsvs(string $fileDirectory, array $csvFileNames, OutputInterface $output): void
	{
		$fileDirectory = str_ends_with($fileDirectory, '/') ? $fileDirectory : $fileDirectory.'/';
		
		$found = [];
		$missing = [];
		foreach ($csvFileNames as $csvFileName) {
			if (file_exists($fileDirectory.$csvFileName) === false) {
				$missing[] = $csvFileName;
			}
			else {
				$found[] = $csvFileName;
			}
		}
		
		if ($found !== []) {
			$output->writeln('<info>Found for importing: '.implode(', ', $found).'</info>');
		}
		if ($missing !== []) {
			$output->writeln('<error>Missing for importing: '.implode(', ', $missing).'</error>');
			$output->writeln('<comment>Add those files in `data/`</comment>');
			$output->writeln('');
			
			throw new \Exception('missing files for importing');
		}
	}
	
	public function getExportCsv(string $csvFilePath, array $expectedHeaders, string $csvSeparator = ','): array
	{
		if (file_exists($csvFilePath) === false) {
			throw new \Exception(basename($csvFilePath) . ' does not exists');
		}
		
		$fileHandler = fopen($csvFilePath, 'r');
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
	
	public function trimFieldValue(string|array $field): string|array
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
