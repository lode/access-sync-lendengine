<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
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
				'Lid.csv',
				'Verantwoordelijke.csv',
			],
			$output,
		);
		
		$memberMapping = [
			'contact_id'        => 'lid_vrw_id',
			'membership_number' => 'lid_key',
		];
		$responsibleMapping = [
			'text'          => 'vrw_bijzonderheden',
			'contact_id'    => 'vrw_id',
			'created_at'    => 'vrw_toevoegdatum', // closest we can get, date for 'bijzonderheden' is not tracked
		];
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$responsibleCsvLines = $service->getExportCsv($dataDirectory.'/Verantwoordelijke.csv', (new ResponsibleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($responsibleCsvLines). ' verantwoordelijken');
		
		$output->writeln('<info>Exporting contact notes ...</info>');
		
		$membershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$responsibleId    = $memberCsvLine[$memberMapping['contact_id']];
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			
			$membershipNumberMapping[$responsibleId] = $membershipNumber;
		}
		
		$contactNoteQueries = [];
		foreach ($responsibleCsvLines as $responsibleCsvLine) {
			$text = trim($responsibleCsvLine[$responsibleMapping['text']]);
			if ($text === '') {
				continue;
			}
			
			$responsibleId = $responsibleCsvLine[$responsibleMapping['contact_id']];
			
			// skip contacts without membership, as we have no way of connecting the data
			if (isset($membershipNumberMapping[$responsibleId]) === false) {
				continue;
			}
			
			$membershipNumber   = $membershipNumberMapping[$responsibleId];
			$responsibleCreated = $responsibleCsvLine[$responsibleMapping['created_at']];
			$responsibleCreated = \DateTime::createFromFormat('Y-n-j H:i:s', $responsibleCreated);
			
			$contactNoteQueries[] = "
				INSERT INTO `note` SET
				`contact_id` = (
					SELECT `id`
					FROM `contact`
					WHERE `membership_number` = '".$membershipNumber."'
				),
				`created_at` = '".$responsibleCreated->format('Y-m-d H:i:s')."',
				`text` = '".str_replace("'", "\'", $text)."',
				`admin_only` = 1,
				`status` = 'open'
			;";
		}
		
		$service->createExportSqls($output, $dataDirectory, '10_ContactNotes_ExtraData', $contactNoteQueries, 'contact notes');
		
		return Command::SUCCESS;
	}
}
