<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ArticleStatusSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'ats_id',
			'ats_oms',
			'ats_BronInstelbaar',
			'ats_DoelInstelbaar',
			'ats_commentaarverplicht',
		];
	}
}
