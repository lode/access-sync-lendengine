<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'convert-website-csvs')]
class ConvertWebsiteCsvsCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('csvTimestamp', InputArgument::REQUIRED, 'timestamp from csv filename in data/ directory');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		/**
		 * @todo convert 'Omschrijving'
		 *       - split into paragraphs
		 *       - assign each paragraph to a certain item column
		 * @todo convert 'Samenvatting'
		 *       - recognize checked out
		 *       - recognize maintenance
		 * @todo convert 'Kenmerk_Naam_Waarde'
		 *       - recognize filled value
		 */
		
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		/**
		 * get access file contents
		 */
		$csvTimestamp        = $input->getArgument('csvTimestamp');
		$itemCsvFilename     = $dataDirectory.'/Artikelen_'.$csvTimestamp.'.csv';
		$categoryCsvFilename = $dataDirectory.'/ArtikelTypes_'.$csvTimestamp.'.csv';
		$brandCsvFilename    = $dataDirectory.'/Merken_'.$csvTimestamp.'.csv';
		
		$itemExpectedHeaders = [
			'Id',
			'Actief',
			'Referentie',
			'Naam',
			'Omschrijving',
			'Samenvatting',
			'CategoryId',
			'MerkId',
			'Afbeelding',
			'Aantal',
			'Prijs',
			'ToonDePrijs',
			'Kenmerk_Naam_Waarde',
			'VerwijderBestaandeAfbeeldingen',
		];
		$categoryExpectedHeaders = [
			'Id',
			'Actief',
			'Naam',
		];
		$brandExpectedHeaders = [
			'Id',
			'Actief',
			'Naam',
		];
		
		echo 'Reading items ...'.PHP_EOL;
		$itemCsvLines = $service->getExportCsv($itemCsvFilename, $itemExpectedHeaders, $csvSeparator = ';');
		
		echo 'Reading categories ...'.PHP_EOL;
		$categoryCsvLines = $service->getExportCsv($categoryCsvFilename, $categoryExpectedHeaders, $csvSeparator = ';');
		
		echo 'Reading brands ...'.PHP_EOL;
		$brandCsvLines = $service->getExportCsv($brandCsvFilename, $brandExpectedHeaders, $csvSeparator = ';');
		
		/**
		 * prepare category and brand values
		 */
		$categoryMapping = [];
		foreach ($categoryCsvLines as $categoryCsvLine) {
			$categoryMapping[$categoryCsvLine['Id']] = $categoryCsvLine['Naam'];
		}
		
		$brandMapping = [];
		foreach ($brandCsvLines as $brandCsvLine) {
			$brandMapping[$brandCsvLine['Id']] = $brandCsvLine['Naam'];
		}
		
		/**
		 * generate lend engine item data
		 */
		$itemKeyMapping = [
			'Code'              => 'Referentie',
			'Name'              => 'Naam',
			'Short description' => null,
			'Long description'  => ['Omschrijving', 'Kenmerk_Naam_Waarde'],
			'Components'        => 'Omschrijving',
			'Condition'         => null,
			'Category'          => 'CategoryId',
			'Brand'             => 'MerkId',
			'Price paid'        => 'Prijs',
			'Reservable'        => 'Referentie',
		];
		
		$convertedItems = [];
		foreach ($itemCsvLines as $itemCsvLine) {
			/**
			 * static values
			 */
			$convertedItem = [
				'Short description' => '-',
				'Condition'         => 'B - Fair',
			];
			
			/**
			 * mapping
			 */
			foreach ($itemKeyMapping as $itemKey => $csvKey) {
				// skip static values
				if ($csvKey === null) {
					continue;
				}
				
				if (is_array($csvKey)) {
					$convertedItem[$itemKey] = array_intersect_key($itemCsvLine, array_flip($csvKey));
				}
				else {
					$convertedItem[$itemKey] = $itemCsvLine[$csvKey];
				}
			}
			
			/**
			 * converting
			 */
			// comma to dot
			$convertedItem['Price paid'] = $service->convertFieldToAmount($convertedItem['Price paid']);
			// birthday toys are not reservable
			$convertedItem['Reservable'] = (strpos($convertedItem['Reservable'], 'V') === 0) ? 'no' : 'yes';
			// amount of parts
			preg_match('{<p>(Aantal onderdelen: [0-9]+)</p>}', $convertedItem['Components'], $matches);
			$convertedItem['Components'] = $matches[1];
			// gather description
			$longDescriptionValues = $convertedItem['Long description'];
			$convertedItem['Long description'] = '';
			preg_match('{^<p>(?<description>[^-]+)</p><p></p><p>(Aantal onderdelen: [0-9]+)?</p>}', $longDescriptionValues['Omschrijving'], $matches);
			if (isset($matches['description'])) {
				#$convertedItem['Long description'] .= str_replace('</p><p>', PHP_EOL, $matches['description']);
				$convertedItem['Long description'] .= '<p>'.$matches['description'].'</p>';
			}
			if ($longDescriptionValues['Kenmerk_Naam_Waarde'] !== 'Leeftijd: Onbekend') {
				#if ($convertedItem['Long description'] !== '') {
				#    $convertedItem['Long description'] .= PHP_EOL.PHP_EOL;
				#}
				#$convertedItem['Long description'] .= $longDescriptionValues['Kenmerk_Naam_Waarde'];
				$convertedItem['Long description'] .= '<p>'.$longDescriptionValues['Kenmerk_Naam_Waarde'].'</p>';
			}
			// category name
			if (substr_count($convertedItem['Category'], ',') === 1) {
				$categoryId = $service->convertFieldToArray($convertedItem['Category'])[0];
				if (isset($categoryMapping[$categoryId])) {
					$convertedItem['Category'] = $categoryMapping[$categoryId];
				}
			}
			// brand name
			if (isset($brandMapping[$convertedItem['Brand']])) {
				$convertedItem['Brand'] = $brandMapping[$convertedItem['Brand']];
			}
			
			$convertedItems[] = $convertedItem;
		}
		
		/**
		 * create lend engine item csv
		 */
		$convertedCsv = $service->createImportCsv($convertedItems);
		file_put_contents($dataDirectory.'/LendEngineItems_'.$csvTimestamp.'_'.time().'.csv', $convertedCsv);
		
		return Command::SUCCESS;
	}
}
