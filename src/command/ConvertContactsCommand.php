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
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		/**
		 * get access file contents
		 */
		$contactCsvFilename = $dataDirectory.'/Verantwoordelijke.csv';
		$contactMapping = [
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
		$contactExpectedHeaders = array_keys($contactMapping);
		
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
		
		echo 'Reading verantwoordelijken ...'.PHP_EOL;
		$contactCsvLines = $service->getExportCsv($contactCsvFilename, $contactExpectedHeaders);
		
		echo 'Reading straten ...'.PHP_EOL;
		$streetCsvFilename = $dataDirectory.'/Straat.csv';
		$streetCsvLines = $service->getExportCsv($streetCsvFilename, $streetExpectedHeaders);
		
		echo 'Reading plaatsen ...'.PHP_EOL;
		$cityCsvFilename = $dataDirectory.'/Plaats.csv';
		$cityCsvLines = $service->getExportCsv($cityCsvFilename, $cityExpectedHeaders);
		
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
		
		$contactsConverted = [];
		foreach ($contactCsvLines as $contactCsvLine) {
			$contactConverted = [];
		
			/**
			 * simple mapping
			 */
			foreach ($contactMapping as $csvKey => $contactKey) {
				// skip unmapped values
				if ($contactKey === null) {
					continue;
				}
		
				if (isset($contactConverted[$contactKey]) && is_array($contactConverted[$contactKey]) === false) {
					$contactConverted[$contactKey] = [
						$contactConverted[$contactKey],
					];
					$contactConverted[$contactKey][] = $contactCsvLine[$csvKey];
				}
				else {
					$contactConverted[$contactKey] = $contactCsvLine[$csvKey];
				}
			}
		
			/**
			 * converting
			 */
		
			// concat tussenvoegsel and last name
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
