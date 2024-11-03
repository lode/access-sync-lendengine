<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\EmployeeSpecification;
use Lode\AccessSyncLendEngine\specification\MemberSpecification;
use Lode\AccessSyncLendEngine\specification\PartMutationSpecification;
use Lode\AccessSyncLendEngine\specification\PartSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'gather-extra-data-item-part-mutations')]
class GatherExtraDataItemPartMutationsCommand extends Command
{
	private const string TYPE_MISSING_OR_BROKEN = 'missing_or_broken';
	private const string TYPE_FOUND_OR_REPAIRED = 'found_or_repaired';
	private const string TYPE_PERMANENTLY_GONE  = 'permanently_gone';
	
	private const string ACCESS_EXPLANATION_MISSING = 'kwijt';
	private const string ACCESS_EXPLANATION_BROKEN  = 'Kapot, nl:';
	
	private const string DEFAULT_EXPLANATION_MISSING = 'kwijt';
	private const string DEFAULT_EXPLANATION_BROKEN  = 'kapot';
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Onderdeel.csv',
				'OnderdeelMutatie.csv',
				'Artikel.csv',
				'Lid.csv',
				'Medewerker.csv',
			],
			$output,
		);
		
		$partMapping = [
			'part_id'          => 'ond_id',
			'article_id'       => 'ond_art_id',
			'part_description' => ['ond_oms', 'ond_nadereoms'],
			'part_count'       => 'ond_aantal',
		];
		
		$partMutationMapping = [
			'part_id'              => 'onm_ond_id',
			'part_count'           => 'onm_definitiefdatum',
			'mutation_member_id'   => 'onm_lid_id',
			'mutation_explanation' => ['onm_kapot', 'onm_oms', 'onm_corr_oms'],
			'mutation_count'       => ['onm_aantal', 'onm_corr_aantal'],
			'note_created'         => 'onm_datum',
			'note_contact_id'      => 'onm_mdw_id',
			'note_closed'          => ['onm_corr_datum', 'onm_definitiefdatum'],
		];
		
		$memberMapping = [
			'member_id'         => 'lid_id',
			'contact_id'        => 'lid_vrw_id',
			'membership_number' => 'lid_key',
		];
		
		$partCsvLines = $service->getExportCsv($dataDirectory.'/Onderdeel.csv', (new PartSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partCsvLines). ' onderdelen');
		
		$partMutationCsvLines = $service->getExportCsv($dataDirectory.'/OnderdeelMutatie.csv', (new PartMutationSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partMutationCsvLines). ' onderdelenmutaties');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$memberCsvLines = $service->getExportCsv($dataDirectory.'/Lid.csv', (new MemberSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($memberCsvLines) . ' members');
		
		$employeeCsvLines = $service->getExportCsv($dataDirectory.'/Medewerker.csv', (new EmployeeSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($employeeCsvLines). ' medewerkers');
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		$canonicalArticleMapping = array_flip($canonicalArticleMapping);
		
		$partRelatedDataMapping = [];
		foreach ($partCsvLines as $partCsvLine) {
			$partId            = $partCsvLine[$partMapping['part_id']];
			$articleId         = $partCsvLine[$partMapping['article_id']];
			$partOriginalCount = $partCsvLine[$partMapping['part_count']];
			$partDescriptions  = array_intersect_key($partCsvLine, array_flip($partMapping['part_description'])); // ['ond_oms', 'ond_nadereoms']
			$partDescription   = implode(' / ', array_filter($partDescriptions));
			
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			if (isset($canonicalArticleMapping[$articleId]) === false) {
				continue;
			}
			
			$partRelatedDataMapping[$partId] = [
				'itemSku'       => $canonicalArticleMapping[$articleId],
				'originalCount' => (int) $partOriginalCount,
				'description'   => $partDescription,
			];
		}
		
		$membershipNumberMapping = [];
		foreach ($memberCsvLines as $memberCsvLine) {
			$memberId         = $memberCsvLine[$memberMapping['member_id']];
			$membershipNumber = $memberCsvLine[$memberMapping['membership_number']];
			
			$membershipNumberMapping[$memberId] = $membershipNumber;
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
		
		$output->writeln('<info>Validating part mutations input format ...</info>');
		
		foreach ($partMutationCsvLines as $partMutationCsvLine) {
			$mutationExplanations = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_explanation'])); // ['onm_kapot', 'onm_oms', 'onm_corr_oms'],
			$mutationCounts       = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_count'])); // ['onm_aantal', 'onm_corr_aantal'],
			
			if ($mutationCounts['onm_aantal'] !== '-1') {
				$output->writeln('<info>value: '.json_encode($mutationCounts['onm_aantal']).'</info>');
				$output->writeln('<info>csv: '.json_encode($partMutationCsvLine).'</info>');
				throw new \Exception('unsupported (yet) mutation count');
			}
			if (in_array($mutationCounts['onm_corr_aantal'], ['1', '0', ''], strict: true) === false) {
				$output->writeln('<info>value: '.json_encode($mutationCounts['onm_corr_aantal']).'</info>');
				$output->writeln('<info>csv: '.json_encode($partMutationCsvLine).'</info>');
				throw new \Exception('unsupported (yet) mutation correction count');
			}
			if (in_array($mutationExplanations['onm_kapot'], ['1', '0'], strict: true) === false) {
				$output->writeln('<info>value: '.json_encode($mutationExplanations['onm_kapot']).'</info>');
				$output->writeln('<info>csv: '.json_encode($partMutationCsvLine).'</info>');
				throw new \Exception('unsupported (yet) mutation broken toggle');
			}
		}
		
		$output->writeln('<info>Determining part mutations type ...</info>');
		
		$typeCounts = [
			self::TYPE_MISSING_OR_BROKEN => 0,
			self::TYPE_FOUND_OR_REPAIRED => 0,
			self::TYPE_PERMANENTLY_GONE  => 0,
		];
		$parsedMutations = [];
		foreach ($partMutationCsvLines as $partMutationCsvLine) {
			$mutationExplanations = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_explanation'])); // ['onm_kapot', 'onm_oms', 'onm_corr_oms'],
			$mutationCounts       = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_count'])); // ['onm_aantal', 'onm_corr_aantal'],
			
			if ($partMutationCsvLine[$partMutationMapping['part_count']] !== '') {
				$type = self::TYPE_PERMANENTLY_GONE;
			}
			elseif ($mutationCounts['onm_corr_aantal'] !== '') {
				$type = self::TYPE_FOUND_OR_REPAIRED;
			}
			else {
				$type = self::TYPE_MISSING_OR_BROKEN;
			}
			
			$typeCounts[$type]++;
			$parsedMutations[] = [
				'type'    => $type,
				'csvLine' => $partMutationCsvLine,
			];
		}
		
		foreach ($typeCounts as $type => $count) {
			$output->writeln('- '.$type.': '.$count);
		}
		
		$output->writeln('<info>Gathering part mutations data ...</info>');
		
		$partsWithMutations = [];
		foreach ($parsedMutations as ['type' => $type, 'csvLine' => $partMutationCsvLine]) {
			$partId               = $partMutationCsvLine[$partMutationMapping['part_id']];
			$mutationExplanations = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_explanation'])); // ['onm_kapot', 'onm_oms', 'onm_corr_oms'],
			$mutationCounts       = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_count'])); // ['onm_aantal', 'onm_corr_aantal'],
			
			// skip mutations of archived items
			if (isset($partRelatedDataMapping[$partId]) === false) {
				continue;
			}
			
			$partMutation = [
				'type' => $type,
			];
			
			switch ($type) {
				case self::TYPE_MISSING_OR_BROKEN:
					$partMutation['mutationCount'] = 1;
					$mutationExplanation = $this->getCleanMutationExplanation($type, $mutationExplanations);
					if ($mutationExplanation !== null) {
						$partMutation['mutationExplanation'] = $mutationExplanation;
					}
					break;
				
				case self::TYPE_FOUND_OR_REPAIRED:
					// nothing needed for mutations, maybe process for notes
					$mutationExplanation = $this->getCleanMutationExplanation($type, $mutationExplanations);
					if ($mutationExplanation !== null) {
						$partMutation['mutationSummary'] = $mutationExplanation;
					}
					break;
				
				case self::TYPE_PERMANENTLY_GONE:
					$partMutation['count'] = -1;
					break;
				
				default:
					throw new \Exception('missing implementation for determined type '.$type);
			}
			
			$partsWithMutations[$partId][] = $partMutation;
		}
		
		$output->writeln('<info>Combine part mutations ...</info>');
		
		$partsWithMutationRecords = [];
		foreach ($partsWithMutations as $partId => $partMutations) {
			// skip mutations which are all found/repaired and thus don't need a record
			$partMutations = array_filter($partMutations, function(array $partMutation) {
				return ($partMutation['type'] !== self::TYPE_FOUND_OR_REPAIRED);
			});
			if ($partMutations === []) {
				continue;
			}
			
			$partMutationRecord = [
				'count'               => $partRelatedDataMapping[$partId]['originalCount'],
				'mutationCount'       => 0,
				'mutationExplanation' => [],
			];
			
			foreach ($partMutations as $partMutation) {
				if ($partMutation['type'] === self::TYPE_FOUND_OR_REPAIRED) {
					continue;
				}
				
				if (isset($partMutation['count']) === true) {
					$partMutationRecord['count'] += $partMutation['count'];
				}
				if (isset($partMutation['mutationCount']) === true) {
					$partMutationRecord['mutationCount'] += $partMutation['mutationCount'];
				}
				if (isset($partMutation['mutationExplanation']) === true) {
					$partMutationRecord['mutationExplanation'][] = $partMutation['mutationExplanation'];
				}
			}
			
			// add 'x missing' if there is a higher count than there are explanations for
			if (count($partMutationRecord['mutationExplanation']) > 0 && count($partMutationRecord['mutationExplanation']) < $partMutationRecord['mutationCount']) {
				$difference = $partMutationRecord['mutationCount'] - count($partMutationRecord['mutationExplanation']);
				$missingExplanations = array_fill(0, $difference, self::DEFAULT_EXPLANATION_MISSING);
				$partMutationRecord['mutationExplanation'] = [...$partMutationRecord['mutationExplanation'], ...$missingExplanations];
			}
			
			$partMutationRecord['mutationExplanation'] = $this->combineExplanations($partMutationRecord['mutationExplanation']);
			
			$partsWithMutationRecords[$partId] = $partMutationRecord;
		}
		
		$output->writeln('<info>Sort part mutations for efficient queries ...</info>');
		
		uksort($partsWithMutationRecords, function(int $partIdA, int $partIdB) use($partRelatedDataMapping) {
			$itemSkuA = $partRelatedDataMapping[$partIdA]['itemSku'];
			$itemSkuB = $partRelatedDataMapping[$partIdB]['itemSku'];
			
			return ($itemSkuA <=> $itemSkuB);
		});
		
		$output->writeln('<info>Exporting part mutations ...</info>');
		
		$partMutationQueries = [];
		$lastItemSku = null;
		foreach ($partsWithMutationRecords as $partId => $partMutationRecord) {
			$itemSku = $partRelatedDataMapping[$partId]['itemSku'];
			if ($itemSku !== $lastItemSku) {
				$partMutationQueries[] = "SET @itemId = (
				    SELECT `id`
				    FROM `inventory_item`
				    WHERE `sku` = '".$itemSku."'
				);".PHP_EOL;
				
				$lastItemSku = $itemSku;
			}
			
			if ($partMutationRecord['mutationCount'] !== 0) {
				$description         = $partRelatedDataMapping[$partId]['description'];
				$count               = $partMutationRecord['count'];
				$mutationCount       = $partMutationRecord['mutationCount'];
				$mutationExplanation = $partMutationRecord['mutationExplanation'];
				
				$partMutationQueries[] = "UPDATE `item_part` SET
				    `count` = {$count},
				    `mutationCount` = {$mutationCount},
				    `mutationExplanation` = ".($mutationExplanation !== null ? "'".str_replace("'", "\'", $mutationExplanation)."'" : "NULL")."
				    WHERE `inventory_item_id` = @itemId
				    AND `description` = '".str_replace("'", "\'", $description)."'
				;".PHP_EOL;
			}
			elseif ($partMutationRecord['count'] !== $partRelatedDataMapping[$partId]['originalCount']) {
				$description = $partRelatedDataMapping[$partId]['description'];
				$count       = $partMutationRecord['count'];
				
				$partMutationQueries[] = "UPDATE `item_part` SET
				    `count` = {$count}
				    WHERE `inventory_item_id` = @itemId
				    AND `description` = '".str_replace("'", "\'", $description)."'
				;".PHP_EOL;
			}
			else {
				throw new \Exception('unknown case without mutation or original count changed');
			}
		}
		
		$output->writeln('<info>Exporting part mutation notes ...</info>');
		
		$noteQueries = [];
		foreach ($partMutationCsvLines as $partMutationCsvLine) {
			$partId               = $partMutationCsvLine[$partMutationMapping['part_id']];
			$mutationExplanations = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_explanation'])); // ['onm_kapot', 'onm_oms', 'onm_corr_oms'],
			$mutationCounts       = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_count'])); // ['onm_aantal', 'onm_corr_aantal'],
			
			// skip mutations of archived items
			if (isset($partRelatedDataMapping[$partId]) === false) {
				continue;
			}
			
			if ($partMutationCsvLine[$partMutationMapping['part_count']] !== '') {
				// not the focus
				continue;
			}
			elseif ($mutationCounts['onm_corr_aantal'] !== '') {
				// makes no sense when not also logging the missing note at missing date
				continue;
			}
			else {
				$type = self::TYPE_MISSING_OR_BROKEN;
			}
			
			// basics
			$itemSku          = $partRelatedDataMapping[$partId]['itemSku'];
			$memberId         = $partMutationCsvLine[$partMutationMapping['mutation_member_id']];
			$membershipNumber = $membershipNumberMapping[$memberId] ?? null;
			$noteCreatedAt    = \DateTime::createFromFormat('Y-n-j H:i:s', $partMutationCsvLine[$partMutationMapping['note_created']]);
			
			// created by
			$employeeId       = $partMutationCsvLine[$partMutationMapping['note_contact_id']];
			$responsibleId    = $employeeMapping[$employeeId];
			$createdByNumber  = $responsibleMembershipNumberMapping[$responsibleId];
			
			// close it directly?
			$noteClosedFields = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['note_closed'])); // ['onm_corr_datum', 'onm_definitiefdatum'],
			if ($type === self::TYPE_FOUND_OR_REPAIRED) {
				$noteClosedAt = \DateTime::createFromFormat('Y-n-j H:i:s', $noteClosedFields['onm_corr_datum']);
				$noteStatus = 'closed';
			}
			elseif ($type === self::TYPE_PERMANENTLY_GONE) {
				$noteClosedAt = \DateTime::createFromFormat('Y-n-j H:i:s', $noteClosedFields['onm_definitiefdatum']);
				$noteStatus = 'closed';
			}
			elseif ($membershipNumber !== null) {
				$noteStatus   = 'open';
				$noteClosedAt = null;
			}
			else {
				// not caused by member
				$noteStatus   = null;
				$noteClosedAt = null;
			}
			
			// summary
			$partDescription = $partRelatedDataMapping[$partId]['description'];
			if ($type === self::TYPE_PERMANENTLY_GONE) {
				$mutationExplanation = '1 permanently gone';
			}
			else {
				$mutationExplanation = $this->getCleanMutationExplanation($type, $mutationExplanations);
				if ($mutationExplanation === null) {
					$mutationExplanation = '1 '.self::DEFAULT_EXPLANATION_MISSING;
				}
			}
			$noteText = 'Part "'.$partDescription.'" changed: '.$mutationExplanation;
			
			$memberQuery = "";
			if ($membershipNumber !== null) {
				$memberQuery = "
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
			
			$noteQueries[] = "
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
				`inventory_item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$itemSku."'
						), 1
					)
				),
				{$memberQuery}
				`created_at` = '".$noteCreatedAt->format('Y-m-d H:i:s')."',
				`closed_at` = ".($noteClosedAt !== null ? "'".$noteClosedAt->format('Y-m-d H:i:s')."'" : "NULL").",
				`text` = '".str_replace("'", "\'", $noteText)."',
				`admin_only` = 1,
				`status` = ".($noteStatus !== null ? "'".$noteStatus."'" : "NULL")."
			;";
		}
		
		$convertedFileName = 'LendEngineItemPartMutations_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $partMutationQueries));
		$output->writeln('<info>Done. ' . count($partMutationQueries) . ' SQLs for part mutations stored in ' . $convertedFileName . '</info>');
		
		$convertedFileName = 'LendEngineItemPartMutationNotes_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $noteQueries));
		$output->writeln('<info>Done. ' . count($noteQueries) . ' SQLs for part mutation notes stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
	
	/**
	 * @param  string $type one of self::TYPE_* consts
	 * @param  array<'onm_kapot': string, 'onm_oms': string, 'onm_corr_oms': string}  $explanations
	 */
	private function getCleanMutationExplanation(string $type, array $explanations): ?string {
		if ($type === self::TYPE_FOUND_OR_REPAIRED) {
			return $explanations['onm_corr_oms'];
		}
		
		$cleanExplanation = $explanations['onm_oms'];
		
		if ($explanations['onm_kapot'] === '1') {
			$cleanExplanation = trim(str_replace(self::ACCESS_EXPLANATION_BROKEN, '', $cleanExplanation));
			if ($cleanExplanation === '' || strlen($cleanExplanation) < 2) {
				$cleanExplanation = self::DEFAULT_EXPLANATION_BROKEN;
			}
		}
		else {
			$cleanExplanation = trim(str_replace(self::ACCESS_EXPLANATION_MISSING, '', $cleanExplanation));
			$cleanExplanation = ltrim($cleanExplanation, '(');
			$cleanExplanation = rtrim($cleanExplanation, ')');
			if ($cleanExplanation === '' || strlen($cleanExplanation) < 2) {
				$cleanExplanation = null;
			}
		}
		
		return $cleanExplanation;
	}
	
	private function combineExplanations(array $explanations): ?string {
		// no explanations
		if ($explanations === []) {
			return null;
		}
		
		// single explanation
		if (count($explanations) === 1) {
			// for the 'x broken' it is good to add the count
			if ($explanations[0] === self::DEFAULT_EXPLANATION_BROKEN) {
				return $this->addCountToExplanation($explanations[0]);
			}
			
			// otherwise, a single custom description is best without a count
			return $explanations[0];
		}
		
		// single explanation, multiple times
		if (count(array_unique($explanations)) === 1) {
			return $this->addCountToExplanation($explanations[0], count($explanations));
		}
		
		// multiple different explanations
		$groups = [];
		foreach ($explanations as $explanation) {
			if (isset($groups[$explanation]) === false) {
				$groups[$explanation] = 0;
			}
			
			$groups[$explanation]++;
		}
		
		$singleExplanation = [];
		foreach ($groups as $explanation => $count) {
			$singleExplanation[] = $this->addCountToExplanation($explanation, $count);
		}
		
		return implode(', ', $singleExplanation);
	}
	
	private function addCountToExplanation(string $explanation, int $count = 1): string {
		if (str_contains($explanation, (string) $count) === false) {
			$explanation = $count.' '.$explanation;
		}
		
		return $explanation;
	}
}
