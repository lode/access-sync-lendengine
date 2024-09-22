<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class TariffUnitSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'tae_id',
			'tae_code',
			'tae_oms',
			'tae_volgnr',
			'tae_actief',
		];
	}
}
