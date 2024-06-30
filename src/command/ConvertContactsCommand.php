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

#[AsCommand(name: 'convert-contacts')]
class ConvertContactsCommand extends Command
{
	private const MEMBER_STATUS_ACTIVE   = 1;
	private const MEMBER_STATUS_CANCELED = 2;
	private const MEMBER_STATUS_INACTIVE = 3;
	
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
			'vrw_str_id'         => 'Address line 1',
			'vrw_huisnr'         => 'Address line 1',
			'vrw_postcode'       => 'Postcode',
			'vrw_telefoonnr'     => 'Telephone',
			'vrw_mobieltelnr'    => 'Telephone',
			'vrw_email'          => 'Email',
			'vrw_bijzonderheden' => 'custom_notes',
		];
		$memberMapping = [
			'lid_lis_id' => 'is_active',
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
		foreach ($memberCsvLines as $memberCsvLine) {
			$responsibleId = $memberCsvLine['lid_vrw_id'];
			
			if (isset($responsibleMemberMapping[$responsibleId])) {
				// @todo figure out which member is the active one
			}
			
			$responsibleMemberMapping[$responsibleId] = $memberCsvLine;
		}
		
		$contactsConverted = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			// skip non-active members
			$responsibleId = $responsibleCsvLine['vrw_id'];
			$memberCsvLine = $responsibleMemberMapping[$responsibleId];
			$memberStatusId = $memberCsvLine['lid_lis_id'];
			if ($memberStatusId !== self::MEMBER_STATUS_ACTIVE) {
				continue;
			}
			
			$contactConverted = [
				'First name'     => null,
				'Last name'      => null,
				'Email'          => null,
				'Telephone'      => null,
				'Address line 1' => null,
				'City'           => null,
				'State'          => '-',
				'Postcode'       => null,
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
			
			/**
			 * converting
			 */
			
			// concat last name affix ('tussenvoegsel') and last name
			$contactConverted['Last name'] = implode(' ', $contactConverted['Last name']);
			
			// collecting address info
			$streetId = $contactConverted['Address line 1'][0];
			$houseNumber = $contactConverted['Address line 1'][1];
			
			if (isset($streetMapping[$streetId])) {
				$contactConverted['Address line 1'] = $streetMapping[$streetId]['streetName'].' '.$houseNumber;
				$contactConverted['City'] = $streetMapping[$streetId]['placeName'];
			}
			else {
				$contactConverted['Address line 1'] = '[straat id '.$streetId.']'.' '.$houseNumber;
			}
			
			// phone number
			$contactConverted['Telephone'] = implode(' / ', array_filter($contactConverted['Telephone']));
			
			$contactsConverted[] = $contactConverted;
		}
		
		/**
		 * create lend engine item csv
		 */
		$convertedCsv = $service->createImportCsv($contactsConverted);
		$convertedFileName = 'LendEngineContacts_'.time().'.csv';
		file_put_contents($dataDirectory.'/'.$convertedFileName, $convertedCsv);
		
		$output->writeln('<info>Done. See ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
