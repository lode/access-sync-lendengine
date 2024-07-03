<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\MessageSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
				'Melding.csv',
				'Lid.csv',
			],
			$output,
		);
		
		$messageMapping = [
			'text'       => 'Mld_Oms',
			'contact_id' => 'Mld_Lid_id',
			'created_by' => 'mld_mdw_id_toevoeg',
			'created_at' => ['Mld_GemeldDatum', 'mld_vanafdatum'],
		];
		
		$messageCsvLines = $service->getExportCsv($dataDirectory.'/Melding.csv', (new MessageSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' meldingen');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines). ' leden');
		
		$output->writeln('<info>Exporting contact notes ...</info>');
		
		$membershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$memberId     = $memberCsvLine['lid_id'];
			$memberNumber = $memberCsvLine['lid_key'];
			
			$membershipNumberMapping[$memberId] = $memberNumber;
		}
		
		$contactNoteQueries = [];
		foreach ($messageCsvLines as $messageCsvLine) {
			// skip messages for items
			if ($messageCsvLine[$messageMapping['contact_id']] === '') {
				continue;
			}
			
			// @todo filter on Mld_Mls_id
			
			$text      = trim($messageCsvLine[$messageMapping['text']]);
			$createdBy = $messageCsvLine[$messageMapping['created_by']]; // @todo convert from mld_mdw_id_toevoeg to contact_id
			
			$createdAt = $messageCsvLine[$messageMapping['created_at'][0]];
			if ($createdAt === '') {
				$createdAt = $messageCsvLine[$messageMapping['created_at'][1]];
			}
			$createdAt = \DateTime::createFromFormat('Y-n-j H:i:s', $createdAt);
			
			$memberId         = $messageCsvLine[$messageMapping['contact_id']];
			$membershipNumber = $membershipNumberMapping[$memberId];
			
			$contactNoteQueries[] = "
				INSERT INTO `note` SET
				`created_by` = ".$createdBy."
				`contact_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `contact`
							WHERE `membership_number` = '".$membershipNumber."'
						), 1
					)
				),
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`text` = '".str_replace("'", "\'", $text)."',
				`admin_only` = 1
			;";
		}
		
		$convertedFileName = 'LendEngineContactNotes_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $contactNoteQueries));
		
		$output->writeln('<info>Done. ' . count($contactNoteQueries) . ' SQLs for contact notes stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
