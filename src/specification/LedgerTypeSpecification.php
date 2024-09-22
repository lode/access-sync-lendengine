<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class LedgerTypeSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'kat_id',
			'kat_oms',
			'kat_actief',
			'kat_KasstaatSommatie',
			'kat_kss_id',
			'kat_somfactor',
		];
	}
}
