<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Lode\AccessSyncLendEngine\specification\EmployeeSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\OpeningSpecification;
use Lode\AccessSyncLendEngine\specification\OpeningtimeSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-loans')]
class GatherExtraDataLoansCommand extends Command
{
	private const STATUS_ON_LOAN = 'Uitgeleend';
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
				'ArtikelStatus.csv',
				'ArtikelStatusLogging.csv',
				'Lid.csv',
				'Medewerker.csv',
				'Opening.csv',
				'Openingstijd.csv',
			],
			$output,
		);
		
		$articleStatusMapping = [
			'location_id'   => 'ats_id',
			'location_name' => 'ats_oms',
		];
		$articleStatusLoggingMapping = [
			'item_id'     => 'asl_art_id',
			'location_id' => 'asl_ats_id',
			'created_at'  => 'asl_datum',
			'employee_id' => 'asl_mdw_id',
			'contact_id'  => 'asl_lid_id',
			'loan_out'    => 'asl_ope_id',
			'loan_in'     => 'asl_ope_id_uiterlijkterug',
		];
		$memberMapping = [
			'member_id'         => 'lid_id',
			'contact_id'        => 'lid_vrw_id',
			'membership_number' => 'lid_key',
		];
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines) . ' articles');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$employeeCsvLines = $service->getExportCsv($dataDirectory.'/Medewerker.csv', (new EmployeeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($employeeCsvLines). ' medewerkers');
		
		$openingCsvLines = $service->getExportCsv($dataDirectory.'/Opening.csv', (new OpeningSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($openingCsvLines). ' openingen');
		
		$openingtimeCsvLines = $service->getExportCsv($dataDirectory.'/Openingstijd.csv', (new OpeningtimeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($openingtimeCsvLines). ' openingstijden');
		
		$output->writeln('<info>Exporting loans ...</info>');
		
		$membershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$memberId         = $memberCsvLine[$memberMapping['member_id']];
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			
			$membershipNumberMapping[$memberId] = $membershipNumber;
		}
		
		$responsibleMembershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$memberId      = $memberCsvLine[$memberMapping['member_id']];
			$responsibleId = $memberCsvLine[$memberMapping['contact_id']];
			$memberNumber  = $memberCsvLine[$memberMapping['membership_number']];
			
			$responsibleMembershipNumberMapping[$responsibleId] = $memberNumber;
		}
		
		$employeeMapping = [];
		foreach ($employeeCsvLines as $employeeCsvLine) {
			$employeeId    = $employeeCsvLine['mdw_id'];
			$responsibleId = $employeeCsvLine['mdw_vrw_id'];
			
			$employeeMapping[$employeeId] = $responsibleId;
		}
		
		$openingtimeMapping = [];
		foreach ($openingtimeCsvLines as $openingtimeCsvLine) {
			$openingtimeId    = $openingtimeCsvLine['opt_id'];
			$openingtimeStart = $openingtimeCsvLine['opt_vanaftijd'];
			
			$openingtimeMapping[$openingtimeId] = $openingtimeStart;
		}
		
		$openingMapping = [];
		foreach ($openingCsvLines as $openingCsvLine) {
			$openingId     = $openingCsvLine['ope_id'];
			$openingtimeId = $openingCsvLine['ope_opt_id'];
			
			$openingDate      = substr($openingCsvLine['ope_datum'], 0, strpos($openingCsvLine['ope_datum'], ' '));
			$openingtimeStart = substr($openingtimeMapping[$openingtimeId], strpos($openingtimeMapping[$openingtimeId], ' ')+1);
			$openingStart     = \DateTime::createFromFormat('Y-n-j H:i:s', $openingDate.' '.$openingtimeStart, new \DateTimeZone('Europe/Amsterdam'));
			$openingStart->setTimezone(new \DateTimeZone('UTC'));
			
			$openingMapping[$openingId] = $openingStart;
		}
		
		$locationMapping = [];
		foreach ($articleStatusCsvLines as $articleStatusCsvLine) {
			$locationId   = $articleStatusCsvLine[$articleStatusMapping['location_id']];
			$locationName = $articleStatusCsvLine[$articleStatusMapping['location_name']];
			
			$locationMapping[$locationId] = $locationName;
		}
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		$canonicalArticleMapping = array_flip($canonicalArticleMapping);
		
		$itemLocationDataSet = [];
		foreach ($articleStatusLoggingCsvLines as $articleStatusLoggingCsvLine) {
			$itemId = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['item_id']];
			
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			if (isset($canonicalArticleMapping[$itemId]) === false) {
				continue;
			}
			
			$itemSku        = $canonicalArticleMapping[$itemId];
			$locationId     = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['location_id']];
			$locationName   = $locationMapping[$locationId];
			$loanCreatedAt  = \DateTime::createFromFormat('Y-n-j H:i:s', $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['created_at']], new \DateTimeZone('Europe/Amsterdam'));
			$loanCreatedAt->setTimezone(new \DateTimeZone('UTC'));
			$loanEmployeeId = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['employee_id']];
			$loanContactId  = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['contact_id']];
			$loanOutOpening = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['loan_out']];
			$loanInOpening  = $articleStatusLoggingCsvLine[$articleStatusLoggingMapping['loan_in']];
			
			// only keep the last location for a certain sku
			$itemLocationDataSet[$itemSku] = [
				'itemSku'        => $itemSku,
				'locationName'   => $locationName,
				'loanCreatedAt'  => $loanCreatedAt,
				'loanEmployeeId' => $loanEmployeeId,
				'loanContactId'  => $loanContactId,
				'loanOutOpening' => $loanOutOpening,
				'loanInOpening'  => $loanInOpening,
			];
		}
		
		$loanRowDataSet = [];
		foreach ($itemLocationDataSet as $itemSku => $loanData) {
			$locationName = $loanData['locationName'];
			if ($locationName !== self::STATUS_ON_LOAN) {
				continue;
			}
			
			$loanRowDataSet[$itemSku] = $loanData;
		}
		
		$loanDataSet = [];
		foreach ($loanRowDataSet as $loanRowData) {
			$contact = $loanRowData['loanContactId'];
			$loanOut = $loanRowData['loanOutOpening'];
			$loanKey = $contact.$loanOut;
			
			if (isset($loanDataSet[$loanKey]) === false) {
				$loanDataSet[$loanKey] = [
					'contact'  => $contact,
					'loanOut'  => $loanOut,
					'loanIn'   => $loanRowData['loanInOpening'],
					'created'  => $loanRowData['loanCreatedAt'],
					'employee' => $loanRowData['loanEmployeeId'],
					'items'    => [],
				];
			}
			$loanDataSet[$loanKey]['items'][] = $loanRowData['itemSku'];
		}
		
		$loanQueries = [];
		foreach ($loanDataSet as $loanData) {
			$contactMembershipNumber  = $membershipNumberMapping[$loanData['contact']] ?? null;
			$employeeMembershipNumber = $responsibleMembershipNumberMapping[$employeeMapping[$loanData['employee']]];
			$status                   = 'ACTIVE'; // LE automatically will set to 'OVERDUE' if needed
			$createdAt                = $loanData['created']->format('Y-m-d H:i:s');
			$datetimeOut              = $openingMapping[$loanData['loanOut']]->format('Y-m-d H:i:s');
			$datetimeIn               = $openingMapping[$loanData['loanIn']]->format('Y-m-d H:i:s');
			
			$loanQueries[] = "
			    INSERT
			      INTO `loan`
			       SET `contact_id`            = (
						   SELECT `id`
						     FROM `contact`
						    WHERE `membership_number` = '".$contactMembershipNumber."'
					   ),
			           `status`                = '".$status."',
			           `created_at`            = '".$createdAt."',
			           `created_by`            = (
						   SELECT `id`
						     FROM `contact`
						    WHERE `membership_number` = '".$employeeMembershipNumber."'
					   ),
			           `datetime_out`          = '".$datetimeOut."',
			           `datetime_in`           = '".$datetimeIn."',
			           `collect_from`          = 1,
			           `total_fee`             = 0.00
			;";
			
			$loanQueries[] = "SET @loanId = LAST_INSERT_ID();";
			
			foreach ($loanData['items'] as $itemSku) {
				$loanQueries[] = "
				    INSERT
				      INTO `loan_row`
				       SET `loan_id`           = @loanId,
				           `inventory_item_id` = (
				               SELECT `id`
                                 FROM `inventory_item`
                                WHERE `sku` = '".$itemSku."'
				           ),
				           `product_quantity`  = 1,
				           `due_in_at`         = '".$datetimeIn."',
				           `due_out_at`        = '".$datetimeOut."',
				           `checked_out_at`    = '".$createdAt."',
				           `fee`               = 0.00,
				           `site_from`         = 1,
				           `site_to`           = 1
				;";
			}
			
			$skus = "'".implode("', '", $loanData['items'])."'";
			$loanQueries[] = "UPDATE `inventory_item` SET `current_location_id` = 1 WHERE `sku` IN (".$skus.");";
		}
		
		$service->createExportSqls($output, $dataDirectory, '12_Loan_ExtraData', $loanQueries, 'loans');
		
		return Command::SUCCESS;
	}
}
