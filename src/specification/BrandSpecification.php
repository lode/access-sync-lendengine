<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class BrandSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'mrk_id',
			'mrk_naam',
			'mrk_fab_id',
			'mrk_vermeldenopartikellabel',
			'mrk_actief',
			'mrk_webcatalogus',
		];
	}
}
