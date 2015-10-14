<?php

/**
 * Class CRM_LegacyExport_Generate
 */
class CRM_LegacyExport_Generate {

	/**
	 * Generates CSV files for Memoria and devel.lid2 imports:
	 * - exportdoubleplus_A.del
	 * - kevel_A.del
	 * - exportdoubleplus_H.del
	 * - kevel_H.del
	 *
	 * @param $exportPath Path to add the files to
	 *
	 * @return bool Success
	 */
	public static function generate($exportPath) {

		// Initialisatie
		$genderCodes = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id');

		$membershipTypes = CRM_Member_PseudoConstant::membershipType();
		$membership_type_sp = array_search('Lid SP', $membershipTypes);
		$membership_type_rood = array_search('Lid ROOD', $membershipTypes);
		$membership_type_sprood = array_search('Lid SP en ROOD', $membershipTypes);

		$relationshipTypes = CRM_Core_PseudoConstant::relationshipType('name');
		foreach($relationshipTypes as $rtype) {
			switch($rtype['name_a_b']) {
				case 'sprel_personeelslid_amersfoort_landelijk':
					$relationship_type_am = $rtype['id'];
					break;
				case 'sprel_personeelslid_denhaag_landelijk':
					$relationship_type_dh = $rtype['id'];
					break;
				case 'sprel_personeelslid_brussel_landelijk':
					$relationship_type_br = $rtype['id'];
					break;
				case 'sprel_bestelpersoon_landelijk':
					$relationship_type_bp = $rtype['id'];
					break;
			}
		}

		$endDate = date('Y-m-d', mktime(0, 0, 0, 1, 1, 2015));

		// Contacten en lidmaatschappen

		$sql = "SELECT DISTINCT c.id AS contact_id, c.first_name, c.middle_name, c.last_name, c.nick_name, c.gender_id, c.birth_date, ca.street_name, ca.street_number, ca.street_unit, ca.city, ca.postal_code, ca.country_id, cc.name AS country_name, cc.iso_code AS country_code, ca.state_province_id, ce.email, cp.phone, cpm.phone AS mobile, cca.id AS afdeling_id, cca.display_name AS afdeling, cva.gemeente_24 AS gemeente, ccr.id AS regio_id, ccr.display_name AS regio, ccp.id AS provincie_id, ccp.display_name AS provincie, c.do_not_mail, c.do_not_phone, cm.membership_type_id AS membership_type, cm.start_date AS sp_start_date, cm.end_date AS sp_end_date, cm.status_id, cm.source, cml.reden_6 AS opzegreden, cmw.cadeau_8 AS cadeau, cmw.datum_14 AS cadeaudatum
	FROM civicrm_contact c
	LEFT JOIN civicrm_membership cm ON (c.id = cm.contact_id AND cm.membership_type_id IN ({$membership_type_sp},{$membership_type_sprood},{$membership_type_rood}) AND (cm.status_id IN (1,2) OR (cm.end_date >= '{$endDate}' AND cm.status_id IN (3,4,6,7))))
	LEFT JOIN civicrm_relationship cr ON (c.id = cr.contact_id_a AND cr.relationship_type_id IN ({$relationship_type_am},{$relationship_type_dh},{$relationship_type_br},{$relationship_type_bp}) AND (cr.end_date IS NULL OR cr.end_date > '{$endDate}'))
	LEFT JOIN civicrm_value_migratie_lidmaatschappen_2 cml ON cml.entity_id = cm.id
	LEFT JOIN civicrm_value_welkomstcadeau_sp_3 cmw ON cmw.entity_id = cm.id
	LEFT JOIN civicrm_address ca ON c.id = ca.contact_id AND ca.is_primary = 1
	LEFT JOIN civicrm_value_adresgegevens_12 cva ON ca.id = cva.entity_id
	LEFT JOIN civicrm_country cc ON ca.country_id = cc.id
	LEFT JOIN civicrm_email ce ON c.id = ce.contact_id AND ce.is_primary = 1
	LEFT JOIN civicrm_phone cp ON c.id = cp.contact_id AND cp.phone_type_id = 1
	LEFT JOIN civicrm_phone cpm ON c.id = cpm.contact_id AND cpm.phone_type_id = 2
	LEFT JOIN civicrm_value_geostelsel cvg ON c.id = cvg.entity_id
	LEFT JOIN civicrm_contact cca ON cvg.afdeling = cca.id
	LEFT JOIN civicrm_contact ccr ON cvg.regio = ccr.id
	LEFT JOIN civicrm_contact ccp ON cvg.provincie = ccp.id
	WHERE c.is_deleted = 0 AND (cm.status_id IN (1,2) OR cm.end_date >= '{$endDate}' OR (cr.relationship_type_id IS NOT NULL AND (cr.end_date IS NULL OR cr.end_date >= '{$endDate}')))
	GROUP BY c.id
	";

		$exportDoublePlusA = fopen($exportPath . "/exportdoubleplus_A.del", "w");
		$kevelA            = fopen($exportPath . "/kevel_A.del", "w");

		$dao = CRM_Core_DAO::executeQuery($sql);
		while ($dao->fetch()) {

			fputcsv($exportDoublePlusA, array(
				$dao->contact_id, // Regnr
				$dao->postal_code, // Postcode
				$dao->nick_name, // Voorletters
				$dao->first_name, // Voornaam
				$dao->middle_name, // Tussenvoegsel
				$dao->last_name, // Achternaam
				$dao->afdeling_id, // Afdeling-id
				$dao->email, // Email
				in_array($dao->membership_type, array($membership_type_sp, $membership_type_sprood)) ? 1 : 0, // Is lid
				$dao->street_name, // Straat
				$dao->street_number, // Huisnummer
				$dao->street_unit, // Toevoeging
				$dao->city, // Plaats
				$dao->phone, // Telefoon
				$dao->mobile, // Mobiel
				- 1, // Aangever?
			), ',', '"');
			fputcsv($kevelA, array(
				$dao->contact_id, // Regnr
				$dao->nick_name, // Voorletters
				$dao->first_name, // Voornaam
				$dao->middle_name, // Tussenvoegsel
				$dao->last_name, // Achternaam
				$dao->street_name, // Straat
				$dao->street_number, // Huisnummer
				$dao->street_unit, // Toevoeging
				$dao->postal_code, // Postcode
				$dao->city, // Plaats
				$dao->country_code, // Land
				$dao->birth_date, // Geboortedatum
				substr($genderCodes[$dao->gender_id],0,1), // Gender
				$dao->do_not_mail ? 1 : 0, // 'Statuscode'
				$dao->afdeling_id, // Afdnr
				$dao->afdeling, // Afdnaam
				$dao->gemeente, // Gemeente
				self::mapProvince($dao->provincie), // Provincie
				$dao->regio, // Regio
				'LID', // Was lidcode
				$dao->sp_start_date, // Bdat
				$dao->sp_end_date, // Edat
				$dao->opzegreden, // Opzegreden
				'', // Was rekeningnummer, werd niet gebruikt
				9, // Tribune -> nu onbekend
				$dao->cadeau, // Welkomstcadeau
				$dao->cadeaudatum, // Datum cadeau verzonden
				9999, // Herkomstsegmentnummer -> nu onbekend
				$dao->source, // Herkomstomschrijving (nieuw)
				in_array($dao->membership_type, array($membership_type_sp, $membership_type_sprood)) ? 'T' : 'F', // Lid SP
				in_array($dao->membership_type, array($membership_type_rood, $membership_type_sprood)) ? 'T' : 'F', // Lid ROOD
				$dao->phone, // Telefoon
				$dao->mobile, // Mobiel
				$dao->email, // Email
			), ',', '"');
		}

		fclose($exportDoublePlusA);
		fclose($kevelA);

		// Functietabel

		$sql = "SELECT DISTINCT cr.id, cr.contact_id_a, cr.contact_id_b, cr.start_date, cr.end_date, cr.is_active, crt.name_a_b AS relname, crt.label_a_b AS rellabel
	FROM civicrm_relationship cr
	LEFT JOIN civicrm_relationship_type crt ON cr.relationship_type_id = crt.id
	WHERE crt.contact_type_a = 'Individual' AND cr.is_active = 1 AND (cr.end_date IS NULL OR cr.end_date >= CURDATE())";
		$dao = CRM_Core_DAO::executeQuery($sql);

		$exportDoublePlusH = fopen($exportPath . "/exportdoubleplus_H.del", "w");
		$kevelH            = fopen($exportPath . "/kevel_H.del", "w");

		while ($dao->fetch()) {

			$funcAbbrev = self::mapFuncAbbrev($dao->relname);

			fputcsv($exportDoublePlusH, array(
				$dao->contact_id_a, // Regnr
				$dao->start_date, // Bdat
				$funcAbbrev, // Afkorting
			), ',', '"');
			fputcsv($kevelH, array(
				$dao->contact_id_a, // Regnr
				$dao->start_date, // Bdat
				$dao->end_date, // Edat
				$funcAbbrev, // Afkorting
				$dao->rellabel, // Funcnaam
			), ',', '"');
		}

		fclose($exportDoublePlusH);
		fclose($kevelH);

		/* Let op, sowieso geschrapt: kevel_R (ROOD-leden apart), kevel_S (herkomstnummers),
		kevel_F en exportdoubleplus_F (functielijst) en exportdoubleplus_L (landcodes). */

		return true;
	}

