<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class MemberStatusSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'lis_id',
			'lis_oms',
		];
	}
}
