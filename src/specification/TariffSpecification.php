<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class TariffSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'tar_id',
			'tar_tas_id',
			'tar_code',
			'tar_oms',
			'tar_kat_id',
			'tar_kostenverwijderbaar',
			'tar_volgnr',
			'tar_actief',
			'tar_tae_id',
		];
	}
}
