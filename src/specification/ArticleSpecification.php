<?php

declare(strict_types=1);

namespace Lode\AccessSyncLendEngine\specification;

class ArticleSpecification {
	/**
	 * @return string[]
	 */
	public function getExpectedHeaders(): array
	{
		return [
			'art_id',
			'art_att_id',
			'art_nr',
			'art_afschrijfvolgnr',
			'art_naam',
			'art_ats_id',
			'art_aso_id',
			'art_oms',
			'art_mrk_id',
			'art_lev_id',
			'art_2ehands',
			'art_aankoopdatum',
			'art_prijs',
			'art_mat_id',
			'art_aantal',
			'art_lengte',
			'art_hoogte',
			'art_extra',
			'art_puzzeltype',
			'art_tar_id_uitleen',
			'art_waarschuwing',
			'art_printdatum',
			'art_groenestipdoosje',
			'art_reserveerbaar',
			'art_tar_id_reservering',
			'art_onderdeelinofopartikel',
			'art_controledatum',
			'art_minaantalspelers',
			'art_maxaantalspelers',
			'art_vanafleeftijd',
			'art_tmleeftijd',
			'art_ltg_id',
			'art_speelduur',
			'art_aud_id',
			'art_uitleen_lid_id',
			'art_ope_id_uiterlijkterug',
			'art_uitleen_uiterlijkterugdatum_Oud',
			'art_hist_uitleenaantal',
			'art_hist_reserveringaantal',
			'art_tar_id_uitleenextra',
			'art_tar_id_borg',
			'art_tar_id_telaat',
			'art_innamewaarschuwing',
			'art_controlewaarschuwing',
			'art_aaf_id',
			'art_score',
			'art_uitleenwaarschuwing',
			'art_speciaalcontrole',
			'art_identificatie',
			'art_aantal_stukjes',
			'art_etc',
			'art_key',
			'art_btw_id',
			'art_wegen',
			'art_webcatalogus',
			'art_naam2',
			'art_oms2',
		];
	}
}
