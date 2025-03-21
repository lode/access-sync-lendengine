<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-item-location')]
class GatherExtraDataItemLocationCommand extends Command
{
	private const int STATUS_DELETE    = -1;
	private const int STATUS_TEMPORARY = 0;
	private const int STATUS_ON_LOAN   = 1;
	private const string ITEM_STATUS_AVAILABLE = 'Gereed voor uitlenen';
	private const string ITEM_STATUS_DELETE = 'Afgekeurd-Definitief';
	private const STATUS_MAPPING   = [
		'Afgekeurd (Tijdelijk)'  => 'Repair',
		'Afgekeurd-Definitief'   => self::STATUS_DELETE,
		'Gereed voor uitlenen'   => 'In stock',
		'Ingenomen: Controleren' => self::STATUS_TEMPORARY,
		'Initieel'               => self::STATUS_TEMPORARY,
		'Uitgeleend'             => self::STATUS_ON_LOAN,
		'Verdwenen/kapot'        => self::STATUS_TEMPORARY,
	];
	
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
			'item_id'  => 'art_id',
			'item_sku' => 'art_key',
		];
		$articleStatusMapping = [
			'location_id'   => 'ats_id',
			'location_name' => 'ats_oms',
		];
		$articleStatusLoggingMapping = [
			'item_id'     => 'asl_art_id',
			'location_id' => 'asl_ats_id',
			'created_at'  => 'asl_datum',
			'note_text'   => 'asl_commentaar',
		];
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines) . ' articles');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$output->writeln('<info>Exporting item locations ...</info>');
		
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
		
		$itemLocationDataSet = [];
		$statisticsPerSku = [];
		foreach ($articleStatusLoggingCsvLines as $articleStatusLoggingCsvLine) {
			$itemId = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['item_id']];
			
			// skip permanently removed
			if ($locationPerItem[$itemId] === self::ITEM_STATUS_DELETE) {
				continue;
			}
			
			$itemSku      = $articleSkuMapping[$itemId];
			$locationId   = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['location_id']];
			$locationName = $locationMapping[$locationId];
			$logCreatedAt = \DateTime::createFromFormat('Y-n-j H:i:s', $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['created_at']], new \DateTimeZone('Europe/Amsterdam'));
			$logCreatedAt->setTimezone(new \DateTimeZone('UTC'));
			$noteText     = trim($articleStatusLoggingCsvLine[$articleStatusLoggingMapping['note_text']]);
			
			// only keep the last location for a certain sku
			$itemLocationDataSet[$itemSku] = [
				'itemSku'      => $itemSku,
				'locationName' => $locationName,
				'logCreatedAt' => $logCreatedAt,
				'noteText'     => $noteText,
			];
			$statisticsPerSku[$itemSku] = $locationName;
		}
		
		$itemLocations = [];
		foreach ($itemLocationDataSet as $itemLocationData) {
			$locationName = $itemLocationData['locationName'];
			$itemLocations[$locationName] = $locationName;
		}
		
		$itemLocationQueries = [];
		foreach ($itemLocations as $locationName) {
			$locationAction = self::STATUS_MAPPING[$locationName] ?? self::STATUS_TEMPORARY;
			if ($locationAction === self::STATUS_TEMPORARY) {
				$locationName = substr('Access - '.$locationName, 0, 32);
			}
			else { // delete & already existing
				continue;
			}
			
			$isAvailable = ($locationName === self::ITEM_STATUS_AVAILABLE) ? '1' : '0';
			
			$itemLocationQueries[] = "
			    INSERT
			      INTO `inventory_location`
			       SET `name`         = '".$locationName."',
			           `is_active`    = 1,
			           `is_available` = ".$isAvailable.",
			           `site`         = 1
			;";
		}
		
		$noteQueries = [];
		foreach ($itemLocationDataSet as $itemLocationData) {
			$locationName = $itemLocationData['locationName'];
			$locationAction = self::STATUS_MAPPING[$locationName] ?? self::STATUS_TEMPORARY;
			if ($locationAction === self::STATUS_DELETE || $locationAction === self::STATUS_ON_LOAN) {
				continue;
			}
			elseif ($locationAction === self::STATUS_TEMPORARY) {
				$locationName = substr('Access - '.$locationName, 0, 32);
			}
			else {
				$locationName = $locationAction;
			}
			
			$itemLocationQueries[] = "
			       SET @locationId = (
			               SELECT `id`
			                 FROM `inventory_location`
			                WHERE `name` = '".$locationName."'
			           )
			;";
			
			$itemLocationQueries[] = "
			    UPDATE `inventory_item`
			       SET `current_location_id` = @locationId
			     WHERE `sku` = '".$itemLocationData['itemSku']."'
			;";
			
			$itemLocationQueries[] = "
			    INSERT
			      INTO `item_movement`
			       SET `inventory_item_id` = (
                           SELECT `id`
                             FROM `inventory_item`
                            WHERE `sku` = '".$itemLocationData['itemSku']."'
			           ),
			           `inventory_location_id` = @locationId,
			           `created_at` = '".$itemLocationData['logCreatedAt']->format('Y-m-d H:i:s')."'
			;";
			
			if ($itemLocationData['noteText'] !== '') {
				$noteQueries[] = "
				    INSERT
				      INTO `note`
				       SET `text` = '".str_replace("'", "\'", $itemLocationData['noteText'])."',
				           `inventory_item_id` = (
	                           SELECT `id`
	                             FROM `inventory_item`
	                            WHERE `sku` = '".$itemLocationData['itemSku']."'
				           ),
				           `created_at` = '".$itemLocationData['logCreatedAt']->format('Y-m-d H:i:s')."'
				;";
			}
		}
		
		$statisticPerLocation = [];
		foreach ($statisticsPerSku as $itemSku => $status) {
			if (isset($statisticPerLocation[$status]) === false) {
				$statisticPerLocation[$status] = 0;
			}
			$statisticPerLocation[$status]++;
		}
		
		foreach ($statisticPerLocation as $location => $count) {
			$output->writeln('- '.$count."\t".$location);
		}
		
		$service->createExportSqls($output, $dataDirectory, '07_ItemLocation_ExtraData', $itemLocationQueries, 'item locations');
		$service->createExportSqls($output, $dataDirectory, '10_ItemLocationNotes_ExtraData', $noteQueries, 'item location notes');
		
		return Command::SUCCESS;
	}
}
