<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class MessageKindSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'Mls_id',
			'Mls_Naam',
		];
	}
}
