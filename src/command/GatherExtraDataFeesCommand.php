<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusLogSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleStatusSpecification;
use Lode\AccessSyncLendEngine\specification\EmployeeSpecification;
use Lode\AccessSyncLendEngine\specification\LedgerSpecification;
use Lode\AccessSyncLendEngine\specification\LedgerTypeSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\PartSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-fees')]
class GatherExtraDataFeesCommand extends Command
{
	private const string CATEGORY_LATE_IN        = 'Te laat';
	private const string CATEGORY_MISSING_BROKEN = 'Kwijt/Kapot';
	private const string CATEGORY_BACK_REPAIRED  = 'Terug/Gerepareerd';
	
	private const string ITEM_STATUS_DELETE = 'Afgekeurd-Definitief';
	
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
				'Kasboek.csv',
				'KasboekType.csv',
				'Lid.csv',
				'Medewerker.csv',
			],
			$output,
		);
		
		$ledgerMapping = [
			'ledger_id'         => 'kas_id',
			'ledger_type_id'    => 'kas_kat_id',
			'member_id'         => 'kas_lid_id',
			'payment_note'      => 'kas_oms',
			'payment_amount'    => 'kas_bedrag',
			'payment_created'   => 'kas_datumtijd',
			'employee_id'       => 'kas_mdw_id',
			'item_id'           => 'kas_art_id',
			'item_part_id'      => 'kas_ond_id',
			'payment_paid'      => 'kas_afrekendatumtijd',
			'payment_scheduled' => 'kas_verwijderbaar',
		];
		$ledgerTypeMapping = [
			'ledger_type_id'  => 'kat_id',
			'ledger_type_key' => 'kat_oms',
		];
		$memberMapping = [
			'member_id'         => 'lid_id',
			'contact_id'        => 'lid_vrw_id',
			'membership_number' => 'lid_key',
		];
		$articleStatusMapping = [
			'location_id'   => 'ats_id',
			'location_name' => 'ats_oms',
		];
		$articleStatusLoggingMapping = [
			'item_id'     => 'asl_art_id',
			'location_id' => 'asl_ats_id',
		];
		$partMapping = [
			'part_id'          => 'ond_id',
			'article_id'       => 'ond_art_id',
			'part_description' => 'ond_oms',
		];
		
		$ledgerCsvLines = $service->getExportCsv($dataDirectory.'/Kasboek.csv', (new LedgerSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($ledgerCsvLines) . ' ledger rows');
		
		$ledgerTypeCsvLines = $service->getExportCsv($dataDirectory.'/KasboekType.csv', (new LedgerTypeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($ledgerTypeCsvLines) . ' ledger types');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$employeeCsvLines = $service->getExportCsv($dataDirectory.'/Medewerker.csv', (new EmployeeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($employeeCsvLines). ' medewerkers');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$articleStatusCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatus.csv', (new ArticleStatusSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusCsvLines) . ' article statuses');
		
		$articleStatusLoggingCsvLines = $service->getExportCsv($dataDirectory.'/ArtikelStatusLogging.csv', (new ArticleStatusLogSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleStatusLoggingCsvLines) . ' article status logs');
		
		$partCsvLines = $service->getExportCsv($dataDirectory.'/Onderdeel.csv', (new PartSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partCsvLines). ' onderdelen');
		
		$output->writeln('<info>Exporting fees ...</info>');
		
		$ledgerTypes = [];
		foreach ($ledgerTypeCsvLines as $ledgerTypeCsvLine) {
			$ledgerTypeId  = $ledgerTypeCsvLine[$ledgerTypeMapping['ledger_type_id']];
			$ledgerTypeKey = $ledgerTypeCsvLine[$ledgerTypeMapping['ledger_type_key']];
			
			$ledgerTypes[$ledgerTypeId] = $ledgerTypeKey;
		}
		
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
		
		$articleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId   = $articleCsvLine['art_id'];
			$articleSku  = $articleCsvLine['art_key'];
			$articleName = $articleCsvLine['art_naam'];
			
			$articleMapping[$articleId] = [$articleSku, $articleName];
		}
		
		$partDataMapping = [];
		foreach ($partCsvLines as $partCsvLine) {
			$partId          = $partCsvLine[$partMapping['part_id']];
			$articleId       = $partCsvLine[$partMapping['article_id']];
			$partDescription = $partCsvLine[$partMapping['part_description']];
			
			$partDataMapping[$partId] = [$articleId, $partDescription];
		}
		
		$openFeeQueries = [];
		foreach ($ledgerCsvLines as $index => $ledgerCsvLine) {
			$paidAt      = ($ledgerCsvLine[$ledgerMapping['payment_paid']] !== '') ? $ledgerCsvLine[$ledgerMapping['payment_paid']] : null;
			$isScheduled = $paidAt === null && $ledgerCsvLine[$ledgerMapping['payment_scheduled']] === '1';
			$emptyAmount = $ledgerCsvLine[$ledgerMapping['payment_amount']] === '€ 0.00';
			$nonFee      = preg_match('{€ [1-9]}', $ledgerCsvLine[$ledgerMapping['payment_amount']]) === 1;
			
			/**
			 * skip:
			 * - already paid
			 * - membership scheduled for future payment
			 * - vague empty payments
			 * - paying back
			 */
			if ($paidAt !== null || $isScheduled === true || $emptyAmount === true || $nonFee === true) {
				continue;
			}
			
			$createdAt  = \DateTime::createFromFormat('Y-n-j H:i:s', $ledgerCsvLine[$ledgerMapping['payment_created']], new \DateTimeZone('Europe/Amsterdam'));
			$createdAt->setTimezone(new \DateTimeZone('UTC'));
			$createdAt = $createdAt->format('Y-m-d H:i:s');
			
			$category    = $ledgerTypes[$ledgerCsvLine[$ledgerMapping['ledger_type_id']]];
			$memberId    = $ledgerCsvLine[$ledgerMapping['member_id']];
			$amount      = abs((float) substr($ledgerCsvLine[$ledgerMapping['payment_amount']], strpos($ledgerCsvLine[$ledgerMapping['payment_amount']], '-')));
			$description = $ledgerCsvLine[$ledgerMapping['payment_note']];
			$employeeId  = $ledgerCsvLine[$ledgerMapping['employee_id']];
			$itemId      = ($ledgerCsvLine[$ledgerMapping['item_id']] !== '') ? $ledgerCsvLine[$ledgerMapping['item_id']] : null;
			$itemPartId  = ($ledgerCsvLine[$ledgerMapping['item_part_id']] !== '') ? $ledgerCsvLine[$ledgerMapping['item_part_id']] : null;
			
			$employeeMembershipNumber = $responsibleMembershipNumberMapping[$employeeMapping[$employeeId]];
			$contactMembershipNumber  = $membershipNumberMapping[$memberId];
			[$itemSku, $itemName]     = $articleMapping[$itemId] ?? [null, null];
			[$partItemId, $partName]  = $partDataMapping[$itemPartId] ?? [null, null];
			if ($partItemId !== null && $itemSku === null) {
				[$itemSku, $itemName] = $articleMapping[$partItemId] ?? [null, null];
			}
			
			// don't connect to permanently removed items
			if ($itemId !== null && $locationPerItem[$itemId] === self::ITEM_STATUS_DELETE) {
				$note = $category.': '.implode(' / ', array_filter([$itemName, $itemSku, self::ITEM_STATUS_DELETE, $partName, $description]));
				$itemSku  = null;
			} elseif ($partItemId !== null && $locationPerItem[$partItemId] === self::ITEM_STATUS_DELETE) {
				$note = $category.': '.implode(' / ', array_filter([$itemName, $itemSku, self::ITEM_STATUS_DELETE, $partName, $description]));
				$itemSku  = null;
			} elseif ($category === self::CATEGORY_LATE_IN) {
				$note = $category;
			} elseif ($category === self::CATEGORY_MISSING_BROKEN) {
				$note = $category.': '.$partName;
			} else {
				$note = $category.': '.implode(' / ', array_filter([$itemName, $itemSku, $partName, $description]));
				$ledgerId = $ledgerCsvLine[$ledgerMapping['ledger_id']];
				$output->writeln('<comment>Not fully recognized, double check kasboek-id '.$ledgerId.': "'.$note.'" (member '.$contactMembershipNumber.')</comment>');
			}
			
			$itemQuery = ($itemSku !== null) ? "(SELECT `id` FROM `inventory_item` WHERE `sku` = '".$itemSku."')" : 'NULL';
			
			$openFeeQueries[] = "
			    INSERT
			      INTO `payment`
			       SET `created_by`   = (SELECT `id` FROM `contact` WHERE `membership_number` = '".$employeeMembershipNumber."'),
			       	   `item_id`      = ".$itemQuery.",
			       	   `contact_id`   = (SELECT `id` FROM `contact` WHERE `membership_number` = '".$contactMembershipNumber."'),
			       	   `created_at`   = '".$createdAt."',
			       	   `type`         = 'FEE',
			       	   `payment_date` = '".$createdAt."',
			       	   `amount`       = ".$amount.",
			       	   `note`         = '".str_replace("'", "\'", $note)."'
			;";
		}
		
		// cleanup non-imported inactive members
		$openFeeQueries[] = "
		    DELETE
		      FROM `payment`
		     WHERE `contact_id` IS NULL
		;";
		
		$service->createExportSqls($output, $dataDirectory, '09_Fees_ExtraData', $openFeeQueries, 'fees');
		
		return Command::SUCCESS;
	}
}
