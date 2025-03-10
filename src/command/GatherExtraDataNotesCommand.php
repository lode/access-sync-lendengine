<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Lode\AccessSyncLendEngine\specification\EmployeeSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\MessageKindSpecification;
use Lode\AccessSyncLendEngine\specification\MessageSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'gather-extra-data-notes')]
class GatherExtraDataNotesCommand extends Command
{
	private const string ITEM_STATUS_DELETE = 'Afgekeurd-Definitief';
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Melding.csv',
				'MeldingSoort.csv',
				'Artikel.csv',
				'ArtikelStatus.csv',
				'ArtikelStatusLogging.csv',
				'Lid.csv',
				'Medewerker.csv',
				'Verantwoordelijke.csv',
			],
			$output,
		);
		
		$memberMapping = [
			'member_id'         => 'lid_id',
			'contact_id'        => 'lid_vrw_id',
			'membership_number' => 'lid_key',
		];
		$responsibleMapping = [
			'contact_id' => 'vrw_id',
		];
		$messageMapping = [
			'kind_id'           => 'Mld_Mls_id',
			'text'              => 'Mld_Oms',
			'contact_id'        => 'Mld_Lid_id',
			'inventory_item_id' => 'Mld_Art_id',
			'created_by'        => 'mld_mdw_id_toevoeg',
			'created_at'        => ['Mld_GemeldDatum', 'mld_vanafdatum'],
			'status_closed'     => 'Mld_GemeldDatum',
			'status_open'       => 'mld_tmdatum',
		];
		$messageKindMapping = [
			'kind_id'   => 'Mls_id',
			'kind_name' => 'Mls_Naam',
		];
		$articleStatusMapping = [
			'location_id'   => 'ats_id',
			'location_name' => 'ats_oms',
		];
		$articleStatusLoggingMapping = [
			'item_id'     => 'asl_art_id',
			'location_id' => 'asl_ats_id',
		];
		
		$messageCsvLines = $service->getExportCsv($dataDirectory.'/Melding.csv', (new MessageSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' meldingen');
		
		$messageKindCsvLines = $service->getExportCsv($dataDirectory.'/MeldingSoort.csv', (new MessageKindSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageKindCsvLines). ' melding soorten');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines). ' leden');
		
		$employeeCsvLines = $service->getExportCsv($dataDirectory.'/Medewerker.csv', (new EmployeeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($employeeCsvLines). ' medewerkers');
		
		$output->writeln('<info>Exporting notes ...</info>');
		
		$messageKindNameMapping = [];
		foreach ($messageKindCsvLines as $messageKindCsvLine) {
			$messageKindId   = $messageKindCsvLine[$messageKindMapping['kind_id']];
			$messageKindName = $messageKindCsvLine[$messageKindMapping['kind_name']];
			
			$messageKindNameMapping[$messageKindId] = $messageKindName;
		}
		
		$memberMembershipNumberMapping      = [];
		$responsibleMembershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$memberId      = $memberCsvLine[$memberMapping['member_id']];
			$responsibleId = $memberCsvLine[$memberMapping['contact_id']];
			$memberNumber  = $memberCsvLine[$memberMapping['membership_number']];
			
			$memberMembershipNumberMapping[$memberId]           = $memberNumber;
			$responsibleMembershipNumberMapping[$responsibleId] = $memberNumber;
		}
		
		$employeeMapping = [];
		foreach ($employeeCsvLines as $employeeCsvLine) {
			$employeeId    = $employeeCsvLine['mdw_id'];
			$responsibleId = $employeeCsvLine['mdw_vrw_id'];
			
			$employeeMapping[$employeeId] = $responsibleId;
		}
		
		$locationMapping = [];
		foreach ($articleStatusCsvLines as $articleStatusCsvLine) {
			$locationId   = $articleStatusCsvLine[$articleStatusMapping['location_id']];
			$locationName = $articleStatusCsvLine[$articleStatusMapping['location_name']];
			
			$locationMapping[$locationId] = $locationName;
		}
		
		$locationPerItem = [];
		foreach ($articleStatusLoggingCsvLines as $articleStatusLoggingCsvLine) {
			$articleId    = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['item_id']];
			$locationId   = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['location_id']];
			$locationName = $locationMapping[$locationId];
			
			// overwrite with the latest location log per article
			$locationPerItem[$articleId] = $locationName;
		}
		
		$articleSkuMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$articleSkuMapping[$articleId] = $articleSku;
		}
		
		$noteQueries = [];
		$failures = [];
		foreach ($messageCsvLines as $messageCsvLine) {
			// filter on kinds meant for contacts
			$text            = trim($messageCsvLine[$messageMapping['text']]);
			$messageKindId   = $messageCsvLine[$messageMapping['kind_id']];
			$messageKindName = $messageKindNameMapping[$messageKindId];
			if ($messageKindName !== 'Lid' && $messageKindName !== 'Artikel') {
				continue;
			}
			
			// skip broken records
			if ($text === '') {
				continue;
			}
			
			if ($messageKindName === 'Lid') {
				// skip for contact references
				if ($messageCsvLine[$messageMapping['contact_id']] === '') {
					throw new \Exception('missing contact id');
				}
				
				$memberId         = $messageCsvLine[$messageMapping['contact_id']];
				if (isset($memberMembershipNumberMapping[$memberId]) === false) {
					$failures[] = $messageCsvLine;
					continue;
				}
				$membershipNumber = $memberMembershipNumberMapping[$memberId];
				
				$relationQuery = "
					`contact_id` = (
						SELECT `id`
						FROM `contact`
						WHERE `membership_number` = '".$membershipNumber."'
					),
				";
			}
			elseif ($messageKindName === 'Artikel') {
				// skip for item references
				if ($messageCsvLine[$messageMapping['inventory_item_id']] === '') {
					throw new \Exception('missing item id');
				}
				
				$articleId  = $messageCsvLine[$messageMapping['inventory_item_id']];
				
				// skip permanently removed
				if ($locationPerItem[$articleId] === self::ITEM_STATUS_DELETE) {
					continue;
				}
				
				$articleSku = $articleSkuMapping[$articleId];
				
				$relationQuery = "
					`inventory_item_id` = (
						SELECT `id`
						FROM `inventory_item`
						WHERE `sku` = '".$articleSku."'
					),
				";
			}
			else {
				throw new \Exception('unsupported message kind');
			}
			
			$employeeId      = $messageCsvLine[$messageMapping['created_by']];
			$responsibleId   = $employeeMapping[$employeeId];
			$createdByNumber = $responsibleMembershipNumberMapping[$responsibleId];
			
			$createdAt = $messageCsvLine[$messageMapping['created_at'][0]];
			if ($createdAt === '') {
				$createdAt = $messageCsvLine[$messageMapping['created_at'][1]];
			}
			$createdAt = \DateTime::createFromFormat('Y-n-j H:i:s', $createdAt, new \DateTimeZone('Europe/Amsterdam'));
			$createdAt->setTimezone(new \DateTimeZone('UTC'));
			
			if ($messageCsvLine[$messageMapping['status_closed']] !== '') {
				$status = "'closed'";
			}
			elseif ($messageCsvLine[$messageMapping['status_open']] !== '') {
				$status = "'open'";
			}
			else {
				// another option is to set these to NULL, but they seem to be written as reminder notes
				// unclear why they are different
				$status = "'open'";
			}
			
			$noteQueries[] = "
				INSERT INTO `note` SET
				`created_by` = (
					SELECT `id`
					FROM `contact`
					WHERE `membership_number` = '".$createdByNumber."'
				),
				{$relationQuery}
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`text` = '".str_replace("'", "\'", $text)."',
				`admin_only` = 1,
				`status` = ".$status."
			;";
		}
		
		if ($failures !== []) {
			$output->writeln('<comment>'.count($failures).' failures</comment>');
			if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('<question>Debug? [y/N]</question> ', false)) === true) {
				print_r($failures);
			}
		}
		
		$service->createExportSqls($output, $dataDirectory, '10_Notes_ExtraData', $noteQueries, 'notes');
		
		return Command::SUCCESS;
	}
}
