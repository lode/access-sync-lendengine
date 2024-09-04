<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ArticleLendPeriodSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'aud_id',
			'aud_oms',
			'aud_aantal',
		];
	}
}
