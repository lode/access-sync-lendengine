<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleLendPeriodSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleTypeSpecification;
use Lode\AccessSyncLendEngine\specification\BrandSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @todo optionally convert parts in components field
 */

#[AsCommand(name: 'convert-items')]
class ConvertItemsCommand extends Command
{
	private const string STATUS_DELETE = 'Afgekeurd-Definitief';
	
	protected function configure(): void
	{
		$this->addOption('withCorrections', description: 'generate corrections SQLs for older version of this script');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		$withCorrections = $input->getOption('withCorrections');
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
				'ArtikelStatus.csv',
				'ArtikelStatusLogging.csv',
				'ArtikelType.csv',
				'ArtikelUitleenDuur.csv',
				'Merk.csv',
			],
			$output,
		);
		
		/**
		 * get access file contents
		 */
		$articleMapping = [
			'art_key'           => 'Code',
			'art_naam'          => 'Name',
			'art_oms'           => 'Full description',
			'art_att_id'        => 'Category',
			'art_mrk_id'        => 'Brand',
			'art_prijs'         => 'Price paid',
			'art_aud_id'        => 'Per',
			'art_reserveerbaar' => 'Reservable',
		];
		$articleStatusMapping = [
			'location_id'   => 'ats_id',
			'location_name' => 'ats_oms',
		];
		$articleStatusLoggingMapping = [
			'item_id'     => 'asl_art_id',
			'location_id' => 'asl_ats_id',
		];
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$articleTypeCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelType.csv', (new ArticleTypeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleTypeCsvLines). ' artikeltypes');
		
		$articleLendPeriodCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelUitleenDuur.csv', (new ArticleLendPeriodSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleLendPeriodCsvLines). ' artikeluitleenduur');
		
		$brandCsvLines = $service->getExportCsv($dataDirectory.'/Merk.csv', (new BrandSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($brandCsvLines). ' merken');
		
		$output->writeln('<info>Exporting items ...</info>');
		
		$articleTypeMapping = [];
		foreach ($articleTypeCsvLines as $articleTypeCsvLine) {
			if ($articleTypeCsvLine['att_actief'] !== '1') {
				continue;
			}
			
			$articleTypeMapping[$articleTypeCsvLine['att_id']] = $articleTypeCsvLine['att_code'].' - '.$articleTypeCsvLine['att_oms'];
		}
		
		$articleLendPeriodMapping = [];
		foreach ($articleLendPeriodCsvLines as $articleLendPeriodCsvLine) {
			$articleLendPeriodMapping[$articleLendPeriodCsvLine['aud_id']] = $articleLendPeriodCsvLine['aud_aantal'];
		}
		
		$brandMapping = [];
		foreach ($brandCsvLines as $brandCsvLine) {
			if ($brandCsvLine['mrk_actief'] !== '1') {
				continue;
			}
			
			$brandMapping[$brandCsvLine['mrk_id']] = $brandCsvLine['mrk_naam'];
		}
		
		$locationMapping = [];
		foreach ($articleStatusCsvLines as $articleStatusCsvLine) {
			$locationId   = $articleStatusCsvLine[$articleStatusMapping['location_id']];
			$locationName = $articleStatusCsvLine[$articleStatusMapping['location_name']];
			
			$locationMapping[$locationId] = $locationName;
		}
		
		$locationPerItem = [];
		foreach ($articleStatusLoggingCsvLines as $articleStatusLoggingCsvLine) {
			$articleId    = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['item_id']];
			$locationId   = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['location_id']];
			$locationName = $locationMapping[$locationId];
			
			// overwrite with the latest location log per article
			$locationPerItem[$articleId] = $locationName;
		}
		
		$itemsConverted = [];
		$skusConverted = [];
		$skusSkipped = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			// skip permanently removed
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			if ($locationPerItem[$articleId] === self::STATUS_DELETE) {
				$skusSkipped[$articleSku] = true;
				continue;
			}
			$skusConverted[$articleSku] = true;
			
			$itemConverted = [
				'Code'             => null,
				'Type'             => 'loan',
				'Name'             => null,
				'Full description' => null,
				'Condition'        => 'B - Fair',
				'Category'         => null,
				'Brand'            => null,
				'Price paid'       => null,
				'Per'              => null,
				'Reservable'       => null,
			];
			
			/**
			 * simple mapping
			 */
			foreach ($articleMapping as $articleKey => $itemKey) {
				$itemConverted[$itemKey] = $articleCsvLine[$articleKey];
			}
			
			/**
			 * converting
			 */
			
			// collect relations
			if (isset($articleTypeMapping[$itemConverted['Category']]) === false) {
				// access allowed to delete categories without disconnecting the category id from the item
				$itemConverted['Category'] = 'Category #'.$itemConverted['Category'];
			}
			else {
				$itemConverted['Category'] = $articleTypeMapping[$itemConverted['Category']];
			}
			$itemConverted['Brand']    = $brandMapping[$itemConverted['Brand']];
			
			// convert amount
			$itemConverted['Price paid'] = str_replace('€ ', '', $itemConverted['Price paid']);
			$itemConverted['Price paid'] = str_replace('.', ',', $itemConverted['Price paid']);
			$itemConverted['Price paid'] = (float) $itemConverted['Price paid'];
			if ($itemConverted['Price paid'] === 0.0) {
				$itemConverted['Price paid'] = null;
			}
			
			// value for loan period, clear default loan period, collection relation for other
			if ($itemConverted['Per'] === '1') {
				$itemConverted['Per'] = null;
			}
			else {
				$itemConverted['Per'] = (int) $articleLendPeriodMapping[$itemConverted['Per']];
			}
			
			// reservable boolean
			$itemConverted['Reservable'] = ($itemConverted['Reservable'] === '1') ? 'yes' : 'no';
			
			$itemsConverted[] = $itemConverted;
		}
		
		/**
		 * create lend engine item csv
		 */
		$convertedCsv = $service->createImportCsv($itemsConverted);
		$convertedFileName = 'LendEngine_02_Items_'.time().'.csv';
		file_put_contents($dataDirectory.'/'.$convertedFileName, $convertedCsv);
		
		$output->writeln('<info>Done. ' . count($itemsConverted) . ' items stored in ' . $convertedFileName . '</info>');
		
		/**
		 * generate SQLs to repair an import created by the previous version of this script
		 */
		if ($withCorrections === true) {
			$repairQueries = [];
			foreach ($skusSkipped as $sku => $null) {
				if (isset($skusConverted[$sku]) === true) {
					continue;
				}
				
				$repairQueries[] = "SET @itemId = (SELECT `id` FROM `inventory_item` WHERE `sku` = '".$sku."');";
				$repairQueries[] = "DELETE FROM `image` WHERE `inventory_item_id` = @itemId;";
				$repairQueries[] = "DELETE FROM `item_movement` WHERE `inventory_item_id` = @itemId;";
				$repairQueries[] = "DELETE FROM `item_part` WHERE `item_id` = @itemId;";
				$repairQueries[] = "DELETE FROM `note` WHERE `inventory_item_id` = @itemId;";
				$repairQueries[] = "DELETE FROM `inventory_item` WHERE `id` = @itemId;";
			}
			
			$service->createExportSqls($output, $dataDirectory, '12_ItemCorrections', $repairQueries, 'loans');
		}
		
		return Command::SUCCESS;
	}
}
