<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class WebsiteArticleSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'Id',
			'Actief',
			'Referentie',
			'Naam',
			'Omschrijving',
			'Samenvatting',
			'CategoryId',
			'MerkId',
			'Afbeelding',
			'Aantal',
			'Prijs',
			'ToonDePrijs',
			'Kenmerk_Naam_Waarde',
			'VerwijderBestaandeAfbeeldingen',
		];
	}
}
