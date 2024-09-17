<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ResponsibleSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-contact-notes')]
class GatherExtraDataContactNotesCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Verantwoordelijke.csv',
			],
			$output,
		);
		
		$responsibleMapping = [
			'text'          => 'vrw_bijzonderheden',
			'contact_id'    => 'vrw_id',
			'contact_email' => 'vrw_id',
			'created_at'    => 'vrw_toevoegdatum', // closest we can get, date for 'bijzonderheden' is not tracked
		];
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', (new ResponsibleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($responsibleCsvLines). ' verantwoordelijken');
		
		$output->writeln('<info>Exporting contact notes ...</info>');
		
		$contactNoteQueries = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$text = trim($responsibleCsvLine[$responsibleMapping['text']]);
			if ($text === '') {
				continue;
			}
			
			$responsibleId      = $responsibleCsvLine[$responsibleMapping['contact_id']];
			$responsibleEmail   = $responsibleCsvLine[$responsibleMapping['contact_email']];
			$responsibleCreated = $responsibleCsvLine[$responsibleMapping['created_at']];
			$responsibleCreated = \DateTime::createFromFormat('Y-n-j H:i:s', $responsibleCreated);
			
			$contactNoteQueries[] = "
				INSERT INTO `note` SET
				`contact_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `contact`
							WHERE `email` = '".$responsibleEmail."'
						), 1
					)
				),
				`created_at` = '".$responsibleCreated->format('Y-m-d H:i:s')."',
				`text` = '".str_replace("'", "\'", $text)."',
				`admin_only` = 1,
				`status` = 'open'
			;";
		}
		
		$convertedFileName = 'LendEngineContactNotes_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $contactNoteQueries));
		
		$output->writeln('<info>Done. ' . count($contactNoteQueries) . ' SQLs for contact notes stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
