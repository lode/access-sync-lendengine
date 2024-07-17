<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class EmployeeSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'mdw_id',
			'mdw_lid_id',
			'mdw_code',
			'mdw_startdatum',
			'mdw_einddatum',
			'mdw_wachtwoord',
			'mdw_geb_id',
			'mdw_fotoinname',
			'mdw_fotocontrole',
			'mdw_fotouitleen',
			'mdw_innamevervolg',
			'mdw_UitleenPopUp',
			'mdw_InnamePopUp',
			'mdw_Enquete',
			'mdw_FotoFile',
			'mdw_inplanbaar',
			'mdw_LidSelectieOpNaam',
			'mdw_UitleenNaEersteInname',
			'mdw_ArtikelSelectieOpNaam',
			'mdw_OnderdelenOpInvoervolgorde',
			'mdw_men_id',
			'mdw_UitleenLidWisselPopUp',
			'mdw_vrw_id',
			'mdw_UitleenLidSelectieVooraf',
			'mdw_UitleenAantal',
			'mdw_NietBeschikbareKnoppenTonen',
			'mdw_LijstschermenViaSneltoetsen',
			'mdw_UnlockedStandaard',
		];
	}
}
