<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class TariffPeriodSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'tap_id',
			'tap_tar_id',
			'tap_vanaf',
			'tap_bedrag',
			'tap_aantal',
			'tap_tae_id',
		];
	}
}
