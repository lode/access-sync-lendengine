<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class PartMutationSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'onm_id',
			'onm_ond_id',
			'onm_lid_id',
			'onm_kapot',
			'onm_aantal',
			'onm_corr_aantal',
			'onm_bedrag',
			'onm_corr_bedrag',
			'onm_datum',
			'onm_corr_datum',
			'onm_oms',
			'onm_corr_oms',
			'onm_mdw_id',
			'onm_corr_mdw_id',
			'onm_definitiefdatum',
		];
	}
}
