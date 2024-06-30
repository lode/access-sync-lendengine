<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\PartSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-item-parts')]
class GatherExtraDataItemPartsCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$partMapping = [
			'article_id'       => 'ond_art_id',
			'part_description' => ['ond_oms', 'ond_nadereoms'],
			'part_count'       => 'ond_aantal',
		];
		
		echo 'Reading onderdelen ...'.PHP_EOL;
		$partCsvLines = $service->getExportCsv($dataDirectory.'/Onderdeel.csv', (new PartSpecification())->getExpectedHeaders());
		
		echo 'Reading artikelen ...'.PHP_EOL;
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		
		$articleSkuMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$articleSkuMapping[$articleId] = $articleSku;
		}
		
		$itemPartQueries = [];
		foreach ($partCsvLines as $partCsvLine) {
			$count = $partCsvLine[$partMapping['part_count']];
			
			$description = implode(' / ', array_filter([
				$partCsvLine[$partMapping['part_description'][0]],
				$partCsvLine[$partMapping['part_description'][1]]
			]));
			
			$articleId = $partCsvLine[$partMapping['article_id']];
			$itemSku   = $articleSkuMapping[$articleId];
			
			$itemPartQueries[] = "
				INSERT INTO `item_part` SET
				`item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$itemSku."'
						), 1
					)
				),
				`description` = '".str_replace("'", "\'", $description)."',
				`count` = '".$count."'
			;";
		}
		
		$itemPartQueryChunks = array_chunk($itemPartQueries, 2500);
		foreach ($itemPartQueryChunks as $index => $itemPartQueryChunk) {
			file_put_contents($dataDirectory.'/LendEngineItemParts_ExtraData_'.time().'_chunk_'.($index+1).'.sql', implode(PHP_EOL, $itemPartQueryChunk));
		}
		
		return Command::SUCCESS;
	}
}
