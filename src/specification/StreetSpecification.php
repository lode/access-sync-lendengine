<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class StreetSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'str_id',
			'str_plt_id',
			'str_geb_id',
			'str_naam',
			'str_coordinaat',
			'str_invoerdatum',
			'str_pcd_vanaf',
			'str_pcd_tm',
			'str_actief',
		];
	}
}
