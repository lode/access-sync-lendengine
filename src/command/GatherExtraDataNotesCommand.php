<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\EmployeeSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\MessageKindSpecification;
use Lode\AccessSyncLendEngine\specification\MessageSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-notes')]
class GatherExtraDataNotesCommand extends Command
{
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
		];
		$messageKindMapping = [
			'kind_id'   => 'Mls_id',
			'kind_name' => 'Mls_Naam',
		];
		
		$messageCsvLines = $service->getExportCsv($dataDirectory.'/Melding.csv', (new MessageSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageCsvLines). ' meldingen');
		
		$messageKindCsvLines = $service->getExportCsv($dataDirectory.'/MeldingSoort.csv', (new MessageKindSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($messageKindCsvLines). ' melding soorten');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
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
		
		$articleSkuMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$articleSkuMapping[$articleId] = $articleSku;
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
		
		$contactNoteQueries = [];
		foreach ($messageCsvLines as $messageCsvLine) {
			// filter on kinds meant for contacts
			$messageKindId   = $messageCsvLine[$messageMapping['kind_id']];
			$messageKindName = $messageKindNameMapping[$messageKindId];
			if ($messageKindName !== 'Lid' && $messageKindName !== 'Artikel') {
				continue;
			}
			
			if ($messageKindName === 'Lid') {
				// skip for contact references
				if ($messageCsvLine[$messageMapping['contact_id']] === '') {
					throw new \Exception('missing contact id');
				}
				
				$memberId         = $messageCsvLine[$messageMapping['contact_id']];
				$membershipNumber = $memberMembershipNumberMapping[$memberId];
				
				$relationQuery = "
					`contact_id` = (
						SELECT IFNULL(
							(
								SELECT `id`
								FROM `contact`
								WHERE `membership_number` = '".$membershipNumber."'
							), 1
						)
					),
				";
			}
			elseif ($messageKindName === 'Artikel') {
				// skip for item references
				if ($messageCsvLine[$messageMapping['inventory_item_id']] === '') {
					throw new \Exception('missing item id');
				}
				
				$articleId  = $messageCsvLine[$messageMapping['inventory_item_id']];
				$articleSku = $articleSkuMapping[$articleId];
				
				$relationQuery = "
					`inventory_item_id` = (
						SELECT IFNULL(
							(
								SELECT `id`
								FROM `inventory_item`
								WHERE `sku` = '".$articleSku."'
							), 1
						)
					),
				";
			}
			else {
				throw new \Exception('unsupported message kind');
			}
			
			$text            = trim($messageCsvLine[$messageMapping['text']]);
			$employeeId      = $messageCsvLine[$messageMapping['created_by']];
			$responsibleId   = $employeeMapping[$employeeId];
			$createdByNumber = $responsibleMembershipNumberMapping[$responsibleId];
			
			$createdAt = $messageCsvLine[$messageMapping['created_at'][0]];
			if ($createdAt === '') {
				$createdAt = $messageCsvLine[$messageMapping['created_at'][1]];
			}
			$createdAt = \DateTime::createFromFormat('Y-n-j H:i:s', $createdAt);
			
			$contactNoteQueries[] = "
				INSERT INTO `note` SET
				`created_by` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `contact`
							WHERE `membership_number` = '".$createdByNumber."'
						), 1
					)
				),
				{$relationQuery}
				`created_at` = '".$createdAt->format('Y-m-d H:i:s')."',
				`text` = '".str_replace("'", "\'", $text)."',
				`admin_only` = 1,
				`status` = 'open'
			;";
		}
		
		$convertedFileName = 'LendEngineNotes_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $contactNoteQueries));
		
		$output->writeln('<info>Done. ' . count($contactNoteQueries) . ' SQLs for notes stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
