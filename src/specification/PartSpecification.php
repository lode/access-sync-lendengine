<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class PartSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'ond_id',
			'ond_art_id',
			'ond_volgnr',
			'ond_kle_id',
			'ond_oms',
			'ond_nadereoms',
			'ond_aantal',
			'ond_apart',
			'ond_InvoerDatum',
			'ond_WijzigDatum',
			'ond_tar_id_min',
			'ond_tar_id_plus',
		];
	}
}