	/**
	 * Maps CiviCRM relationship name to legacy function code
	 *
	 * @param string $relname Relationship name
	 *
	 * @return string Function code
	 */
	private static function mapFuncAbbrev($relname) {

		switch ($relname) {
			case 'sprel_voorzitter_afdeling':
				return 'V';
				break;
			case 'sprel_vervangendvoorzitter_afdeling':
				return 'VV';
				break;
			case 'sprel_organisatiesecretaris_afdeling':
				return 'OS';
				break;
			case 'sprel_penningmeester_afdeling':
				return 'PM';
				break;
			case 'sprel_bestuurslid_afdeling':
				return 'B';
				break;
			case 'sprel_kaderlid_afdeling':
				return 'AM';
				break;
			case 'sprel_ROOD_Contactpersoon_afdeling':
				return 'JV';
				break;
			case 'sprel_scholingsverantwoordelijke_afdeling':
				return 'D';
				break;
			case 'sprel_opnaartweehonderd_afdeling':
				return 'OPV';
				break;
			case 'sprel_webmaster_afdeling':
				return 'WM';
				break;
			case 'sprel_hulpdienstmedewerker_afdeling':
				return 'H';
				break;
			case 'sprel_verantwoordelijke_ledenadministratie_afdeling':
				return 'MM';
				break;
			case 'sprel_bestelpersoon_afdeling':
				return 'BP';
				break;
			case 'sprel_bestelpersoon_fractie':
			case 'sprel_bestelpersoon_provincie':
			case 'sprel_bestelpersoon_landelijk':
				return 'FMB';
				break;
			case 'sprel_fractievoorzitter_afdeling':
				return 'FG';
				break;
			case 'sprel_fractievoorzitter_provincie':
				return 'FP';
				break;
			case 'sprel_fractievoorzitter_landelijk':
				return 'F';
				break;
			case 'sprel_fractieraadslid_afdeling':
				return 'YR';
				break;
			case 'sprel_deelraadslid_afdeling':
				return 'YD';
				break;
			case 'sprel_wethouder_afdeling':
				return 'WH';
				break;
			case 'sprel_statenlid_provincie':
				return 'S';
				break;
			case 'sprel_gedeputeerde_provincie':
				return 'GS';
				break;
			case 'sprel_tweede_kamerlid_landelijk':
				return 'TK';
				break;
			case 'sprel_eerste_kamerlid_landelijk':
				return 'EK';
				break;
			case 'sprel_europarlementarier_landelijk':
				return 'EP';
				break;
			case 'sprel_partijbestuurslid_landelijk':
				return 'OD';
				break;
			case 'sprel_liddagelijksbestuur_landelijk':
				return 'DB';
				break;
			case 'sprel_regiobestuurder_landelijk':
				return 'OR';
				break;
			case 'sprel_personeelslid_amersfoort_landelijk':
				return 'P_R';
				break;
			case 'sprel_personeelslid_denhaag_landelijk':
				return 'P_DH';
				break;
			case 'sprel_personeelslid_brussel_landelijk':
				return 'P_B';
				break;
			case 'sprel_lidberoepscomissie_landelijk':
				return 'BC';
				break;
			case 'sprel_lidfinancielecontrolecomissie_landelijk':
				return 'FC';
				break;
			case 'sprel_lidvteam_landelijk':
				return 'VT';
				break;
			case 'sprel_bestuurslidrood_landelijk':
				return 'RB';
				break;
			case 'sprel_actiefroodlandelijk_landelijk':
				return 'ROOD';
				break;
			case 'sprel_gebiedscomissielid_afd':
				return 'GL';
				break;
			case 'sprel_gebiedscomissievoorzitter_afd':
				return 'GV';
				break;
			default:
				return 'UNK';
		}

		return '';
	}

