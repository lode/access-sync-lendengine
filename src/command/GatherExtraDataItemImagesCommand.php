<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\command;

use Lode\AccessSyncLendEngine\service\ConvertCsvService;
use Lode\AccessSyncLendEngine\specification\ArticleSpecification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'gather-extra-data-item-images')]
class GatherExtraDataItemImagesCommand extends Command
{
	protected function configure(): void
	{
		$this->addArgument('imagesDirectoryName', InputArgument::REQUIRED, 'sub directory of data/ with images, using code as file name (e.g. B42.jpg)');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$service             = new ConvertCsvService();
		$dataDirectory       = dirname(dirname(__DIR__)).'/data';
		$imagesDirectoryName = $input->getArgument('imagesDirectoryName');
		$imagesDirectory     = realpath($dataDirectory.'/'.$imagesDirectoryName);
		$exportDirectory     = $dataDirectory.'/export_'.time();
		$supportedExtensions = ['jpg', 'jpeg'];
		
		$service->requireInputCsvs(
			$dataDirectory,
			[
				'Artikel.csv',
			],
			$output,
		);
		
		if ($imagesDirectoryName === '') {
			throw new \Exception('missing images directory name');
		}
		if ($imagesDirectory === false || file_exists($imagesDirectory) === false) {
			throw new \Exception('images directory not found');
		}
		if (str_starts_with($imagesDirectory, $dataDirectory.'/') === false) {
			throw new \Exception('images directory not a sub directory of data/');
		}
		if (glob($imagesDirectory.'/*') === []) {
			throw new \Exception('images directory does not contain any images');
		}
		
		$articleCsvLines = $service->getExportCsv($dataDirectory.'/Artikel.csv', (new ArticleSpecification())->getExpectedHeaders());
		$output->writeln('Imported ' . count($articleCsvLines). ' artikelen');
		
		$output->writeln('<info>Exporting item images ...</info>');
		
		$knownArticleSKUs = [];
		foreach ($articleCsvLines as $articleCsvLine) {
			$articleSku = $articleCsvLine['art_key'];
			
			$knownArticleSKUs[$articleSku] = true;
		}
		
		mkdir($exportDirectory.'/thumbs/', recursive: true);
		mkdir($exportDirectory.'/large/', recursive: true);
		
		$imagePaths = glob($imagesDirectory.'/*');
		$imagePathMapping = [];
		$errors = [
			'without-extension'      => [],
			'multiple-files'         => [],
			'article-code-not-found' => [],
			'non-jpg'                => [],
		];
		foreach ($imagePaths as $imagePath) {
			$fileName = basename($imagePath);
			
			if (str_contains($fileName, '.') === false) {
				$errors['without-extension'][] = 'Found images without extension '.$fileName.', skipped';
				continue;
			}
			
			$articleSku          = substr($fileName, 0, strrpos($fileName, '.'));
			$sanitizedArticleSku = strtolower($articleSku);
			
			if (isset($imagePathMapping[$sanitizedArticleSku]) === true) {
				$existingImageExtension = substr($imagePathMapping[$sanitizedArticleSku], strrpos($imagePathMapping[$sanitizedArticleSku], '.') + 1);
				$newImageExtension      = substr($fileName, strrpos($fileName, '.') + 1);
				
				$existingImageSupported = (in_array($existingImageExtension, $supportedExtensions, strict: true));
				$newImageSupported      = (in_array($newImageExtension, $supportedExtensions, strict: true));
				$preferedImagePath      = ($existingImageSupported === false && $newImageSupported === true) ? $imagePath : $imagePathMapping[$sanitizedArticleSku];
				
				$errors['multiple-files'][] = 'Found multiple images for '.$articleSku.', picked '.$preferedImagePath;
				
				if ($preferedImagePath === $imagePathMapping[$sanitizedArticleSku]) {
					continue;
				}
			}
			
			$imagePathMapping[$sanitizedArticleSku] = $imagePath;
		}
		
		$progressBar = new ProgressBar($output, count($knownArticleSKUs));
		$progressBar->setFormat('debug');
		$progressBar->start();
		
		$itemImagesQueries = [];
		foreach ($knownArticleSKUs as $articleSku => $null) {
			$progressBar->advance();
			
			$sanitizedArticleSku = strtolower($articleSku);
			if (isset($imagePathMapping[$sanitizedArticleSku]) === false) {
				$errors['article-code-not-found'][] = 'No image found for '.$articleSku.', skipped';
				continue;
			}
			
			$imagePath = $imagePathMapping[$sanitizedArticleSku];
			
			// @todo support different extensions
			$fileExtension = strtolower(substr($imagePath, strrpos($imagePath, '.') + 1));
			if (in_array($fileExtension, $supportedExtensions, strict: true) === false) {
				$errors['non-jpg'][] = 'Found non-jpg image for '.$articleSku.', skipped';
				continue;
			}
			
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
							WHERE `sku` = '".$articleSku."'
						), 1000
					)
				),
				`image_name` = '".$imageNewFileName."'
			;";
			
			$itemImagesQueries[] = "
				UPDATE `inventory_item` SET
				`image_name` = '".$imageNewFileName."'
				WHERE `sku` = '".$articleSku."'
			;";
		}
		
		$progressBar->finish();
		$output->writeln('');
		
		$errors = array_filter($errors);
		if ($errors !== []) {
			$output->writeln('<comment>Errors:</comment>');
			foreach ($errors as $reason => $messages) {
				$output->writeln('- '.$reason.': '.count($messages));
			}
			
			if ($this->getHelper('question')->ask($input, $output, new ConfirmationQuestion('<question>Debug? [y/N]</question> ', false)) === true) {
				print_r($errors);
			}
		}
		
		$service->createExportSqls($output, $dataDirectory, '05_ItemImages_ExtraData', $itemImagesQueries, 'item images');
		$output->writeln('New image files stored in ' . $exportDirectory);
		
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
