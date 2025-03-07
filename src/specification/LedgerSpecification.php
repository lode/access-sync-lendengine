<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class LedgerSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'kas_id',
			'kas_kat_id',
			'kas_lid_id',
			'kas_oms',
			'kas_bedrag',
			'kas_datumtijd',
			'kas_mdw_id',
			'kas_art_id',
			'kas_ond_id',
			'kas_aantal',
			'kas_afrekendatumtijd',
			'kas_verwijderbaar',
			'kas_totdatumOUD',
			'kas_grpnr',
			'kas_kenmerk',
			'kas_afrekening_kas_id',
			'kas_selectie',
			'kas_asl_id',
			'kas_vanaf',
			'kas_tm',
			'kas_x_aantal',
			'kas_x_bedrag',
			'kas_x_tae_id',
			'kas_res_id',
		];
	}
}
