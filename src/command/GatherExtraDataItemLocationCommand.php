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
	private const STATUS_DELETE = -1;
	private const STATUS_TEMPORARY = 0;
	private const STATUS_MAPPING = [
		'Afgekeurd (Tijdelijk)'  => 'Repair',
		'Afgekeurd-Definitief'   => self::STATUS_DELETE,
		'Gereed voor uitlenen'   => 'In stock',
		'Ingenomen: Controleren' => self::STATUS_TEMPORARY,
		'Initieel'               => self::STATUS_TEMPORARY,
		'Uitgeleend'             => self::STATUS_TEMPORARY,
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
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		$canonicalArticleMapping = array_flip($canonicalArticleMapping);
		
		$itemLocationDataSet = [];
		$statisticsPerSku = [];
		foreach ($articleStatusLoggingCsvLines as $articleStatusLoggingCsvLine) {
			$itemId = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['item_id']];
			
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			if (isset($canonicalArticleMapping[$itemId]) === false) {
				continue;
			}
			
			$itemSku      = $canonicalArticleMapping[$itemId];
			$locationId   = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['location_id']];
			$locationName = $locationMapping[$locationId];
			$logCreatedAt = \DateTime::createFromFormat('Y-n-j H:i:s', $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['created_at']]);
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
				$locationName = 'Access - '.$locationName;
			}
			else { // delete & already existing
				continue;
			}
			
			$isAvailable = ($locationName === 'Gereed voor uitlenen') ? '1' : '0';
			
			$itemLocationQueries[] = "
			    INSERT
			      INTO `inventory_location`
			       SET `name`         = '".$locationName."',
			           `is_active`    = 1,
			           `is_available` = ".$isAvailable.",
			           `site`         = 1
			;";
		}
		
		foreach ($itemLocationDataSet as $itemLocationData) {
			$locationName = $itemLocationData['locationName'];
			$locationAction = self::STATUS_MAPPING[$locationName] ?? self::STATUS_TEMPORARY;
			if ($locationAction === self::STATUS_DELETE) {
				continue;
			}
			elseif ($locationAction === self::STATUS_TEMPORARY) {
				$locationName = 'Access - '.$locationName;
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
			               SELECT IFNULL(
			                      (
			                          SELECT `id`
			                            FROM `inventory_item`
			                           WHERE `sku` = '".$itemLocationData['itemSku']."'
			                      ), 1000
			               )
			           ),
			           `inventory_location_id` = @locationId,
			           `created_at` = '".$itemLocationData['logCreatedAt']->format('Y-m-d H:i:s')."'
			;";
			
			if ($itemLocationData['noteText'] !== '') {
				$itemLocationQueries[] = "
				    INSERT
				      INTO `note`
				       SET `text` = '".str_replace("'", "\'", $itemLocationData['noteText'])."',
				           `inventory_item_id` = (
				               SELECT IFNULL(
				                      (
				                          SELECT `id`
				                            FROM `inventory_item`
				                           WHERE `sku` = '".$itemLocationData['itemSku']."'
				                      ), 1000
				               )
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
		
		$convertedFileName = 'LendEngineItemLocation_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemLocationQueries));
		
		$output->writeln('<info>Done. ' . count($itemLocationQueries) . ' SQLs for item locations stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
