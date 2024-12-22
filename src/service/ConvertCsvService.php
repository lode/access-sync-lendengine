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
	
	public function createExportSqls(OutputInterface $output, string $dataDirectory, string $fileName, array $queries, string $description): void
	{
		$importConfig = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;';
		
		$limit = 2500;
		if (count($queries) > $limit) {
			$output->writeln('<info>Done. ' . count($queries) . ' SQLs for '.$description.' stored in:</info>');
			
			// manually chunk to make sure `SET @variable` statements are not split
			$index = 0;
			$chunks = [$index => []];
			$hasSetQueries = false;
			$isSetQuery = false;
			foreach ($queries as $query) {
				$isSetQuery = str_contains($query, 'SET @');
				$hasSetQueries = $hasSetQueries || $isSetQuery;
				
				$reachedLimit = count($chunks[$index]) > $limit;
				$canSplitNow = ($hasSetQueries === false || $isSetQuery === true);
				if ($reachedLimit === true && $canSplitNow === true) {
					$index++;
				}
				
				if (isset($chunks[$index]) === false) {
					$chunks[$index] = [];
				}
				
				$chunks[$index][] = $query;
			}
			
			foreach ($chunks as $index => $chunk) {
				$convertedFileName = 'LendEngine_'.$fileName.'_'.time().'_chunk_'.($index+1).'.sql';
				file_put_contents($dataDirectory.'/'.$convertedFileName, $importConfig.PHP_EOL.implode(PHP_EOL, $chunk));
				
				$output->writeln('- '.$convertedFileName);
			}
		}
		else {
			$convertedFileName = 'LendEngine_'.$fileName.'_'.time().'.sql';
			file_put_contents($dataDirectory.'/'.$convertedFileName, $importConfig.PHP_EOL.implode(PHP_EOL, $queries));
			$output->writeln('<info>Done. ' . count($queries) . ' SQLs for '.$description.' stored in ' . $convertedFileName . '</info>');
		}
	}
}
