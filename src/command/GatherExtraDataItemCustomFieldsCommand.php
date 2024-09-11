<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\EmployeeSpecification;
use Lode\AccessSyncLendEngine\specification\MessageKindSpecification;
use Lode\AccessSyncLendEngine\specification\MessageSpecification;
use Lode\AccessSyncLendEngine\specification\ResponsibleSpecification;
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
				'MeldingSoort.csv',
				'Artikel.csv',
				'Medewerker.csv',
				'Verantwoordelijke.csv',
			],
			$output,
		);
		
		$messageMapping = [
			'kind'              => 'Mld_Mls_id',
			'text'              => 'Mld_Oms',
			'inventory_item_id' => 'Mld_Art_id',
			'created_by'        => 'mld_mdw_id_toevoeg',
			'created_at'        => ['Mld_GemeldDatum', 'mld_vanafdatum'],
		];
		
		$messageCsvLines = $service->getExportCsv($dataDirectory.'/Melding.csv', (new MessageSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' meldingen');
		
		$messageKindCsvLines = $service->getExportCsv($dataDirectory.'/MeldingSoort.csv', (new MessageKindSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' melding soorten');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$employeeCsvLines = $service->getExportCsv($dataDirectory.'/Medewerker.csv', (new EmployeeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' medewerkers');
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', (new ResponsibleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' verantwoordelijken');
		
		$output->writeln('<info>Exporting item notes ...</info>');
		
		$messageKindMapping = [];
		foreach ($messageKindCsvLines as $messageKindCsvLine) {
			$messageKindId = $messageKindCsvLine['Mls_id'];
			$messageKind   = $messageKindCsvLine['Mls_Naam'];
			
			$messageKindMapping[$messageKindId] = $messageKind;
		}
		
		$articleSkuMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$articleSkuMapping[$articleId] = $articleSku;
		}
		
		$employeeMapping = [];
		foreach ($employeeCsvLines as $employeeCsvLine) {
			$employeeId    = $employeeCsvLine['mdw_id'];
			$responsibleId = $employeeCsvLine['mdw_vrw_id'];
			
			$employeeMapping[$employeeId] = $responsibleId;
		}
		
		$responsibleMapping = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$responsibleId   = $responsibleCsvLine['vrw_id'];
			$responsibleName = $responsibleCsvLine['vrw_voornaam'].' '.$responsibleCsvLine['vrw_achternaam'];
			
			$responsibleMapping[$responsibleId] = $responsibleName;
		}
		
		$itemCustomFieldData = [];
		foreach ($messageCsvLines as $messageCsvLine) {
			// filter on kinds meant for items
			$messageKindId = $messageCsvLine[$messageMapping['kind']];
			$messageKind   = $messageKindMapping[$messageKindId];
			if ($messageKind !== 'Artikel') {
				continue;
			}
			
			// check for item references
			if ($messageCsvLine[$messageMapping['inventory_item_id']] === '') {
				throw new \Exception('missing item id');
			}
			
			$text          = trim($messageCsvLine[$messageMapping['text']]);
			$employeeId    = $messageCsvLine[$messageMapping['created_by']];
			$responsibleId = $employeeMapping[$employeeId];
			$createdByName = $responsibleMapping[$responsibleId];
			
			$createdAt = $messageCsvLine[$messageMapping['created_at'][0]];
			if ($createdAt === '') {
				$createdAt = $messageCsvLine[$messageMapping['created_at'][1]];
			}
			$createdAt = \DateTime::createFromFormat('Y-n-j H:i:s', $createdAt);
			
			$text = $createdAt->format('Y-m-d H:i:s').' '.$createdByName.': '.$text;
			
			$articleId  = $messageCsvLine[$messageMapping['inventory_item_id']];
			$articleSku = $articleSkuMapping[$articleId];
			
			if (isset($itemCustomFieldData[$articleSku]) === false) {
				$itemCustomFieldData[$articleSku] = [];
			}
			
			$itemCustomFieldData[$articleSku][] = $text;
		}
		
		$itemCustomFieldQueries = [];
		foreach ($itemCustomFieldData as $articleSku => $texts) {
			$text = implode(PHP_EOL, $texts);
			
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
			;";
		}
		
		$convertedFileName = 'LendEngineItemCustomField_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemCustomFieldQueries));
		
		$output->writeln('<info>Done. ' . count($itemCustomFieldQueries) . ' SQLs for item custom fields stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
