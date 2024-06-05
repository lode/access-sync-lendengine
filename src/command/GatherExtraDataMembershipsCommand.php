<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
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
		
		$memberMapping = [
			'starts_at'         => 'lid_vanafdatum',
			'expires_at'        => 'lid_einddatum',
			'membership_number' => 'lid_key',
		];
		
		echo 'Reading members ...'.PHP_EOL;
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		
		$membershipQueries = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			$startsAt         = \DateTime::createFromFormat('Y-n-j H:i:s', $responsibleCsvLine[$responsibleMapping['starts_at']]);
			$expiresAt        = \DateTime::createFromFormat('Y-n-j H:i:s', $responsibleCsvLine[$responsibleMapping['expires_at']]);
			
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
				`starts_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`expires_at` = '".$expiresAt->format('Y-m-d H:i:s')."'
				`status` = 'ACTIVE'
				WHERE `email` = '".$email."'
			;";
		}
		
		file_put_contents($dataDirectory.'/LendEngineMemberships_ExtraData_'.time().'.sql', implode(PHP_EOL, $membershipQueries));
		
		return Command::SUCCESS;
	}
}
