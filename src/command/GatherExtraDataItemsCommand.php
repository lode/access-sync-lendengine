<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-items')]
class GatherExtraDataItemsCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
			],
			$output,
		);
		
		$articleMapping = [
			'id'              => 'art_id',
			'show_on_website' => 'art_webcatalogus',
			'created_at'      => 'art_aankoopdatum',
			'sku'             => 'art_key',
		];
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines) . ' artikelen');
		
		$output->writeln('<info>Exporting items ...</info>');
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine[$articleMapping['id']];
			$articleSku = $articleCsvLine[$articleMapping['sku']];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		
		$itemQueries = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			$articleId  = $articleCsvLine[$articleMapping['id']];
			$articleSku = $articleCsvLine[$articleMapping['sku']];
			
			if ($canonicalArticleMapping[$articleSku] !== $articleId) {
				continue;
			}
			
			$showOnWebsite = (bool) $articleCsvLine[$articleMapping['show_on_website']];
			$createdAt     = \DateTime::createFromFormat('Y-n-j H:i:s', $articleCsvLine[$articleMapping['created_at']]);
			$updatedAt     = $createdAt;
			
			$itemQueries[] = "
				UPDATE `inventory_item` SET
				`show_on_website` = '".($showOnWebsite === false ? 0 : 1)."',
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`updated_at` = '".$updatedAt->format('Y-m-d H:i:s')."'
				WHERE `sku` = '".$articleSku."'
			;";
		}
		
		$convertedFileName = 'LendEngine_08_Items_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemQueries));
		
		$output->writeln('<info>Done. ' . count($itemQueries) . ' SQLs for items stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
