<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleLendPeriodSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Lode\AccessSyncLendEngine\specification\ArticleTypeSpecification;
use Lode\AccessSyncLendEngine\specification\BrandSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'insight-warnings')]
class InsightWarningsCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service = new ConvertCsvService();
		$dataDirectory = dirname(dirname(__DIR__)).'/data';
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
			],
			$output,
		);
		
		/**
		 * get access file contents
		 */
		$articleMapping = [
			'art_key'                  => 'Code',
			'art_naam'                 => 'Name',
			'art_groenestipdoosje'     => 'Doosje',
			'art_innamewaarschuwing'   => 'Inname => Check-in prompt',
			'art_controlewaarschuwing' => 'Controle (anders dan inname) => Check-in prompt',
			'art_waarschuwing'         => 'Uitlenen => Check-out prompt',
			'art_uitleenwaarschuwing'  => 'Uitleen-info (anders dan uitlenen) => Custom field',
		];
		$warningKeys = [
			'art_innamewaarschuwing',
			'art_controlewaarschuwing',
			'art_waarschuwing',
			'art_uitleenwaarschuwing',
		];
		$warningCategories = [
			'ladekast'   => 'Iets met ladekast',
			'grondplaat' => 'Iets met grondplaat',
			'batterij'   => 'Iets met batterijen',
			'losse'      => 'Iets met een los onderdeel',
			'mist'       => 'Iets is kwijt/kapot',
			'stuk'       => 'Iets is kwijt/kapot',
		];
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$output->writeln('<info>Collecting warnings ...</info>');
		
		$canonicalArticleMapping = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			
			$canonicalArticleMapping[$articleSku] = $articleId;
		}
		
		$itemsCollected = [];
		$warningsCollected = [];
		
		foreach ($warningCategories as $name) {
			$warningsCollected[$name] = [
				'warning' => $name,
				'items'   => [],
			];
		}
		
		foreach ($articleCsvLines as $articleCsvLine) {
			// skip non-last items of duplicate SKUs
			// SKUs are re-used and old articles are made inactive
			$articleId  = $articleCsvLine['art_id'];
			$articleSku = $articleCsvLine['art_key'];
			if ($canonicalArticleMapping[$articleSku] !== $articleId) {
				continue;
			}
			
			$item = [];
			foreach ($articleMapping as $key => $name) {
				$value = $articleCsvLine[$key];
				
				if ($value === '' || $value === '0') {
					$value = null;
				}
				if ($key === 'art_groenestipdoosje' && $value !== null) {
					$value = 'Ja';
				}
				
				// controle and uitleen-info are are interesting if they differ from inname and uitleen
				if ($key === 'art_controlewaarschuwing' && $value === $articleCsvLine['art_innamewaarschuwing']) {
					$value = null;
				}
				if ($key === 'art_uitleenwaarschuwing' && $value === $articleCsvLine['art_waarschuwing']) {
					$value = null;
				}
				
				$item[$name] = $value;
				
				// group by warning as well
				if ($value !== null && in_array($key, $warningKeys, strict: true) === true) {
					if (isset($warningsCollected[$value]) === false) {
						$warningsCollected[$value] = [
							'warning' => $value,
							'items'   => [],
						];
					}
					
					$warningsCollected[$value]['items'][] = $articleSku;
					
					foreach ($warningCategories as $search => $name) {
						if (str_contains($value, $search) === true) {
							$warningsCollected[$name]['items'][] = $articleSku;
						}
					}
				}
			}
			if (count(array_filter($item)) === 2) {
				continue;
			}
			
			$itemsCollected[$articleSku] = $item;
		}
		
		uksort($itemsCollected, function($a, $b) {
			return strnatcmp($a, $b);
		});
		$itemsCollected = array_values($itemsCollected);
		
		$repeatingWarningsCollected = [];
		foreach ($warningsCollected as $warning => $info) {
			// skip when it occurs once or twice, than it probably is not an generic prompt
			$articleSkus = array_unique($info['items']);
			if (count($articleSkus) <= 2) {
				continue;
			}
			
			natsort($articleSkus);
			$repeatingWarningsCollected[] = [
				'Waarschuwing' => $info['warning'],
				'Artikelen'    => implode(', ', $articleSkus),
			];
		}
		
		/**
		 * create insight csvs
		 */
		$convertedCsv = $service->createImportCsv($itemsCollected);
		$convertedFileName = 'LendEngine_11_ItemWithWarnings_'.time().'.csv';
		file_put_contents($dataDirectory.'/'.$convertedFileName, $convertedCsv);
		
		$output->writeln('<info>Done. ' . count($itemsCollected) . ' items with warnings stored in ' . $convertedFileName . '</info>');
		
		$convertedCsv = $service->createImportCsv($repeatingWarningsCollected);
		$convertedFileName = 'LendEngine_11_WarningsWithItems_'.time().'.csv';
		file_put_contents($dataDirectory.'/'.$convertedFileName, $convertedCsv);
		
		$output->writeln('<info>Done. ' . count($repeatingWarningsCollected) . ' warnings with items stored in ' . $convertedFileName . '</info>');
		
		return Command::SUCCESS;
	}
}
