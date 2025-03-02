<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\PlaceSpecification;
use Lode\AccessSyncLendEngine\specification\ResponsibleSpecification;
use Lode\AccessSyncLendEngine\specification\StreetSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'convert-contacts')]
class ConvertContactsCommand extends Command
{
	private const MEMBER_STATUS_ACTIVE   = '1';
	private const MEMBER_STATUS_CANCELED = '2';
	private const MEMBER_STATUS_INACTIVE = '3';
	
	// whether or not to allow login after imported in Lend Engine
	// when false no login link will be added to transactional emails
	private const ALLOW_LOGIN = true;
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Verantwoordelijke.csv',
				'Straat.csv',
				'Plaats.csv',
				'Lid.csv',
			],
			$output,
		);
		
		/**
		 * get access file contents
		 */
		$responsibleMapping = [
			'vrw_achternaam'     => 'Last name',
			'vrw_voornaam'       => 'First name',
			'vrw_tussenvoegsel'  => 'Last name',
			'vrw_str_id'         => 'Address',
			'vrw_huisnr'         => 'Address',
			'vrw_postcode'       => 'Postcode',
			'vrw_telefoonnr'     => 'Telephone',
			'vrw_mobieltelnr'    => 'Telephone',
			'vrw_email'          => 'Email address',
		];
		$memberMapping = [
			'lid_lis_id' => 'is_active',
			'lid_key'    => 'membership_number',
		];
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', (new ResponsibleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($responsibleCsvLines) . ' responsibles');
		
		$streetCsvLines = $service->getExportCsv($dataDirectory.'/Straat.csv', (new StreetSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($streetCsvLines) . ' streets');
		
		$placeCsvLines = $service->getExportCsv($dataDirectory.'/Plaats.csv', (new PlaceSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($placeCsvLines) . ' places');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$output->writeln('<info>Exporting contacts ...</info>');
		
		$placeMapping = [];
		foreach ($placeCsvLines as $placeCsvLine) {
			$placeMapping[$placeCsvLine['plt_id']] = $placeCsvLine['plt_naam'];
		}
		
		$streetMapping = [];
		foreach ($streetCsvLines as $streetCsvLine) {
			$streetMapping[$streetCsvLine['str_id']] = [
				'streetName' => $streetCsvLine['str_naam'],
				'placeName'  => $placeMapping[$streetCsvLine['str_plt_id']],
			];
		}
		
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
		
		$skipped = [
			'not-active' => [],
			'no-member'  => [],
		];
		
		$contactsConverted = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			// skip non-active members
			$responsibleId = $responsibleCsvLine['vrw_id'];
			if (isset($responsibleMemberMapping[$responsibleId]) === false) {
				if (in_array($responsibleId, $nonActiveResponsibleIds, strict: true) === true) {
					$skipped['not-active'][] = $responsibleCsvLine;
					continue;
				}
				
				$skipped['no-member'][] = $responsibleCsvLine;
				continue;
			}
			
			$memberCsvLine = $responsibleMemberMapping[$responsibleId];
			
			if ($responsibleCsvLine['vrw_email'] === '') {
				$responsibleCsvLine['vrw_email'] = 'no-email-member-' . $memberCsvLine['lid_key'] . '@example.org';
			}
			
			$contactConverted = [
				'First name'        => null,
				'Last name'         => null,
				'Email address'     => null,
				'Telephone'         => null,
				'Address'           => null,
				'City'              => null,
				'State'             => '-',
				'Postcode'          => null,
				'Membership number' => null,
				'Can log in'        => (self::ALLOW_LOGIN === true) ? '1' : '0',
			];
			
			/**
			 * simple mapping
			 */
			foreach ($responsibleMapping as $responsibleKey => $contactKey) {
				if (isset($contactConverted[$contactKey]) && $contactConverted[$contactKey] !== null && is_array($contactConverted[$contactKey]) === false) {
					$contactConverted[$contactKey] = [
						$contactConverted[$contactKey],
					];
					$contactConverted[$contactKey][] = $responsibleCsvLine[$responsibleKey];
				}
				else {
					$contactConverted[$contactKey] = $responsibleCsvLine[$responsibleKey];
				}
			}
			
			$contactConverted['Membership number'] = $memberCsvLine['lid_key'];
			
			/**
			 * converting
			 */
			
			// put affix ('tussenvoegsel') before last name
			$contactConverted['Last name'] = array_reverse($contactConverted['Last name']);
			// concat last name affix ('tussenvoegsel') and last name
			$contactConverted['Last name'] = trim(implode(' ', $contactConverted['Last name']));
			
			// collecting address info
			$streetId = $contactConverted['Address'][0];
			$houseNumber = $contactConverted['Address'][1];
			
			if (isset($streetMapping[$streetId])) {
				$contactConverted['Address'] = $streetMapping[$streetId]['streetName'].' '.$houseNumber;
				$contactConverted['City'] = $streetMapping[$streetId]['placeName'];
			}
			else {
				$contactConverted['Address'] = '[straat id '.$streetId.']'.' '.$houseNumber;
			}
			
			// phone number
			$contactConverted['Telephone'] = implode(' / ', array_filter($contactConverted['Telephone']));
			
			$contactsConverted[] = $contactConverted;
		}
		
		$skipped = array_filter($skipped);
		if ($skipped !== []) {
			$output->writeln('<comment>Skipped:</comment>');
			foreach ($skipped as $reason => $responsibles) {
				$output->writeln('- '.$reason.': '.count($responsibles));
			}
			
			if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('<question>Debug? [y/N]</question> ', false)) === true) {
				print_r($skipped);
			}
		}
		
		/**
		 * create lend engine contact csv
		 */
		$convertedCsv = $service->createImportCsv($contactsConverted);
		$convertedFileName = 'LendEngine_01_Contacts_'.time().'.csv';
		file_put_contents($dataDirectory.'/'.$convertedFileName, $convertedCsv);
		
		$output->writeln('<info>Done. ' . count($contactsConverted) . ' contacts stored in ' . $convertedFileName . '</info>');
		$output->writeln('<comment>Remember to update duplicate email addresses before importing.</comment>');
		
		return Command::SUCCESS;
	}
}
