<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class OpeningtimeSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'opt_id',
			'opt_wkd_id',
			'opt_vanaftijd',
			'opt_tmtijd',
			'opt_ddt_id',
			'opt_actueel',
		];
	}
}
