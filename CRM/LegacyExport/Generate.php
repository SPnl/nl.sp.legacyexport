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
	 * @return bool Success
	 * @throws \Exception When an API error occurs
	 */
	public static function generate($exportPath) {

		// Get contacts
		$contacts = civicrm_api3('Contact', 'getspdata', [
			'include_memberships'   => 1,
			'include_relationships' => 1,
			'include_spspecial'     => 1,
			'options'               => ['limit' => 999999],
		]);

		if (!$contacts || $contacts['is_error']) {
			throw new \Exception('An error occurred fetching Contacts.GetSPData: ' . $contacts['error_message'] . '.');
		}

		// Open all files and walk contact array
		$exportDoublePlusA = fopen($exportPath . "/exportdoubleplus_A.del", "w");
		$kevelA = fopen($exportPath . "/kevel_A.del", "w");
		$exportDoublePlusH = fopen($exportPath . "/exportdoubleplus_H.del", "w");
		$kevelH = fopen($exportPath . "/kevel_H.del", "w");

		foreach ($contacts['values'] as $contact) {

			// Get current SP and ROOD membership from the contact array, if available
			$lidm = [];
			if (count($contact['memberships']) > 0) {
				foreach ($contact['memberships'] as $membership) {
					if (in_array($membership['type_name'], ['Lid SP', 'Lid SP en ROOD']) &&
					    (!isset($lidm['sp']) || in_array($membership['status_name'], ['New', 'Current']))
					) {
						$lidm['sp'] = $membership;
					}
					if (in_array($membership['type_name'], ['Lid ROOD', 'Lid SP en ROOD']) &&
					    (!isset($lidm['rood']) || in_array($membership['status_name'], ['New', 'Current']))
					) {
						$lidm['rood'] = $membership;
					}
				}
			}

			// Write basic data to exportdoubleplus_a.del
			fputcsv($exportDoublePlusA, [
				$contact['contact_id'], // Regnr
				$contact['postal_code'], // Postcode
				$contact['initials'], // Voorletters
				$contact['first_name'], // Voornaam
				$contact['middle_name'], // Tussenvoegsel
				$contact['last_name'], // Achternaam
				$contact['afdeling_code'], // Afdeling-id
				$contact['email'], // Email
				$contact['member_sp'], // Is lid
				$contact['street_name'], // Straat
				$contact['street_number'], // Huisnummer
				$contact['street_unit'], // Toevoeging
				$contact['city'], // Plaats
				$contact['phone'], // Telefoon
				$contact['mobile'], // Mobiel
				- 1, // Aangever?
			], ',', '"');

			// Write Memoria data to kevel_a.del
			fputcsv($kevelA, [
				$contact['contact_id'], // Regnr
				$contact['initials'], // Voorletters
				$contact['first_name'], // Voornaam
				$contact['middle_name'], // Tussenvoegsel
				$contact['last_name'], // Achternaam
				$contact['street_name'], // Straat
				$contact['street_number'], // Huisnummer
				$contact['street_unit'], // Toevoeging
				$contact['postal_code'], // Postcode
				$contact['city'], // Plaats
				$contact['country_code'], // Land
				$contact['birth_date'], // Geboortedatum
				$contact['gender'], // Gender
				$contact['do_not_mail'] ? 1 : 0, // 'Statuscode'
				$contact['afdeling_code'], // Afdnr
				$contact['afdeling'], // Afdnaam
				$contact['gemeente'], // Gemeente
				self::mapProvince($contact['provincie']), // Provincie
				$contact['regio'], // Regio
				'LID', // Was lidcode
				(isset($lidm['sp']) ? $lidm['sp']['start_date'] : ''), // Bdat
				(isset($lidm['sp']) ? $lidm['sp']['end_date'] : ''), // Edat
				(isset($lidm['sp']) && isset($lidm['sp']['opzegreden']) ? $lidm['sp']['opzegreden'] : ''), // Opzegreden
				'', // Was rekeningnummer, werd niet gebruikt
				9, // Tribune -> nu onbekend
				(isset($lidm['sp']) && isset($lidm['sp']['cadeau']) ? $lidm['sp']['cadeau'] : ''), // Welkomstcadeau
				(isset($lidm['sp']) && isset($lidm['sp']['cadeau_datum']) ? $lidm['sp']['cadeau_datum'] : ''), // Datum cadeau verzonden
				(int) $contact['source'], // Herkomstsegmentnummer -> nu onbekend
				$contact['source'], // Herkomstomschrijving (nieuw)
				$contact['member_sp'],
				$contact['member_rood'],
				$contact['phone'],
				$contact['mobile'],
				$contact['email'],
			], ',', '"');

			// Write relationships data
			if (count($contact['relationships']) > 0) {
				foreach ($contact['relationships'] as $rel) {
					$funcAbbrev = self::mapFuncAbbrev($rel['name_a_b']);

					// ...to exportdoubleplus_h.del
					fputcsv($exportDoublePlusH, [
						$rel['contact_id_a'], // Regnr
						$rel['start_date'], // Bdat
						$funcAbbrev, // Afkorting
					], ',', '"');

					// ...to kevel_h.del
					fputcsv($kevelH, [
						$rel['contact_id_a'],
						$rel['start_date'],
						$rel['end_date'],
						$funcAbbrev,
						$rel['label_a_b'],
					], ',', '"');
				}
			}

		}

		fclose($exportDoublePlusA);
		fclose($kevelA);
		fclose($exportDoublePlusH);
		fclose($kevelH);

		/*
		 * Let op, er zijn dus nog vier bestanden over (exportdoubleplus_A|H en kevel_A|H).
		 * Geschrapt zijn: kevel_R (ROOD-leden apart), kevel_S (herkomstnummers),
		 * kevel_F en exportdoubleplus_F (functielijst) en exportdoubleplus_L (landcodes).
		 */

		return TRUE;
	}

	/**
	 * Maps CiviCRM relationship name to legacy function code
	 * @param string $relname Relationship name
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
	 * @param string $provname Province name
	 * @return string Province code
	 */
	private static function mapProvince($provname) {

		if (stripos($provname, 'Drenthe') !== FALSE) {
			return 'DR';
		} elseif (stripos($provname, 'Zuid-Holland') !== FALSE) {
			return 'ZH';
		} elseif (stripos($provname, 'Noord-Holland') !== FALSE) {
			return 'NH';
		} elseif (stripos($provname, 'Overijssel') !== FALSE) {
			return 'OV';
		} elseif (stripos($provname, 'Flevoland') !== FALSE) {
			return 'FL';
		} elseif (stripos($provname, 'Utrecht') !== FALSE) {
			return 'UT';
		} elseif (stripos($provname, 'Gelderland') !== FALSE) {
			return 'GD';
		} elseif (stripos($provname, 'Groningen') !== FALSE) {
			return 'GR';
		} elseif (stripos($provname, 'Noord-Brabant') !== FALSE) {
			return 'NB';
		} elseif (stripos($provname, 'Limburg') !== FALSE) {
			return 'LB';
		} elseif (stripos($provname, 'Friesland') !== FALSE) {
			return 'FR';
		} elseif (stripos($provname, 'Zeeland') !== FALSE) {
			return 'ZL';
		} else {
			return '';
		}

	}

	/* This is the original query this module previously used. Check if we get the same result (count):

		$sql = "SELECT DISTINCT c.id AS contact_id, c.first_name, c.middle_name, c.last_name, cmc.voorletters_1 AS voorletters, c.gender_id, c.birth_date, ca.street_name, ca.street_number, ca.street_unit, ca.city, ca.postal_code, ca.country_id, cc.name AS country_name, cc.iso_code AS country_code, ca.state_province_id, ce.email, cp.phone, cpm.phone AS mobile, cca.id AS afdeling_id, cca.display_name AS afdeling, cva.gemeente_24 AS gemeente, ccr.id AS regio_id, ccr.display_name AS regio, ccp.id AS provincie_id, ccp.display_name AS provincie, c.do_not_mail, c.do_not_phone, cm.membership_type_id AS membership_type, cm.start_date AS sp_start_date, cm.end_date AS sp_end_date, cm.status_id, cm.source, cml.reden_6 AS opzegreden, cmw.cadeau_8 AS cadeau, cmw.datum_14 AS cadeaudatum
	FROM civicrm_contact c
	LEFT JOIN civicrm_membership cm ON (c.id = cm.contact_id AND cm.membership_type_id IN ({$membership_type_sp},{$membership_type_sprood},{$membership_type_rood}) AND (cm.status_id IN (1,2) OR (cm.end_date >= '{$endDate}' AND cm.status_id IN (3,4,6,7))))
	LEFT JOIN civicrm_relationship cr ON (c.id = cr.contact_id_a AND cr.relationship_type_id IN ({$relationship_type_am},{$relationship_type_dh},{$relationship_type_br},{$relationship_type_bp}) AND (cr.end_date IS NULL OR cr.end_date > '{$endDate}'))
	LEFT JOIN civicrm_value_migratie_1 cmc ON cmc.entity_id = c.id
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
	 * 
	 */

}
