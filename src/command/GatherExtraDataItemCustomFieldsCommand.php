<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\MessageSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-item-custom-fields')]
class GatherExtraDataItemCustomFieldsCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('customFieldId', InputArgument::REQUIRED, 'id of the multi-line custom field to store "meldingen"');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		$customFieldId = $input->getArgument('customFieldId');
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Melding.csv',
				'Artikel.csv',
			],
			$output,
		);
		
		$messageMapping = [
			'text'              => 'Mld_Oms',
			'inventory_item_id' => 'Mld_Art_id',
			'created_by'        => 'mld_mdw_id_toevoeg',
			'created_at'        => 'Mld_GemeldDatum',
		];
		
		$messageCsvLines = $service->getExportCsv($dataDirectory.'/Melding.csv', (new MessageSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' meldingen');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$output->writeln('<info>Exporting contact notes ...</info>');
		
		$articleSkuMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$articleSkuMapping[$articleId] = $articleSku;
		}
		
		$itemCustomFieldQueries = [];
		foreach ($messageCsvLines as $messageCsvLine) {
			// @todo filter on Mld_Mls_id
			
			$text      = $messageCsvLine[$messageMapping['text']];
			$createdBy = $messageCsvLine[$messageMapping['created_by']]; // @todo convert from mld_mdw_id_toevoeg to contact_first|last_name
			$createdAt = \DateTime::createFromFormat('Y-n-j H:i:s', $messageCsvLine[$messageMapping['created_at']]);
			
			$text = $createdAt->format('Y-m-d H:i:s').' '.$createdBy.': '.$text.PHP_EOL;
			
			$articleId  = $messageCsvLine[$messageMapping['inventory_item_id']];
			$articleSku = $articleSkuMapping[$articleId];
			
			$customFieldId
			$itemCustomFieldQueries[] = "
				INSERT INTO `product_field_value` SET
				`product_field_id` = ".$customFieldId."
				`inventory_item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$articleSku."'
						), 1
					)
				),
				`field_value` = '".str_replace("'", "\'", $text)."'
				ON DUPLICATE KEY UPDATE
				`field_value` = CONCAT(`field_value`, '\n', '".str_replace("'", "\'", $text)."')
			;";
		}
		
		$convertedFileName = 'LendEngineItemCustomField_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemCustomFieldQueries));
		
		$output->writeln('<info>Done. ' . count($itemCustomFieldQueries) . ' SQLs for item custom fields stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
