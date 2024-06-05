<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class PlaceSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'plt_id',
			'plt_naam',
			'plt_actief',
		];
	}
}
