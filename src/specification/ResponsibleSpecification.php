<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ResponsibleSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'vrw_id',
			'vrw_key',
			'vrw_oms',
			'vrw_vrt_id',
			'vrw_toevoegdatum',
			'vrw_organisatie',
			'vrw_achternaam',
			'vrw_voornaam',
			'vrw_tussenvoegsel',
			'vrw_voorletters',
			'vrw_geslacht',
			'vrw_achternaam2',
			'vrw_voornaam2',
			'vrw_tussenvoegsel2',
			'vrw_voorletters2',
			'vrw_geslacht2',
			'vrw_str_id',
			'vrw_huisnr',
			'vrw_postcode',
			'vrw_telefoonnr',
			'vrw_mobieltelnr',
			'vrw_via',
			'vrw_email',
			'vrw_medewerkerambities',
			'vrw_identificatie',
			'vrw_autoincasso',
			'vrw_nationaliteit',
			'vrw_bankgironr',
			'vrw_aanhef',
			'vrw_IBAN',
			'vrw_straat',
			'vrw_plaats',
			'vrw_extra1',
			'vrw_extra2',
			'vrw_extra3',
			'vrw_bijzonderheden',
		];
	}
}
