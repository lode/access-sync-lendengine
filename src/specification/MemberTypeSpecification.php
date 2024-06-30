<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class MemberTypeSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'lit_id',
			'lit_oms',
			'lit_tar_id',
			'lit_uip_id',
			'lit_lenentoegestaan',
			'lit_uitleenkostenfactor',
			'lit_tv',
			'lit_wz',
			'lit_lid_max_artikelaantal',
			'lit_kind_max_artikelaantal',
			'lit_actief',
			'lit_tar_id_borg',
			'lit_tar_id_administratie',
			'lit_nacalculatie',
		];
	}
}
