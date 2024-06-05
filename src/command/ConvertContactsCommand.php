<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
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
		
		/**
		 * get access file contents
		 */
		$responsibleCsvFilename = $dataDirectory.'/Verantwoordelijke.csv';
		$responsibleMapping = [
			'vrw_id'                 => null,
			'vrw_key'                => null,
			'vrw_oms'                => null,
			'vrw_vrt_id'             => null,
			'vrw_toevoegdatum'       => null,
			'vrw_organisatie'        => null,
			'vrw_achternaam'         => 'Last name',
			'vrw_voornaam'           => 'First name',
			'vrw_tussenvoegsel'      => 'Last name',
			'vrw_voorletters'        => null,
			'vrw_geslacht'           => null,
			'vrw_achternaam2'        => null,
			'vrw_voornaam2'          => null,
			'vrw_tussenvoegsel2'     => null,
			'vrw_voorletters2'       => null,
			'vrw_geslacht2'          => null,
			'vrw_str_id'             => 'Address line 1',
			'vrw_huisnr'             => 'Address line 1',
			'vrw_postcode'           => 'Postcode',
			'vrw_telefoonnr'         => 'Telephone',
			'vrw_mobieltelnr'        => 'Telephone',
			'vrw_via'                => null,
			'vrw_email'              => 'Email',
			'vrw_medewerkerambities' => null,
			'vrw_identificatie'      => null,
			'vrw_autoincasso'        => null,
			'vrw_nationaliteit'      => null,
			'vrw_bankgironr'         => null,
			'vrw_aanhef'             => null,
			'vrw_IBAN'               => null,
			'vrw_straat'             => null,
			'vrw_plaats'             => null,
			'vrw_extra1'             => null,
			'vrw_extra2'             => null,
			'vrw_extra3'             => null,
			'vrw_bijzonderheden'     => 'custom_notes',
		];
		$responsibleExpectedHeaders = array_keys($responsibleMapping);
		
		$streetExpectedHeaders = [
			'str_id',
			'str_plt_id',
			'str_geb_id',
			'str_naam',
			'str_coordinaat',
			'str_invoerdatum',
			'str_pcd_vanaf',
			'str_pcd_tm',
			'str_actief',
		];
		
		$cityExpectedHeaders = [
			'plt_id',
			'plt_naam',
			'plt_actief',
		];
		
		$memberMapping = [
			'lid_id'                 => null,
			'lid_lis_id'             => 'is_active',
			'lid_lit_id'             => null,
			'lid_voornaam'           => null,
			'lid_tussenvoegsel'      => null,
			'lid_achternaam'         => null,
			'lid_voorletters'        => null,
			'lid_voornaam2'          => null,
			'lid_tussenvoegsel2'     => null,
			'lid_achternaam2'        => null,
			'lid_voorletters2'       => null,
			'lid_str_id'             => null,
			'lid_huisnr'             => null,
			'lid_postcode'           => null,
			'lid_telefoonnr'         => null,
			'lid_mobieltelnr'        => null,
			'lid_geslacht'           => null,
			'lid_via'                => null,
			'lid_email'              => null,
			'lid_opzegreden'         => null,
			'lid_contributietot'     => null,
			'lid_telenenaantal'      => null,
			'lid_tespelenaantal'     => null,
			'lid_LedenpasPrinten'    => null,
			'lid_PrintSoort'         => null,
			'lid_vanafdatum'         => null,
			'lid_einddatum'          => null,
			'lid_toevoegdatum'       => null,
			'lid_wijzigdatum'        => null,
			'lid_medewerkerambities' => null,
			'lid_identificatie'      => null,
			'lid_autoincasso'        => null,
			'lid_nationaliteit'      => null,
			'lid_bankgironr'         => null,
			'lid_aanhef'             => null,
			'lid_IBAN'               => null,
			'lid_straat'             => null,
			'lid_plaats'             => null,
			'lid_bijzonderheden'     => null,
			'lid_vrw_id'             => null,
			'lid_kin_id'             => null,
			'lid_key'                => null,
			'lid_oms'                => null,
		];
		$memberExpectedHeaders = array_keys($memberMapping);
		
		echo 'Reading responsibles ...'.PHP_EOL;
		$responsiblesCsvLines = $service->getExportCsv($responsibleCsvFilename, $responsibleExpectedHeaders);
		
		echo 'Reading streets ...'.PHP_EOL;
		$streetCsvFilename = $dataDirectory.'/Straat.csv';
		$streetCsvLines = $service->getExportCsv($streetCsvFilename, $streetExpectedHeaders);
		
		echo 'Reading places ...'.PHP_EOL;
		$cityCsvFilename = $dataDirectory.'/Plaats.csv';
		$cityCsvLines = $service->getExportCsv($cityCsvFilename, $cityExpectedHeaders);
		
		echo 'Reading members ...'.PHP_EOL;
		$memberCsvFilename = $dataDirectory.'/Lid.csv';
		$memberCsvLines = $service->getExportCsv($memberCsvFilename, $memberExpectedHeaders);
		
		$cityMapping = [];
		foreach ($cityCsvLines as $cityCsvLine) {
			$cityMapping[$cityCsvLine['plt_id']] = $cityCsvLine['plt_naam'];
		}
		
		$streetMapping = [];
		foreach ($streetCsvLines as $streetCsvLine) {
			$streetMapping[$streetCsvLine['str_id']] = [
				'streetName' => $streetCsvLine['str_naam'],
				'cityName'   => $cityMapping[$streetCsvLine['str_plt_id']],
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
		foreach ($responsiblesCsvLines as $responsibleCsvLine) {
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
				// skip unmapped values
				if ($contactKey === null) {
					continue;
				}
				
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
				$contactConverted['City'] = $streetMapping[$streetId]['cityName'];
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
		file_put_contents($dataDirectory.'/LendEngineContacts_'.time().'.csv', $convertedCsv);
		
		return Command::SUCCESS;
	}
}
