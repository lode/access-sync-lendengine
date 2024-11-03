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

/**
 * @todo convert staff to admin
 */

#[AsCommand(name: 'gather-extra-data-contacts')]
class GatherExtraDataContactsCommand extends Command
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
		
		$output->writeln('<info>Exporting contacts ...</info>');
		
		$responsibleMapping = [
			'created_at' => 'vrw_toevoegdatum',
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
		
		$contactQueries = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$responsibleId = $responsibleCsvLine['vrw_id'];
			
			// skip contacts without membership, as we have no way of connecting the data
			if (isset($membershipNumberMapping[$responsibleId]) === false) {
				continue;
			}
			
			$membershipNumber = $membershipNumberMapping[$responsibleId];
			$createdAt        = \DateTime::createFromFormat('Y-n-j H:i:s', $responsibleCsvLine[$responsibleMapping['created_at']]);
			
			$contactQueries[] = "
				UPDATE `contact` SET
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."'
				WHERE `membership_number` = '".$membershipNumber."'
			;";
		}
		
		$service->createExportSqls($output, $dataDirectory, '09_Contacts_ExtraData', $contactQueries, 'contacts');
		
		return Command::SUCCESS;
	}
}
