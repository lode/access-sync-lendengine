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
	private const MEMBER_STATUS_ACTIVE   = '1';
	private const MEMBER_STATUS_CANCELED = '2';
	private const MEMBER_STATUS_INACTIVE = '3';
	
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
			'email'      => 'vrw_email',
		];
		
		$memberMapping = [
			'membership_number' => 'lid_key',
		];
		
		$responsibleMemberMapping = [];
		$nonActiveResponsibleIds = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$responsibleId = $memberCsvLine['lid_vrw_id'];
			if ($responsibleId === '') {
				continue;
			}
			
			$memberStatusId = $memberCsvLine['lid_lis_id'];
			if ($memberStatusId !== self::MEMBER_STATUS_ACTIVE) {
				$nonActiveResponsibleIds[] = $responsibleId;
				continue;
			}
			
			if (isset($responsibleMemberMapping[$responsibleId])) {
				throw new \Exception('multiple active members found');
			}
			
			$responsibleMemberMapping[$responsibleId] = $memberCsvLine;
		}
		
		$contactQueries = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			// skip non-active members
			$responsibleId = $responsibleCsvLine['vrw_id'];
			if (isset($responsibleMemberMapping[$responsibleId]) === false) {
				continue;
			}
			if ($responsibleCsvLine['vrw_email'] === '') {
				continue;
			}
			
			$memberCsvLine = $responsibleMemberMapping[$responsibleId];
			
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			$email            = $responsibleCsvLine[$responsibleMapping['email']];
			$createdAt        = \DateTime::createFromFormat('Y-n-j H:i:s', $responsibleCsvLine[$responsibleMapping['created_at']]);
			
			$contactQueries[] = "
				UPDATE `contact` SET
				`membership_number` = '".$membershipNumber."',
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."'
				WHERE `email` = '".$email."'
			;";
		}
		
		$convertedFileName = 'LendEngineContacts_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $contactQueries));
		
		$output->writeln('<info>Done. ' . count($contactQueries) . ' SQLs for contacts stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
