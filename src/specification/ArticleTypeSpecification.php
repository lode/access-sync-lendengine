<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ArticleTypeSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'att_id',
			'att_code',
			'att_oms',
			'att_uitleendoorbelasting',
			'att_actief',
			'att_afgifte',
			'att_webcatalogus',
		];
	}
}
