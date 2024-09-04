<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\WebsiteArticleSpecification;
use Lode\AccessSyncLendEngine\specification\WebsiteArticleTypeSpecification;
use Lode\AccessSyncLendEngine\specification\WebsiteBrandSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @todo convert 'Omschrijving'
 *       - split into paragraphs
 *       - assign each paragraph to a certain item column
 * @todo convert 'Samenvatting'
 *       - recognize checked out
 *       - recognize maintenance
 * @todo convert 'Kenmerk_Naam_Waarde'
 *       - recognize filled value
 * @todo make 'verjaardagsspeelgoed' being not reservable configurable?
 */

#[AsCommand(name: 'convert-website-csvs')]
class ConvertWebsiteCsvsCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('csvTimestamp', InputArgument::REQUIRED, 'timestamp from csv filename in data/ directory');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		/**
		 * get access file contents
		 */
		$csvTimestamp = $input->getArgument('csvTimestamp');
		$csvSeparator = ';';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikelen_'.$csvTimestamp.'.csv',
				'ArtikelTypes_'.$csvTimestamp.'.csv',
				'Merken_'.$csvTimestamp.'.csv',
			],
			$output,
		);
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikelen_'.$csvTimestamp.'.csv', (new WebsiteArticleSpecification())->getExpectedHeaders(), $csvSeparator);
		$output->writeln('Imported ' . count($articleCsvLines) . ' articles');
		
		$articleTypeCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelTypes_'.$csvTimestamp.'.csv', (new WebsiteArticleTypeSpecification())->getExpectedHeaders(), $csvSeparator);
		$output->writeln('Imported ' . count($articleTypeCsvLines) . ' article types');
		
		$brandCsvLines = $service->getExportCsv($dataDirectory.'/Merken_'.$csvTimestamp.'.csv', (new WebsiteBrandSpecification())->getExpectedHeaders(), $csvSeparator);
		$output->writeln('Imported ' . count($brandCsvLines) . ' brands');
		
		$output->writeln('<info>Exporting items ...</info>');
		
		/**
		 * prepare article type and brand values
		 */
		$articleTypeMapping = [];
		foreach ($articleTypeCsvLines as $articleTypeCsvLine) {
			$articleTypeMapping[$articleTypeCsvLine['Id']] = $articleTypeCsvLine['Naam'];
		}
		
		$brandMapping = [];
		foreach ($brandCsvLines as $brandCsvLine) {
			$brandMapping[$brandCsvLine['Id']] = $brandCsvLine['Naam'];
		}
		
		/**
		 * generate lend engine item data
		 */
		$itemArticleMapping = [
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
		
		$itemsConverted = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			/**
			 * static values
			 */
			$itemConverted = [
				'Short description' => '-',
				'Condition'         => 'B - Fair',
			];
			
			/**
			 * mapping
			 */
			foreach ($itemArticleMapping as $itemKey => $articleKey) {
				// skip static values
				if ($articleKey === null) {
					continue;
				}
				
				if (is_array($articleKey)) {
					$itemConverted[$itemKey] = array_intersect_key($articleCsvLine, array_flip($articleKey));
				}
				else {
					$itemConverted[$itemKey] = $articleCsvLine[$articleKey];
				}
			}
			
			/**
			 * converting
			 */
			// comma to dot
			$itemConverted['Price paid'] = $service->convertFieldToAmount($itemConverted['Price paid']);
			// birthday toys are not reservable
			$itemConverted['Reservable'] = (strpos($itemConverted['Reservable'], 'V') === 0) ? 'no' : 'yes';
			// amount of parts
			preg_match('{<p>(Aantal onderdelen: [0-9]+)</p>}', $itemConverted['Components'], $matches);
			$itemConverted['Components'] = $matches[1];
			// gather description
			$longDescriptionValues = $itemConverted['Long description'];
			$itemConverted['Long description'] = '';
			preg_match('{^<p>(?<description>[^-]+)</p><p></p><p>(Aantal onderdelen: [0-9]+)?</p>}', $longDescriptionValues['Omschrijving'], $matches);
			if (isset($matches['description'])) {
				#$itemConverted['Long description'] .= str_replace('</p><p>', PHP_EOL, $matches['description']);
				$itemConverted['Long description'] .= '<p>'.$matches['description'].'</p>';
			}
			if ($longDescriptionValues['Kenmerk_Naam_Waarde'] !== 'Leeftijd: Onbekend') {
				#if ($itemConverted['Long description'] !== '') {
				#    $itemConverted['Long description'] .= PHP_EOL.PHP_EOL;
				#}
				#$itemConverted['Long description'] .= $longDescriptionValues['Kenmerk_Naam_Waarde'];
				$itemConverted['Long description'] .= '<p>'.$longDescriptionValues['Kenmerk_Naam_Waarde'].'</p>';
			}
			// category name
			if (substr_count($itemConverted['Category'], ',') === 1) {
				$articleTypeId = $service->convertFieldToArray($itemConverted['Category'])[0];
				if (isset($articleTypeMapping[$articleTypeId])) {
					$itemConverted['Category'] = $articleTypeMapping[$articleTypeId];
				}
			}
			// brand name
			if (isset($brandMapping[$itemConverted['Brand']])) {
				$itemConverted['Brand'] = $brandMapping[$itemConverted['Brand']];
			}
			
			$itemsConverted[] = $itemConverted;
		}
		
		/**
		 * create lend engine item csv
		 */
		$convertedCsv = $service->createImportCsv($itemsConverted);
		$convertedFileName = 'LendEngineItemsAlternative_'.$csvTimestamp.'_'.time().'.csv';
		file_put_contents($dataDirectory.'/'.$convertedFileName, $convertedCsv);
		
		$output->writeln('<info>Done. ' . count($itemsConverted) . ' items stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
