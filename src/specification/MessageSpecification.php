<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class MessageSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'Mld_id',
			'Mld_Mls_id',
			'Mld_Volgnr',
			'Mld_Oms',
			'Mld_Lid_id',
			'Mld_Art_id',
			'Mld_Mdw_id',
			'Mld_GemeldDatum',
			'mld_vanafdatum',
			'mld_tmdatum',
			'mld_mdw_id_toevoeg',
			'mld_mdw_id_gemeld',
		];
	}
}
