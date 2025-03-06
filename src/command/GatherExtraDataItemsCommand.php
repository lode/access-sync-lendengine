<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-items')]
class GatherExtraDataItemsCommand extends Command
{
	private const string ITEM_STATUS_DELETE = 'Afgekeurd-Definitief';
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
				'ArtikelStatus.csv',
				'ArtikelStatusLogging.csv',
			],
			$output,
		);
		
		$articleMapping = [
			'id'                  => 'art_id',
			#'emission_factor_id' => 'art_mat_id', // Materiaal (extra data convert to 'Emission factor type')
			'show_on_website'     => 'art_webcatalogus',
			'created_at'          => 'art_aankoopdatum',
			'sku'                 => 'art_key',
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
		$output->writeln('Imported ' . count($articleCsvLines) . ' artikelen');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$output->writeln('<info>Exporting items ...</info>');
		
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
		
		$itemQueries = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine[$articleMapping['id']];
			$articleSku = $articleCsvLine[$articleMapping['sku']];
			
			// skip permanently removed
			if ($locationPerItem[$articleId] === self::ITEM_STATUS_DELETE) {
				continue;
			}
			
			$showOnWebsite = (bool) $articleCsvLine[$articleMapping['show_on_website']];
			$createdAt     = \DateTime::createFromFormat('Y-n-j H:i:s', $articleCsvLine[$articleMapping['created_at']], new \DateTimeZone('Europe/Amsterdam'));
			$createdAt->setTimezone(new \DateTimeZone('UTC'));
			$updatedAt     = $createdAt;
			
			$itemQueries[] = "
				UPDATE `inventory_item` SET
				`show_on_website` = '".($showOnWebsite === false ? 0 : 1)."',
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`updated_at` = '".$updatedAt->format('Y-m-d H:i:s')."'
				WHERE `sku` = '".$articleSku."'
			;";
		}
		
		$service->createExportSqls($output, $dataDirectory, '08_Items_ExtraData', $itemQueries, 'items');
		
		return Command::SUCCESS;
	}
}
