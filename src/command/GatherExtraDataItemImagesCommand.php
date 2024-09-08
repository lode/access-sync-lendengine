<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gather-extra-data-item-images')]
class GatherExtraDataItemImagesCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('imagesDirectoryName', InputArgument::REQUIRED, 'sub directory of data/ with images, using code as file name (e.g. B42.jpg)');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$dataDirectory       = dirname(dirname(__DIR__)).'/data';
		$imagesDirectoryName = $input->getArgument('imagesDirectoryName');
		$imagesDirectory     = realpath($dataDirectory.'/'.$imagesDirectoryName);
		$exportDirectory     = $dataDirectory.'/export_'.time();
		
		if ($imagesDirectoryName === '') {
			throw new \Exception('missing images directory name');
		}
		if (file_exists($imagesDirectory) === false) {
			throw new \Exception('images directory not found');
		}
		if (str_starts_with($imagesDirectory, $dataDirectory.'/') === false) {
			throw new \Exception('images directory not a sub directory of data/');
		}
		
		$imagePaths = glob($imagesDirectory.'/*.jpg');
		if ($imagePaths === []) {
			throw new \Exception('images directory does not contain any images');
		}
		
		$output->writeln('<info>Exporting item images ...</info>');
		
		$itemImagesQueries = [];
		foreach ($imagePaths as $imagePath) {
			$imageArticleSku  = basename($imagePath, '.jpg');
			$imageNewFileName = uniqid().'.jpg';
			
			// @todo white square image (100x100 or 1200x1200) with image in the center
			copy($imagePath, $exportDirectory.'/large/'.$imageNewFileName);
			copy($imagePath, $exportDirectory.'/thumbs/'.$imageNewFileName);
			
			$itemImagesQueries[] = "
				INSERT INTO `images` SET
				`inventory_item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$imageArticleSku."'
						), 1
					)
				),
				`image_name` = ".$imageNewFileName."
			;";
		}
		
		$convertedFileName = 'LendEngineItemImages_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemImagesQueries));
		
		$output->writeln('<info>Done.</info>');
		$output->writeln('- ' . count($itemImagesQueries) . ' SQLs for item custom fields stored in ' . $convertedFileName);
		$output->writeln('- new image files stored in ' . $exportDirectory . ', bundle in a zip file');
		
		return Command::SUCCESS;
	}
}
