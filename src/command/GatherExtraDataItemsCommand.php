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
		
		$articleMapping = [
			'created_at' => 'art_aankoopdatum',
			'sku'        => 'art_key',
		];
		
		echo 'Reading artikelen ...'.PHP_EOL;
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		
		$itemQueries = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$createdAt = \DateTime::createFromFormat('Y-n-j H:i:s', $articleCsvLine[$articleMapping['created_at']]);
			$updatedAt = $createdAt;
			$sku       = $articleCsvLine[$articleMapping['membership_number']];
			
			$itemQueries[] = "
				UPDATE `inventory_item` SET
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`updated_at` = '".$updatedAt->format('Y-m-d H:i:s')."'
				WHERE `sku` = '".$sku."'
			;";
		}
		
		file_put_contents($dataDirectory.'/LendEngineItems_ExtraData_'.time().'.sql', implode(PHP_EOL, $itemQueries));
		
		return Command::SUCCESS;
	}
}
