<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ArticleStatusLogSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'asl_id',
			'asl_art_id',
			'asl_ats_id',
			'asl_mdw_id',
			'asl_lid_id',
			'asl_datum',
			'asl_uitleenInclGroeneStipDoosje',
			'asl_commentaar',
			'asl_ope_id',
			'asl_aantaluitleenuren',
			'asl_tm',
			'asl_aantal',
			'asl_ope_id_uiterlijkterug',
			'asl_lok_id',
			'asl_kenmerk',
			'asl_ats_id_bron',
			'asl_lok_id_bron',
			'asl_lid_id_bron',
			'asl_aantal_mutatie',
			'asl_systeemdatum',
		];
	}
}
