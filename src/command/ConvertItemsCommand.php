<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleLendPeriodSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
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
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
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
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
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
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		
		$itemsConverted = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			if ($canonicalArticleMapping[$articleSku] !== $articleId) {
				continue;
			}
			
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
		
		return Command::SUCCESS;
	}
}
