<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\MemberStatusSpecification;
use Lode\AccessSyncLendEngine\specification\MembershipTypeSpecification;
use Lode\AccessSyncLendEngine\specification\ResponsibleSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * show:
 * - email addresses with duplicate contacts
 * - contacts without email address
 */

#[AsCommand(name: 'insight-contacts')]
class InsightContactsCommand extends Command
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
				'LidStatus.csv',
				'LidType.csv',
			],
			$output,
		);
		
		/**
		 * get access file contents
		 */
		$responsibleMapping = [
			'vrw_id'    => 'Responsible id',
			'vrw_oms'   => 'Responsible omschrijving',
			'vrw_email' => 'Responsible email',
		];
		
		$memberMapping = [
			'lid_id'     => 'Lid id',
			'lid_lis_id' => 'Lid status',
			'lid_lit_id' => 'Lid type',
			'lid_vrw_id' => 'Lid Responsible koppeling',
			'lid_key'    => 'Lid nummer',
			'lid_oms'    => 'Lid omschrijving',
		];
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', (new ResponsibleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($responsibleCsvLines) . ' responsibles');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$memberStatusCsvLines = $service->getExportCsv($dataDirectory.'/LidStatus.csv', (new MemberStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberStatusCsvLines) . ' member statuses');
		
		$membershipTypeCsvLines = $service->getExportCsv($dataDirectory.'/LidType.csv', (new MembershipTypeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($membershipTypeCsvLines) . ' membership types');
		
		$output->writeln('<info>Checking contacts ...</info>');
		
		$memberStatusMapping = [];
		foreach ($memberStatusCsvLines as $memberStatusCsvLine) {
			$memberStatusMapping[$memberStatusCsvLine['lis_id']] = $memberStatusCsvLine['lis_oms'];
		}
		
		$membershipTypeMapping = [];
		foreach ($membershipTypeCsvLines as $memberTypeCsvLine) {
			$membershipTypeMapping[$memberTypeCsvLine['lit_id']] = $memberTypeCsvLine['lit_oms'];
		}
		
		$responsibleMappingById = [];
		$memberHolder = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$responsibleId = $responsibleCsvLine['vrw_id'];
			$responsibleMappingById[$responsibleId] = $responsibleCsvLine;
		}
		
		/**
		 * email addresses with duplicate contacts
		 */
		$cases = [];
		$holder = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$responsibleId = $memberCsvLine['lid_vrw_id'];
			if ($responsibleId === '') {
				continue;
			}
			
			$responsibleCsvLine = $responsibleMappingById[$responsibleId];
			$responsibleMapped = [];
			foreach ($responsibleMapping as $csvKey => $mappedKey) {
				$responsibleMapped[$mappedKey] = $responsibleCsvLine[$csvKey];
			}
			
			$memberMapped = [];
			foreach ($memberMapping as $csvKey => $mappedKey) {
				$memberMapped[$mappedKey] = $memberCsvLine[$csvKey];
				
				if ($csvKey === 'lid_lis_id') {
					$memberMapped[$mappedKey] = $memberStatusMapping[$memberMapped[$mappedKey]];
				}
				if ($csvKey === 'lid_lit_id') {
					$memberMapped[$mappedKey] = $membershipTypeMapping[$memberMapped[$mappedKey]];
				}
			}
			
			if ($memberMapped['Lid status'] === 'Inactief') {
				continue;
			}
			
			$emailAddress = $responsibleCsvLine['vrw_email'];
			if ($emailAddress === '') {
				continue;
			}
			
			// found duplicate
			if (isset($holder[$emailAddress])) {
				if (isset($cases[$emailAddress]) === false) {
					$cases[$emailAddress] = [
						$holder[$emailAddress],
					];
				}
				
				$cases[$emailAddress][] = [
					'responsible' => $responsibleMapped,
					'member' => $memberMapped,
				];
			}
			
			$holder[$emailAddress] = [
				'responsible' => $responsibleMapped,
				'member' => $memberMapped,
			];
		}
		
		$info = [];
		foreach ($cases as $emailAddress => $records) {
			$info[] = '- '.$emailAddress.':';
			foreach ($records as $record) {
				$info[] = '  - #'.$record['member']['Lid nummer'].' '.$record['member']['Lid type'].': '.$record['member']['Lid omschrijving'];
			}
		}
		
		$output->writeln('Duplicates email: '.count($cases));
		$output->writeln(implode(PHP_EOL, $info));
		$output->writeln('<comment>These need to be manually corrected before/after importing in `LendEngine_01_Contacts_*.csv`.</comment>');
		if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('<question>Debug? [y/N]</question> ', false)) === true) {
			print_r($cases);
		}
		
		/**
		 * contacts without email address
		 */
		$cases = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			if ($responsibleCsvLine['vrw_email'] === '') {
				$cases[] = $responsibleCsvLine;
			}
		}
		
		$info = [];
		foreach ($cases as $responsibleCsvLine) {
			$info[] = '- #'.$responsibleCsvLine['vrw_id'].': '.$responsibleCsvLine['vrw_oms'];
		}
		
		$output->writeln('Without email: '.count($cases));
		$output->writeln(implode(PHP_EOL, $info));
		$output->writeln('<comment>These will be given a standard email address (no-email-member-<membership-number>@example.org) and can be changed before/after importing.</comment>');
		if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('<question>Debug? [y/N]</question> ', false)) === true) {
			print_r($cases);
		}
		
		return Command::SUCCESS;
	}
}
