<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
		
		$imagePaths = [
			...glob($imagesDirectory.'/*.jpg'),
			...glob($imagesDirectory.'/*.JPG'),
			...glob($imagesDirectory.'/*.jpeg'),
			...glob($imagesDirectory.'/*.JPEG'),
		];
		if ($imagePaths === []) {
			throw new \Exception('images directory does not contain any images');
		}
		
		$output->writeln('<info>Exporting item images ...</info>');
		
		mkdir($exportDirectory.'/thumbs/', recursive: true);
		mkdir($exportDirectory.'/large/', recursive: true);
		
		$progressBar = new ProgressBar($output, count($imagePaths));
		$progressBar->setFormat('debug');
		$progressBar->start();
		
		$itemImagesQueries = [];
		foreach ($imagePaths as $imagePath) {
			$progressBar->advance();
			
			$imageArticleSku  = basename($imagePath);
			$imageArticleSku  = substr($imageArticleSku, 0, strpos($imageArticleSku, '.'));
			$imageNewFileName = uniqid().'.jpg';
			
			$this->convertImage($imagePath, $exportDirectory.'/thumbs/'.$imageNewFileName, 100);
			$this->convertImage($imagePath, $exportDirectory.'/large/'.$imageNewFileName, 1200);
			
			$itemImagesQueries[] = "
				INSERT INTO `image` SET
				`inventory_item_id` = (
					SELECT IFNULL(
						(
							SELECT `id`
							FROM `inventory_item`
							WHERE `sku` = '".$imageArticleSku."'
						), 1000
					)
				),
				`image_name` = '".$imageNewFileName."'
			;";
		}
		
		$progressBar->finish();
		$output->writeln('');
		
		$convertedFileName = 'LendEngineItemImages_ExtraData_'.time().'.sql';
		file_put_contents($dataDirectory.'/'.$convertedFileName, implode(PHP_EOL, $itemImagesQueries));
		
		$output->writeln('<info>Done.</info>');
		$output->writeln('- ' . count($itemImagesQueries) . ' SQLs for item custom fields stored in ' . $convertedFileName);
		$output->writeln('- new image files stored in ' . $exportDirectory . ', bundle in a zip file');
		
		return Command::SUCCESS;
	}
	
	/**
	 * convert to white square image with original image in the center
	 */
	private function convertImage(string $originalPath, string $newPath, int $newSize): void
	{
		if (mime_content_type($originalPath) !== 'image/jpeg') {
			return;
		}
		
		[$originalWidth, $originalHeight] = getimagesize($originalPath);
		
		// determine size and place of original
		$scale     = $newSize / max($originalWidth, $originalHeight);
		$newWidth  = (int) round($originalWidth * $scale);
		$newHeight = (int) round($originalHeight * $scale);
		$offsetX   = (int) round(($newSize - $newWidth) / 2);
		$offsetY   = (int) round(($newSize - $newHeight) / 2);
		
		// create new image and fill with background colour
		$gdImageNew      = imagecreatetruecolor($newSize, $newSize);
		$whiteBackground = imagecolorallocate($gdImageNew, 255, 255, 255);
		imagefill($gdImageNew, 0, 0, $whiteBackground);
		
		// place original image in the center of the new image
		$gdImageOriginal = imagecreatefromjpeg($originalPath);
		imagecopyresampled($gdImageNew, $gdImageOriginal, $offsetX, $offsetY, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
		copy($originalPath, $newPath);
		imagejpeg($gdImageNew, $newPath, quality: 95);
	}
}
