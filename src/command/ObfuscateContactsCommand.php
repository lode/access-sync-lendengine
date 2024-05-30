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
		
		$expectedHeaders = [
			'Last name',
			'First name',
			'Address line 1',
			'Postcode',
			'Telephone',
			'Email',
			'custom_notes',
			'City',
		];
		$contactsCsvLines = $service->getExportCsv($dataDirectory.'/LendEngineContacts_'.$timestamp.'.csv', $expectedHeaders, $csvSeparator="\t");
		
		$obfuscatedCsvLines = [];
		foreach ($contactsCsvLines as $contactCsvLine) {
			$obfuscatedCsvLine = $contactCsvLine;
			
			$obfuscatedCsvLine['Last name']      = $obfuscatedCsvLine['Last name'] !== '' ?      $faker->lastName()  : '';
			$obfuscatedCsvLine['First name']     = $obfuscatedCsvLine['First name'] !== '' ?     $faker->firstName()  : '';
			$obfuscatedCsvLine['Address line 1'] = $obfuscatedCsvLine['Address line 1'] !== '' ? $faker->streetName(). ' '.$faker->randomNumber(4) : '';
			$obfuscatedCsvLine['Postcode']       = $obfuscatedCsvLine['Postcode'] !== '' ?       $faker->postcode()  : '';
			$obfuscatedCsvLine['Telephone']      = $obfuscatedCsvLine['Telephone'] !== '' ?      $faker->phoneNumber()  : '';
			$obfuscatedCsvLine['Email']          = $obfuscatedCsvLine['Email'] !== '' ?          $faker->email()  : '';
			$obfuscatedCsvLine['custom_notes']   = $obfuscatedCsvLine['custom_notes'] !== '' ?   $faker->text()  : '';
			$obfuscatedCsvLine['City']           = $obfuscatedCsvLine['City'] !== '' ?           $faker->city()  : '';
			
			$obfuscatedCsvLines[] = $obfuscatedCsvLine;
		}
		
		$obfuscatedCsv = $service->createImportCsv($obfuscatedCsvLines);
		file_put_contents($dataDirectory.'/LendEngineContacts_'.$timestamp.'_obfuscated_'.time().'.csv', $obfuscatedCsv);
		
		return Command::SUCCESS;
	}
}
