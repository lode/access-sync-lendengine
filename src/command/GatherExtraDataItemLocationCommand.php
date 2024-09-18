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
		
		$itemMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$itemId  = $articleCsvLine[$articleMapping['item_id']];
			$itemSku = $articleCsvLine[$articleMapping['item_sku']];
			
			$itemMapping[$itemId] = $itemSku;
		}
		
		$locationMapping = [];
		foreach ($articleStatusCsvLines as $articleStatusCsvLine) {
			$locationId   = $articleStatusCsvLine[$articleStatusMapping['location_id']];
			$locationName = $articleStatusCsvLine[$articleStatusMapping['location_name']];
			
			$locationMapping[$locationId] = $locationName;
		}
		
		$itemLocationQueries = [];
		$locationCreated = [];
		foreach ($articleStatusLoggingCsvLines as $articleStatusLoggingCsvLine) {
			$itemId       = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['item_id']];
			if (isset($itemMapping[$itemId]) === false) {
				continue;
			}
			
			$itemSku      = $itemMapping[$itemId];
			$locationId   = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['location_id']];
			$locationName = $locationMapping[$locationId];
			$logCreatedAt = \DateTime::createFromFormat('Y-n-j H:i:s', $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['created_at']]);
			$noteText     = trim($articleStatusLoggingCsvLine[$articleStatusLoggingMapping['note_text']]);
			
			if (isset($locationCreated[$locationId]) === false) {
				if ($locationName === 'Gereed voor uitlenen') {
					continue;
				}
				
				$itemLocationQueries[] = "
				    INSERT
				      INTO `inventory_location`
				       SET `name`         = 'Access - ".$locationName."',
				           `is_active`    = 1,
				           `is_available` = 0,
				           `site`         = 1
				;";
			}
			
			$itemLocationQueries[] = "
			       SET @locationId = (
			               SELECT `id`
			                 FROM `inventory_location`
			                WHERE `name` = 'Access - ".$locationName."'
			           )
			;";
			
			$itemLocationQueries[] = "
			    UPDATE `inventory_item`
			       SET `inventory_location_id` = @locationId
			     WHERE `sku` = '".$itemSku."'
			;";
			
			$itemLocationQueries[] = "
			    INSERT
			      INTO `item_movement`
			       SET `inventory_item_id` = (
			               SELECT IFNULL(
			                      (
			                          SELECT `id`
			                            FROM `inventory_item`
			                           WHERE `sku` = '".$itemSku."'
			                      ), 1000
			               )
			           ),
			           `inventory_location_id` = @locationId,
			           `created_at` = '".$logCreatedAt->format('Y-m-d H:i:s')."'
			;";
			
			if ($noteText !== '') {
				$itemLocationQueries[] = "
				    INSERT
				      INTO `note`
				       SET `text` = '".str_replace("'", "\'", $noteText)."',
				           `inventory_item_id` = (
				               SELECT IFNULL(
				                      (
				                          SELECT `id`
				                            FROM `inventory_item`
				                           WHERE `sku` = '".$itemSku."'
				                      ), 1000
				               )
				           ),
				           `created_at` = '".$logCreatedAt->format('Y-m-d H:i:s')."'
				;";
			}
		}
		
		$convertedFileName = 'LendEngineItemLocation_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemLocationQueries));
		
		$output->writeln('<info>Done. ' . count($itemLocationQueries) . ' SQLs for item locations stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
