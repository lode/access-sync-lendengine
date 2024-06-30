<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * show duplicates:
 * - email address
 * - membership number
 * - multiple members
 * - @todo physical address
 * - @todo phone number
 * - @todo full name
 */

#[AsCommand(name: 'insight-duplicate-contacts')]
class InsightDuplicateContactsCommand extends Command
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
		
		/**
		 * get access file contents
		 */
		$responsibleMapping = [
			'vrw_id'                 => 'Responsible id',
			'vrw_key'                => null,
			'vrw_oms'                => 'Responsible omschrijving',
			'vrw_vrt_id'             => null,
			'vrw_toevoegdatum'       => null,
			'vrw_organisatie'        => null,
			'vrw_achternaam'         => null,
			'vrw_voornaam'           => null,
			'vrw_tussenvoegsel'      => null,
			'vrw_voorletters'        => null,
			'vrw_geslacht'           => null,
			'vrw_achternaam2'        => null,
			'vrw_voornaam2'          => null,
			'vrw_tussenvoegsel2'     => null,
			'vrw_voorletters2'       => null,
			'vrw_geslacht2'          => null,
			'vrw_str_id'             => null,
			'vrw_huisnr'             => null,
			'vrw_postcode'           => null,
			'vrw_telefoonnr'         => null,
			'vrw_mobieltelnr'        => null,
			'vrw_via'                => null,
			'vrw_email'              => 'Responsible email',
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
			'vrw_bijzonderheden'     => null,
		];
		$responsibleExpectedHeaders = array_keys($responsibleMapping);
		
		$memberMapping = [
			'lid_id'                 => 'Lid id',
			'lid_lis_id'             => 'Lid status',
			'lid_lit_id'             => 'Lid type',
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
			'lid_vrw_id'             => 'Lid Responsible koppeling',
			'lid_kin_id'             => null,
			'lid_key'                => 'Lid nummer',
			'lid_oms'                => 'Lid omschrijving',
		];
		$memberExpectedHeaders = array_keys($memberMapping);
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', $responsibleExpectedHeaders);
		$output->writeln('Imported ' . count($responsibleCsvLines) . ' responsibles');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', $memberExpectedHeaders);
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$output->writeln('<info>Exporting contacts ...</info>');
		
		/**
		 * email address
		 */
		$duplicatesEmail = [];
		$holderEmail = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$emailAddress = $responsibleCsvLine['vrw_email'];
			
			$responsibleMapped = [];
			foreach ($responsibleMapping as $csvKey => $mappedKey) {
				// skip unmapped values
				if ($mappedKey === null) {
					continue;
				}
				
				$responsibleMapped[$mappedKey] = $responsibleCsvLine[$csvKey];
			}
			
			// found duplicate
			if (isset($holderEmail[$emailAddress])) {
				if (isset($duplicatesEmail[$emailAddress]) === false) {
					$duplicatesEmail[$emailAddress] = [
						$holderEmail[$emailAddress],
					];
				}
				
				$duplicatesEmail[$emailAddress][] = [
					'mapped'  => $responsibleMapped,
					'csvLine' => $responsibleCsvLine,
				];
			}
			
			$holderEmail[$emailAddress] = [
				'mapped'  => $responsibleMapped,
				'csvLine' => $responsibleCsvLine,
			];
		}
		
		print_r($duplicatesEmail);
		$output->writeln('Duplicates email: '.count($duplicatesEmail));
		
		return Command::SUCCESS;
		
		/**
		 * @todo membership number
		 */
		$duplicatesNumber = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$emailAddress = $memberCsvLine['lid_key'];
			
			if (isset($duplicatesNumber[$emailAddress]) === false) {
				$duplicatesNumber[$emailAddress] = [];
			}
			
			$duplicatesNumber[$emailAddress][] = $memberCsvLine;
		}
		
		print_r($duplicatesNumber);
		$output->writeln('Duplicates number: '.count($duplicatesNumber));
		
		return Command::SUCCESS;
		
		/**
		 * @todo multiple members
		 */
		$duplicatesMember = [];
		$responsibleMappingById = [];
		$memberHolder = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$responsibleId = $responsibleCsvLine['vrw_id'];
			$responsibleMappingById[$responsibleId] = $responsibleCsvLine;
		}
		foreach ($memberCsvLines as $memberCsvLine) {
			$responsibleId = $memberCsvLine['lid_vrw_id'];
			$responsibleCsvLine = $responsibleMappingById[$responsibleId];
			
			// found duplicate
			if (isset($memberHolder[$responsibleId])) {
				if (isset($duplicatesMember[$responsibleId]) === false) {
					$duplicatesMember[$responsibleId] = [];
				}
				
				$duplicatesMember[$responsibleId][] = $memberCsvLine;
			}
			
			$memberHolder[$responsibleId] = $memberCsvLine;
		}
		
		print_r($duplicatesMember);
		$output->writeln('Duplicates member: '.count($duplicatesMember));
		
		return Command::SUCCESS;
	}
}
