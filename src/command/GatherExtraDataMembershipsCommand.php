<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-memberships')]
class GatherExtraDataMembershipsCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('membershipId', InputArgument::REQUIRED, 'id of the main membership');
		$this->addArgument('membershipPrice', InputArgument::REQUIRED, 'price of the main membership');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		$membershipId = $input->getArgument('membershipId');
		$membershipPrice = $input->getArgument('membershipPrice');
		
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
			else {
				$expiresAt = clone $startsAt;
				$expiresAt->modify('+1 year');
			}
			
			$membershipQueries[] = "
				INSERT INTO `membership` SET
				`subscription_id` = ".$membershipId.",
				`contact_id` = (
					SELECT `id`
					FROM `contact`
					WHERE `membership_number` = '".$membershipNumber."'
				),
				`created_by` = 1,
				`price` = '".$membershipPrice."',
				`created_at` = '".$startsAt->format('Y-m-d H:i:s')."',
				`starts_at` = '".$startsAt->format('Y-m-d H:i:s')."',
				`expires_at` = '".$expiresAt->format('Y-m-d H:i:s')."',
				`status` = 'ACTIVE'
			;";
		}
		
		$convertedFileName = 'LendEngineMemberships_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $membershipQueries));
		
		$output->writeln('<info>Done. ' . count($membershipQueries) . ' SQLs for memberships stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
