<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
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
		];
		
		$partCsvLines = $service->getExportCsv($dataDirectory.'/Onderdeel.csv', (new PartSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partCsvLines). ' onderdelen');
		
		$partMutationCsvLines = $service->getExportCsv($dataDirectory.'/OnderdeelMutatie.csv', (new PartMutationSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($partMutationCsvLines). ' onderdelenmutaties');
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
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
		
		// @todo map part original count for calculating permanently gone types
		// @todo map part descriptions for filling notes
		// @todo map item ids for query where statements
		
		/*
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		$canonicalArticleMapping = array_flip($canonicalArticleMapping);
		
		$partToArticleMapping = [];
		foreach ($partCsvLines as $partCsvLine) {
			$partId    = $partCsvLine[$partMapping['part_id']];
			$articleId = $partCsvLine[$partMapping['article_id']];
			$partCount = $partCsvLine[$partMapping['part_count']];
			
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			if (isset($canonicalArticleMapping[$articleId]) === false) {
				continue;
			}
			
			$itemSku = $canonicalArticleMapping[$articleId];
			$description = implode(' / ', array_filter([
				$partCsvLine[$partMapping['part_description'][0]],
				$partCsvLine[$partMapping['part_description'][1]]
			]));
			
			$partToArticleMapping[$partId] = [
				'item_sku'         => $itemSku,
				'part_count'       => $partCount,
				'part_description' => $description,
			];
		}
		*/
		
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
		
		// @todo sort parts per item
		
		$output->writeln('<info>Exporting part mutations ...</info>');
		
		$partMutationQueries = [];
		foreach ($partsWithMutationRecords as $partId => $partMutationRecord) {
			// @todo determine when to switch item id
			$partMutationQueries[] = "
			    SELECT `id`
			    FROM `inventory_item`
			    WHERE `sku` = '".$itemSku."'
			;";
			
			if ($partMutationRecord['mutationCount'] !== 0) {
				$count               = $partMutationRecord['count'];
				$mutationCount       = $partMutationRecord['mutationCount'];
				$mutationExplanation = $partMutationRecord['mutationExplanation'];
				
				$partMutationQueries[] = "UPDATE `item_part` SET
				    `count` = `count` + {$count},
				    `mutationCount` = `mutationCount` + {$mutationCount},
				    `mutationExplanation` = '".str_replace("'", "\'", $mutationExplanation)."'
				    WHERE `inventory_item_id` = @itemId
				;";
			}
			elseif ($partMutationRecord['count'] !== 0) {
				$count = $partMutationRecord['count'];
				
				$partMutationQueries[] = "UPDATE `item_part` SET
				    `count` = `count` + {$count}
				    WHERE `inventory_item_id` = @itemId
				;";
			}
			else {
				throw new \Exception('unknown case without mutation or original count changed');
			}
		}
		
		$output->writeln('<info>Exporting part mutation notes ...</info>');
		
		foreach ($set as $data) {
			// @todo
			
			$noteCreated   = $partMutationCsvLine[$partMutationMapping['note_created']]; // 'onm_datum',
			$noteContactId = $partMutationCsvLine[$partMutationMapping['note_contact_id']]; // 'onm_mdw_id',
			
			/*
			$notes[] = [
				'createdAt' => $noteCreated,
				'createdBy' => $noteContactId,
				'contact'   => $mutationMemberId,
				'item'      => $itemSku,
				'text'      => $noteText,
			];
			*/
		}
		
		$convertedFileName = 'LendEngineItemPartMutations_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $partMutationQueries));
		$output->writeln('<info>Done. ' . count($partMutationQueries) . ' SQLs for part mutations stored in ' . $convertedFileName . '</info>');
		
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