	/**
	 * Maps CiviCRM province contact name to province code
	 *
	 * @param string $provname Province name
	 *
	 * @return string Province code
	 */
	private static function mapProvince($provname) {

		if (stripos($provname, 'Drenthe') !== false) {
			return 'DR';
		} elseif (stripos($provname, 'Zuid-Holland') !== false) {
			return 'ZH';
		} elseif (stripos($provname, 'Noord-Holland') !== false) {
			return 'NH';
		} elseif (stripos($provname, 'Overijssel') !== false) {
			return 'OV';
		} elseif (stripos($provname, 'Flevoland') !== false) {
			return 'FL';
		} elseif (stripos($provname, 'Utrecht') !== false) {
			return 'UT';
		} elseif (stripos($provname, 'Gelderland') !== false) {
			return 'GD';
		} elseif (stripos($provname, 'Groningen') !== false) {
			return 'GR';
		} elseif (stripos($provname, 'Noord-Brabant') !== false) {
			return 'NB';
		} elseif (stripos($provname, 'Limburg') !== false) {
			return 'LB';
		} elseif (stripos($provname, 'Friesland') !== false) {
			return 'FR';
		} elseif (stripos($provname, 'Zeeland') !== false) {
			return 'ZL';
		} else {
			return '';
		}

	}

}
