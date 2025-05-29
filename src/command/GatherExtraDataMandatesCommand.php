<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\ResponsibleSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-mandates')]
class GatherExtraDataMandatesCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Verantwoordelijke.csv',
				'Lid.csv',
			],
			$output,
		);
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', (new ResponsibleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($responsibleCsvLines) . ' responsibles');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$output->writeln('<info>Exporting mandates ...</info>');
		
		$responsibleMapping = [
			'mandate_number' => 'vrw_IBAN',
		];
		$memberMapping = [
			'contact_id'        => 'lid_vrw_id',
			'membership_number' => 'lid_key',
		];
		
		$membershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$responsibleId    = $memberCsvLine[$memberMapping['contact_id']];
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			
			$membershipNumberMapping[$responsibleId] = $membershipNumber;
		}
		
		$mandateQueries = [];
		$mandateQueries[] = "
			SET @newSort = (
				SELECT IFNULL(MAX(`sort`)+1, 0)
				FROM `contact_field`
			)
		;";
		$mandateQueries[] = "
			INSERT INTO `contact_field` SET
			`name` = 'IBAN',
			`type` = 'text',
			`required` = 0,
			`sort` = @newSort
		;";
		$mandateQueries[] = "
			SET @fieldId = (
				SELECT `id`
				FROM `contact_field`
				WHERE `name` = 'IBAN'
			)
		;";
		
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$responsibleId = $responsibleCsvLine['vrw_id'];
			
			// skip contacts without membership, as we have no way of connecting the data
			if (isset($membershipNumberMapping[$responsibleId]) === false) {
				continue;
			}
			
			$membershipNumber = $membershipNumberMapping[$responsibleId];
			$mandateNumber    = $responsibleCsvLine[$responsibleMapping['mandate_number']];
			
			if ($mandateNumber === '') {
				continue;
			}
			
			$mandateQueries[] = "
				INSERT INTO `contact_field_value` SET
				`contact_field_id` = @fieldId,
				`contact_id` = (
					SELECT `id`
					FROM `contact`
					WHERE `membership_number` = '".$membershipNumber."'
				),
				`field_value` = '".$mandateNumber."'
			;";
		}
		
		$service->createExportSqls($output, $dataDirectory, '09_Mandates_ExtraData', $mandateQueries, 'mandates');
		
		return Command::SUCCESS;
	}
}
