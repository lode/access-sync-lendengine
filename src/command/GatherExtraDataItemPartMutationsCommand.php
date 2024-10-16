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
	private const string DEFAULT_EXPLANATION_MISSING = 'kwijt';
	private const string DEFAULT_EXPLANATION_BROKEN  = 'Kapot, nl:';
	
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
		
		$output->writeln('<info>Exporting part mutations ...</info>');
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		$canonicalArticleMapping = array_flip($canonicalArticleMapping);
		
		/*
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
		
		$partsWithMutations = [];
		foreach ($partMutationCsvLines as $index => $partMutationCsvLine) {
			$output->writeln('<error>'.$index.'</error>');
			dump($partMutationCsvLine);
			
			$partId               = $partMutationCsvLine[$partMutationMapping['part_id']]; // 'onm_ond_id',
			$partCount            = $partMutationCsvLine[$partMutationMapping['part_count']]; // 'onm_definitiefdatum',
			$mutationMemberId     = $partMutationCsvLine[$partMutationMapping['mutation_member_id']]; // 'onm_lid_id',
			$mutationExplanations = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_explanation'])); // ['onm_kapot', 'onm_oms', 'onm_corr_oms'],
			$mutationCounts       = array_intersect_key($partMutationCsvLine, array_flip($partMutationMapping['mutation_count'])); // ['onm_aantal', 'onm_corr_aantal'],
			$noteCreated          = $partMutationCsvLine[$partMutationMapping['note_created']]; // 'onm_datum',
			$noteContactId        = $partMutationCsvLine[$partMutationMapping['note_contact_id']]; // 'onm_mdw_id',
			$noteText             = '';
			
			dump([
				'$partId'               => $partId,
				'$partCount'            => $partCount,
				'$mutationMemberId'     => $mutationMemberId,
				'$mutationExplanations' => $mutationExplanations,
				'$mutationCounts'       => $mutationCounts,
				'$noteCreated'          => $noteCreated,
				'$noteContactId'        => $noteContactId,
				'$noteText'             => $noteText,
			]);
			
			if (isset($partsWithMutations[$partId]) === false) {
				$partsWithMutations[$partId] = [
					#'originalCount'       => $partToArticleMapping[$partId]['part_count'],
					'originalMutation'    => 0,
					'mutationCount'       => 0,
					'mutationExplanation' => [],
				];
			}
			
			// missing/broken and returned/repaired
			if (array_sum(array_filter($mutationCounts)) === 0) {
				dump('missing/broken and returned/repaired', $mutationCounts, array_sum(array_filter($mutationCounts)));
				// @todo maybe add historic notes
				continue;
			}
			
			// permanently missing/broken
			elseif ($partCount !== '') {
				dump('permanently missing/broken', $partCount, $mutationCounts, array_sum(array_filter($mutationCounts)));
				$partsWithMutations[$partId]['originalMutation'] += array_sum(array_filter($mutationCounts));
				
				// @todo maybe add historic notes
				#continue;
			}
			
			// broken
			elseif ($mutationExplanations['onm_kapot'] === '1') {
				$mutationCount = abs(array_sum(array_filter($mutationCounts)));
				$partsWithMutations[$partId]['mutationCount'] += $mutationCount;
				$newMutationExplanation = $mutationExplanations['onm_oms'];
				$newMutationExplanation = str_replace(self::DEFAULT_EXPLANATION_BROKEN, '', $newMutationExplanation);
				$newMutationExplanation = trim($newMutationExplanation);
				if (strlen($newMutationExplanation) <= 1) {
					$newMutationExplanation = '';
				}
				if ($newMutationExplanation === '') {
					$newMutationExplanation = 'kapot';
				}
				dump('broken', $mutationCounts, $mutationCount, $mutationExplanations['onm_oms'], $newMutationExplanation);
				if ($newMutationExplanation !== '') {
					$partsWithMutations[$partId]['mutationExplanation'] = [
						...$partsWithMutations[$partId]['mutationExplanation'],
						['count' => $mutationCount, 'explanation' => $newMutationExplanation],
					];
				}
			}
			
			// missing
			else {
				$mutationCount = abs(array_sum(array_filter($mutationCounts)));
				$partsWithMutations[$partId]['mutationCount'] += $mutationCount;
				$newMutationExplanation = $mutationExplanations['onm_oms'];
				$newMutationExplanation = str_replace(self::DEFAULT_EXPLANATION_MISSING, '', $newMutationExplanation);
				$newMutationExplanation = trim($newMutationExplanation);
				$newMutationExplanation = trim($newMutationExplanation, '.');
				if (strlen($newMutationExplanation) <= 1) {
					$newMutationExplanation = '';
				}
				dump('missing', $mutationCounts, $mutationCount, $mutationExplanations['onm_oms'], $newMutationExplanation);
				if ($newMutationExplanation !== '') {
					$partsWithMutations[$partId]['mutationExplanation'] = [
						...$partsWithMutations[$partId]['mutationExplanation'],
						['count' => $mutationCount, 'explanation' => $newMutationExplanation],
					];
				}
			}
			
			dump($partsWithMutations[$partId], $noteText);
			
			#if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('<question>Next? [Y/n]</question> ', true)) === false) {
			#	break;
			#}
			
			if ($noteText !== '') {
				$notes[] = [
					'createdAt' => $noteCreated,
					'createdBy' => $noteContactId,
					'contact'   => $mutationMemberId,
					'item'      => $itemSku,
					'text'      => $noteText,
				];
			}
		}
		
		$partsWithMutations = array_map(function(array $mutationInformation) {
			if ($mutationInformation['mutationExplanation'] === []) {
				unset($mutationInformation['mutationExplanation']);
				unset($mutationInformation['mutationCount']);
				unset($mutationInformation['originalMutation']);
			}
			#elseif ($mutationInformation['mutationCount'] <= 1) {
			#	unset($mutationInformation['mutationCount']);
			#}
			
			if (isset($mutationInformation['mutationExplanation'])) {
				$summary = [];
				$countWithExplanation = 0;
				foreach ($mutationInformation['mutationExplanation'] as $mutationExplanationInformation) {
					if (isset($summary[$mutationExplanationInformation['explanation']]) === false) {
						$summary[$mutationExplanationInformation['explanation']] = 0;
					}
					
					$summary[$mutationExplanationInformation['explanation']] += $mutationExplanationInformation['count'];
					$countWithExplanation += $mutationExplanationInformation['count'];
				}
				
				$finalExplanation = [];
				foreach ($summary as $explanation => $count) {
					$finalExplanation[] = (str_contains($explanation, (string) $count)) ? $explanation : $count.' '.$explanation;
				}
				
				if ($countWithExplanation < $mutationInformation['mutationCount']) {
					$countMissing = ($mutationInformation['mutationCount'] - $countWithExplanation);
					$finalExplanation[] = ($countMissing > 1) ? $countMissing.' missen' : $countMissing.' mist';
				}
				
				$mutationInformation['mutationExplanation'] = implode('; ', $finalExplanation);
			}
			
			return $mutationInformation;
		}, $partsWithMutations);
		
		$partsWithMutations = array_filter($partsWithMutations, function(array $mutationInformation) {
			if (isset($mutationInformation['originalMutation']) && $mutationInformation['originalMutation'] !== 0) {
				return true;
			}
			if (isset($mutationInformation['mutationCount']) && $mutationInformation['mutationCount'] !== 0) {
				return true;
			}
			return false;
		});
		
		$convertedFileName = 'LendEngineItemPartMutations_ExtraData_'.time().'.json';
		file_put_contents($dataDirectory.'/'.$convertedFileName, json_encode($partsWithMutations));
		
		dd('end');
		
		$itemPartMutationQueries = [];
		foreach ($partsWithMutations as $partId => $partWithMutations) {
			$whereStatementInfo = $partToArticleMapping[$partId];
			
			$itemPartMutationQueries[] = "
				UPDATE `item_part`
				SET
				`count` = $count,
				`mutation_count` = ".($hasMutation ? $mutationCount : "NULL").",
				`mutation_explanation` = ".($hasMutation ? "'".$mutationExplanation."'" : "NULL")."
				WHERE
				`item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$itemSku."'
						), 1000
					)
				),
				AND `description` = '".str_replace("'", "\'", $description)."'
			;";
		}
		
		$output->writeln('<info>Done. ' . count($itemPartMutationQueries) . ' SQLs for part mutations stored in:</info>');
		
		$itemPartMutationQueryChunks = array_chunk($itemPartMutationQueries, 2500);
		foreach ($itemPartMutationQueryChunks as $index => $itemPartMutationQueryChunk) {
			$convertedFileName = 'LendEngineItemPartMutations_ExtraData_'.time().'_chunk_'.($index+1).'.sql';
			file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemPartMutationQueryChunk));
			
			$output->writeln('- '.$convertedFileName);
		}
		
		return Command::SUCCESS;
	}
}
