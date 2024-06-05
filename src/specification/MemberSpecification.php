<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class MemberSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'lid_id',
			'lid_lis_id',
			'lid_lit_id',
			'lid_voornaam',
			'lid_tussenvoegsel',
			'lid_achternaam',
			'lid_voorletters',
			'lid_voornaam2',
			'lid_tussenvoegsel2',
			'lid_achternaam2',
			'lid_voorletters2',
			'lid_str_id',
			'lid_huisnr',
			'lid_postcode',
			'lid_telefoonnr',
			'lid_mobieltelnr',
			'lid_geslacht',
			'lid_via',
			'lid_email',
			'lid_opzegreden',
			'lid_contributietot',
			'lid_telenenaantal',
			'lid_tespelenaantal',
			'lid_LedenpasPrinten',
			'lid_PrintSoort',
			'lid_vanafdatum',
			'lid_einddatum',
			'lid_toevoegdatum',
			'lid_wijzigdatum',
			'lid_medewerkerambities',
			'lid_identificatie',
			'lid_autoincasso',
			'lid_nationaliteit',
			'lid_bankgironr',
			'lid_aanhef',
			'lid_IBAN',
			'lid_straat',
			'lid_plaats',
			'lid_bijzonderheden',
			'lid_vrw_id',
			'lid_kin_id',
			'lid_key',
			'lid_oms',
		];
	}
}
