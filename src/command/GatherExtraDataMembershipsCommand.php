<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-memberships')]
class GatherExtraDataMembershipsCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Lid.csv',
			],
			$output,
		);
		
		$memberMapping = [
			'starts_at'         => 'lid_vanafdatum',
			'expires_at'        => 'lid_einddatum',
			'membership_number' => 'lid_key',
		];
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$output->writeln('<info>Exporting memberships ...</info>');
		
		$membershipQueries = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			$startsAt         = \DateTime::createFromFormat('Y-n-j H:i:s', $memberCsvLine[$memberMapping['starts_at']]);
			$expiresAt        = null;
			if ($memberCsvLine[$memberMapping['expires_at']] !== '') {
				$expiresAt = \DateTime::createFromFormat('Y-n-j H:i:s', $memberCsvLine[$memberMapping['expires_at']]);
			}
			
			$membershipQueries[] = "
				INSERT INTO `membership` SET
				`subscription_id` = 1,
				`contact_id` = (
					SELECT `id`
					FROM `contact`
					WHERE `membership_number` = '".$membershipNumber."'
				),
				`created_by` = 1,
				`price` = '32,50',
				`created_at` = NOW(),
				`starts_at` = '".$startsAt->format('Y-m-d H:i:s')."',
				`expires_at` = ".($expiresAt === null ? 'NULL' : "'".$expiresAt->format('Y-m-d H:i:s')."'")."
				`status` = 'ACTIVE'
			;";
		}
		
		$convertedFileName = 'LendEngineMemberships_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/LendEngineMemberships_ExtraData_'.time().'.sql', implode(PHP_EOL, $membershipQueries));
		
		$output->writeln('<info>Done. ' . count($membershipQueries) . ' SQLs for memberships stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
