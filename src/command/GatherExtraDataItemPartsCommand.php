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
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Onderdeel.csv',
				'Artikel.csv',
			],
			$output,
		);
		
		$partMapping = [
			'article_id'       => 'ond_art_id',
			'part_description' => ['ond_oms', 'ond_nadereoms'],
			'part_count'       => 'ond_aantal',
		];
		
		$partCsvLines = $service->getExportCsv($dataDirectory.'/Onderdeel.csv', (new PartSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partCsvLines). ' onderdelen');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$output->writeln('<info>Exporting parts ...</info>');
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		$canonicalArticleMapping = array_flip($canonicalArticleMapping);
		
		$itemPartQueries = [];
		foreach ($partCsvLines as $partCsvLine) {
			$articleId = $partCsvLine[$partMapping['article_id']];
			
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			if (isset($canonicalArticleMapping[$articleId]) === false) {
				continue;
			}
			
			$itemSku = $canonicalArticleMapping[$articleId];
			
			$count = $partCsvLine[$partMapping['part_count']];
			
			$description = implode(' / ', array_filter([
				$partCsvLine[$partMapping['part_description'][0]],
				$partCsvLine[$partMapping['part_description'][1]]
			]));
			
			$itemPartQueries[] = "
				INSERT INTO `item_part` SET
				`item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$itemSku."'
						), 1000
					)
				),
				`description` = '".str_replace("'", "\'", $description)."',
				`count` = '".$count."'
			;";
		}
		
		$service->createExportSqls($output, $dataDirectory, '03_ItemParts_ExtraData', $itemPartQueries, 'parts');
		
		return Command::SUCCESS;
	}
}
