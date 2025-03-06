<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Lode\AccessSyncLendEngine\specification\PartSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-item-parts')]
class GatherExtraDataItemPartsCommand extends Command
{
	private const string ITEM_STATUS_DELETE = 'Afgekeurd-Definitief';
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Onderdeel.csv',
				'Artikel.csv',
				'ArtikelStatus.csv',
				'ArtikelStatusLogging.csv',
			],
			$output,
		);
		
		$partMapping = [
			'article_id'       => 'ond_art_id',
			'part_description' => ['ond_oms', 'ond_nadereoms'],
			'part_count'       => 'ond_aantal',
			'part_sort'        => 'ond_volgnr',
		];
		$articleStatusMapping = [
			'location_id'   => 'ats_id',
			'location_name' => 'ats_oms',
		];
		$articleStatusLoggingMapping = [
			'item_id'     => 'asl_art_id',
			'location_id' => 'asl_ats_id',
		];
		
		$partCsvLines = $service->getExportCsv($dataDirectory.'/Onderdeel.csv', (new PartSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partCsvLines). ' onderdelen');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$output->writeln('<info>Exporting parts ...</info>');
		
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
		
		$articleSkuMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$articleSkuMapping[$articleId] = $articleSku;
		}
		
		$partSortMapping = [];
		foreach ($partCsvLines as $partCsvLine) {
			$articleId = $partCsvLine[$partMapping['article_id']];
			$csvSort   = $partCsvLine[$partMapping['part_sort']];
			
			if (isset($partSortMapping[$articleId]) === false) {
				$partSortMapping[$articleId] = [];
			}
			
			$partSortMapping[$articleId][] = $csvSort;
		}
		
		foreach ($partSortMapping as &$sorts) {
			sort($sorts);
			$sorts = array_flip($sorts);
		}
		unset($sorts);
		
		$itemPartQueries = [];
		foreach ($partCsvLines as $partCsvLine) {
			$articleId = $partCsvLine[$partMapping['article_id']];
			
			// skip permanently removed
			if ($locationPerItem[$articleId] === self::ITEM_STATUS_DELETE) {
				continue;
			}
			
			$itemSku = $articleSkuMapping[$articleId];
			
			$count = $partCsvLine[$partMapping['part_count']];
			
			$description = implode(' / ', array_filter([
				$partCsvLine[$partMapping['part_description'][0]],
				$partCsvLine[$partMapping['part_description'][1]]
			]));
			
			$csvSort   = $partCsvLine[$partMapping['part_sort']];
			$cleanSort = $partSortMapping[$articleId][$csvSort] + 1;
			
			$itemPartQueries[] = "
				INSERT INTO `item_part` SET
				`item_id` = (
					SELECT `id`
					FROM `inventory_item`
					WHERE `sku` = '".$itemSku."'
				),
				`description` = '".str_replace("'", "\'", $description)."',
				`count` = '".$count."',
				`sort` = '".$cleanSort."'
			;";
		}
		
		$service->createExportSqls($output, $dataDirectory, '03_ItemParts_ExtraData', $itemPartQueries, 'parts');
		
		return Command::SUCCESS;
	}
}
