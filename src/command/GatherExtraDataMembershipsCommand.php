<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\LedgerTypeSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\MembershipTypeSpecification;
use Lode\AccessSyncLendEngine\specification\TariffPeriodSpecification;
use Lode\AccessSyncLendEngine\specification\TariffSpecification;
use Lode\AccessSyncLendEngine\specification\TariffUnitSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-memberships')]
class GatherExtraDataMembershipsCommand extends Command
{
	private const MEMBER_STATUS_ACTIVE   = '1';
	private const MEMBER_STATUS_CANCELED = '2';
	private const MEMBER_STATUS_INACTIVE = '3';
	
	private const CATEGORY_MEMBERSHIP = 'Leenkosten';
	private const CATEGORY_STRIP_CARD = 'Knipkaart';
	private const KNOWN_CATEGORIES = [
		self::CATEGORY_MEMBERSHIP,
		self::CATEGORY_STRIP_CARD,
	];
	private const TARIFF_UNITS = [
		'Maand' => 30.416666667,
		'Jaar' => 365,
		'Keer' => 9999,
	];
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'KasboekType.csv',
				'Lid.csv',
				'LidType.csv',
				'Tarief.csv',
				'TariefEenheid.csv',
				'TariefPeriode.csv',
			],
			$output,
		);
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$membershipTypeCsvLines = $service->getExportCsv($dataDirectory.'/LidType.csv', (new MembershipTypeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($membershipTypeCsvLines) . ' membership types');
		
		$tariffCsvLines = $service->getExportCsv($dataDirectory.'/Tarief.csv', (new TariffSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($tariffCsvLines) . ' tariffs');
		
		$ledgerTypeCsvLines = $service->getExportCsv($dataDirectory.'/KasboekType.csv', (new LedgerTypeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($ledgerTypeCsvLines) . ' ledger types');
		
		$tariffUnitCsvLines = $service->getExportCsv($dataDirectory.'/TariefEenheid.csv', (new TariffUnitSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($tariffUnitCsvLines) . ' tariff units');
		
		$tariffPeriodCsvLines = $service->getExportCsv($dataDirectory.'/TariefPeriode.csv', (new TariffPeriodSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($tariffPeriodCsvLines) . ' tariff periods');
		
		$memberMapping = [
			'is_active'         => 'lid_lis_id',
			'starts_at'         => 'lid_vanafdatum',
			'canceled_at'       => 'lid_einddatum',
			'expires_at'        => 'lid_contributietot',
			'membership_id'     => 'lid_lit_id',
			'membership_number' => 'lid_key',
		];
		$membershipTypeMapping = [
			'type_id'        => 'lit_id',
			'type_name'      => 'lit_oms',
			'type_tariff'    => 'lit_tar_id',
			'type_max_items' => 'lit_lid_max_artikelaantal',
			'type_active'    => 'lit_actief',
		];
		$tariffMapping = [
			'tariff_id'     => 'tar_id',
			'tariff_key'    => 'tar_code',
			'tariff_name'   => 'tar_oms',
			'tariff_ledger' => 'tar_kat_id',
		];
		$ledgerTypeMapping = [
			'ledger_type_id'  => 'kat_id',
			'ledger_type_key' => 'kat_oms',
		];
		$tariffUnitMapping = [
			'tariff_unit_id'  => 'tae_id',
			'tariff_unit_key' => 'tae_code',
		];
		$tariffPeriodMapping = [
			'tariff_period_id'     => 'tap_id',
			'tariff_period_tariff' => 'tap_tar_id',
			'tariff_period_price'  => 'tap_bedrag',
			'tariff_period_count'  => 'tap_aantal',
			'tariff_period_unit'   => 'tap_tae_id',
		];
		
		$output->writeln('<info>Exporting memberships ...</info>');
		
		$ledgerTypes = [];
		foreach ($ledgerTypeCsvLines as $ledgerTypeCsvLine) {
			$ledgerTypeId  = $ledgerTypeCsvLine[$ledgerTypeMapping['ledger_type_id']];
			$ledgerTypeKey = $ledgerTypeCsvLine[$ledgerTypeMapping['ledger_type_key']];
			
			$ledgerTypes[$ledgerTypeId] = $ledgerTypeKey;
		}
		
		$tariffUnits = [];
		foreach ($tariffUnitCsvLines as $tariffUnitCsvLine) {
			$tariffUnitId  = $tariffUnitCsvLine[$tariffUnitMapping['tariff_unit_id']];
			$tariffUnitKey = $tariffUnitCsvLine[$tariffUnitMapping['tariff_unit_key']];
			$tariffUnits[$tariffUnitId] = $tariffUnitKey;
		}
		
		$tariffDetails = [];
		foreach ($tariffCsvLines as $tariffCsvLine) {
			$tariffId = $tariffCsvLine[$tariffMapping['tariff_id']];
			
			$tariffDetails[$tariffId] = [
				'code'     => $tariffCsvLine[$tariffMapping['tariff_key']],
				'name'     => $tariffCsvLine[$tariffMapping['tariff_name']],
				'category' => $ledgerTypes[$tariffCsvLine[$tariffMapping['tariff_ledger']]],
			];
		}
		foreach ($tariffPeriodCsvLines as $tariffPeriodCsvLine) {
			$tariffId = $tariffPeriodCsvLine[$tariffPeriodMapping['tariff_period_tariff']];
			if (isset($tariffDetails[$tariffId]) === false) {
				throw new \Exception('can not match tariff period');
			}
			
			$tariffDetails[$tariffId]['price'] = $tariffPeriodCsvLine[$tariffPeriodMapping['tariff_period_price']];
			$tariffDetails[$tariffId]['count'] = $tariffPeriodCsvLine[$tariffPeriodMapping['tariff_period_count']];
			$tariffDetails[$tariffId]['unit']  = $tariffUnits[$tariffPeriodCsvLine[$tariffPeriodMapping['tariff_period_unit']]];
		}
		$tariffDetails = array_filter($tariffDetails, function($tariff) {
			return (isset($tariff['price']));
		});
		
		$membershipTypeIds = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$membershipTypeId = $memberCsvLine[$memberMapping['membership_id']];
			$membershipTypeIds[$membershipTypeId] = true;
		}
		
		$membershipTypeQueries = [];
		$membershipPriceMapping = [];
		foreach ($membershipTypeCsvLines as $membershipTypeCsvLine) {
			$membershipTypeId = $membershipTypeCsvLine[$membershipTypeMapping['type_id']];
			if (isset($membershipTypeIds[$membershipTypeId]) === false) {
				// not in use
				continue;
			}
			
			$tariffId = $membershipTypeCsvLine[$membershipTypeMapping['type_tariff']];
			$tariff   = $tariffDetails[$tariffId];
			
			$name        = $membershipTypeCsvLine[$membershipTypeMapping['type_name']];
			$description = $tariff['name'] ?? $tariff['code'];
			$price       = str_replace(',', '.', $tariff['price']);
			$max         = $membershipTypeCsvLine[$membershipTypeMapping['type_max_items']];
			$active      = $membershipTypeCsvLine[$membershipTypeMapping['type_active']];
			
			if (in_array($tariff['category'], self::KNOWN_CATEGORIES, strict: true) === false) {
				$category = self::CATEGORY_MEMBERSHIP;
			}
			else {
				// self::CATEGORY_*
				$category = $tariff['category'];
			}
			
			$duration = round($tariff['count'] * self::TARIFF_UNITS[$tariff['unit']]);
			
			$output->write('Membership type: "'.$name.'" ('.$tariff['category'].'), mapped to '.$category);
			$output->writeln(', '.$tariff['count'].'x'.$tariff['unit']);
			
			$membershipTypeQueries[] = "
			    INSERT INTO `membership_type` SET
			    `created_by`  = 1,
			    `name`        = '".$name."',
			    `description` = '".$description."',
			    `price`       = ".$price.",
			    `duration`    = ".$duration.",
			    `discount`    = 0,
			    `created_at`  = NOW(),
			    `self_serve`  = 0,
			    `max_items`   = ".$max.",
			    `is_active`   = ".$active."
			;";
			$membershipTypeQueries[] = "
			    SET @membershipType".$membershipTypeId." = (
			        SELECT `id` FROM `membership_type`
			        WHERE `name` = '".$name."'
			    )
			;";
			$membershipPriceMapping[$membershipTypeId] = $price;
		}
		
		foreach ($memberCsvLines as $memberCsvLine) {
			$memberStatusId = $memberCsvLine[$memberMapping['is_active']];
			if ($memberStatusId !== self::MEMBER_STATUS_ACTIVE) {
				continue;
			}
			
			$membershipTypeId = $memberCsvLine[$memberMapping['membership_id']];
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			$membershipPrice  = $membershipPriceMapping[$membershipTypeId];
			$startsAt         = \DateTime::createFromFormat('Y-n-j H:i:s', $memberCsvLine[$memberMapping['starts_at']], new \DateTimeZone('Europe/Amsterdam'));
			$expiresAt        = \DateTime::createFromFormat('Y-n-j H:i:s', $memberCsvLine[$memberMapping['expires_at']], new \DateTimeZone('Europe/Amsterdam'));
			if ($memberCsvLine[$memberMapping['canceled_at']] !== '') {
				$canceledAt = \DateTime::createFromFormat('Y-n-j H:i:s', $memberCsvLine[$memberMapping['canceled_at']], new \DateTimeZone('Europe/Amsterdam'));
				if ($canceledAt < $expiresAt) {
					$expiresAt = $canceledAt;
				}
			}
			
			$startsAt->setTimezone(new \DateTimeZone('UTC'));
			$expiresAt->setTimezone(new \DateTimeZone('UTC'));
			
			$status = ($expiresAt < new \DateTime('now', new \DateTimeZone('Europe/Amsterdam'))) ? 'EXPIRED' : 'ACTIVE';
			
			$membershipQueries[] = "
				INSERT INTO `membership` SET
				`subscription_id` = @membershipType".$membershipTypeId.",
				`contact_id` = (
					SELECT `id`
					FROM `contact`
					WHERE `membership_number` = '".$membershipNumber."'
				),
				`created_by` = 1,
				`price` = '".$membershipPrice."',
				`created_at` = '".$startsAt->format('Y-m-d H:i:s')."',
				`starts_at` = '".$startsAt->format('Y-m-d H:i:s')."',
				`expires_at` = '".$expiresAt->format('Y-m-d H:i:s')."',
				`status` = '".$status."'
			;";
		}
		
		$membershipQueries[] = "
		    UPDATE `contact` SET
		    `active_membership` = (
		        SELECT `id` FROM `membership`
		        WHERE `contact_id` = `contact`.`id`
		        AND `status` = 'ACTIVE'
		        ORDER BY `id` DESC
		        LIMIT 1
		    )
		;";
		
		$allQueries = [...$membershipTypeQueries, ...$membershipQueries];
		$service->createExportSqls($output, $dataDirectory, '06_Memberships_ExtraData', $allQueries, 'memberships');
		
		return Command::SUCCESS;
	}
}
