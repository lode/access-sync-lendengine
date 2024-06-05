<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class WebsiteArticleTypeSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'Id',
			'Actief',
			'Naam',
		];
	}
}
