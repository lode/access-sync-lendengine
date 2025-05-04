<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ColorSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'kle_id',
			'kle_oms',
			'kle_actief',
		];
	}
}
