<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Faker\Factory as Faker;
use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'obfuscate-contacts')]
class ObfuscateContactsCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('timestamp', InputArgument::REQUIRED, 'timestamp from LendEngineContacts file in data/ directory');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		$timestamp = $input->getArgument('timestamp');
		$faker = Faker::create('nl_NL');
		$fakerEN = Faker::create();
		
		$expectedHeaders = [
			'First name',
			'Last name',
			'Email',
			'Telephone',
			'Address line 1',
			'City',
			'State',
			'Postcode',
			'custom_notes',
		];
		$contactsCsvLines = $service->getExportCsv($dataDirectory.'/LendEngineContacts_'.$timestamp.'.csv', $expectedHeaders, $csvSeparator="\t");
		
		$obfuscatedCsvLines = [];
		foreach ($contactsCsvLines as $contactCsvLine) {
			$obfuscatedCsvLine = $contactCsvLine;
			
			$obfuscatedCsvLine['First name']     = $obfuscatedCsvLine['First name'] !== '' ?     $faker->firstName()  : '';
			$obfuscatedCsvLine['Last name']      = $obfuscatedCsvLine['Last name'] !== '' ?      $fakerEN->lastName()  : '';
			$obfuscatedCsvLine['Telephone']      = $obfuscatedCsvLine['Telephone'] !== '' ?      $faker->phoneNumber()  : '';
			$obfuscatedCsvLine['Email']          = $obfuscatedCsvLine['Email'] !== '' ?          $faker->safeEmail()  : '';
			$obfuscatedCsvLine['Address line 1'] = $obfuscatedCsvLine['Address line 1'] !== '' ? $faker->streetName(). ' '.$faker->randomNumber(4) : '';
			$obfuscatedCsvLine['City']           = $obfuscatedCsvLine['City'] !== '' ?           $faker->city()  : '';
			$obfuscatedCsvLine['Postcode']       = $obfuscatedCsvLine['Postcode'] !== '' ?       $faker->postcode()  : '';
			$obfuscatedCsvLine['custom_notes']   = $obfuscatedCsvLine['custom_notes'] !== '' ?   $faker->text()  : '';
			
			// Lend Engine has a limit of 25 chars ...
			if (mb_strlen($obfuscatedCsvLine['Last name']) > 25) {
				$obfuscatedCsvLine['Last name'] = mb_substr($obfuscatedCsvLine['Last name'], 0, 25);
			}
			
			$obfuscatedCsvLines[] = $obfuscatedCsvLine;
		}
		
		$obfuscatedCsv = $service->createImportCsv($obfuscatedCsvLines);
		file_put_contents($dataDirectory.'/LendEngineContacts_'.$timestamp.'_obfuscated_'.time().'.csv', $obfuscatedCsv);
		
		return Command::SUCCESS;
	}
}
