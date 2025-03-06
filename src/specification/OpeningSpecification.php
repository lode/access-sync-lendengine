<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class OpeningSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'ope_id',
			'ope_datum',
			'ope_plp_id',
			'ope_opt_id',
			'ope_ope_id_terug',
			'ope_ope_id_terug_lang',
			'ope_byzonderheid',
			'ope_actief',
		];
	}
}
