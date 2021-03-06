<?php

class DND_Character_Fighter extends DND_Character_Character {

	protected $ac_rows   = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21 );
	protected $hit_die   = array( 'limit' => 9, 'size' => 10, 'step' => 3 );
	protected $non_prof  = -2;
	protected $stats     = array( 'str' => 9, 'int' => 3, 'wis' => 3, 'dex' => 3, 'con' => 7, 'chr' => 3 );
	protected $weap_init = array( 'initial' => 4, 'step' => 3 );
	protected $xp_bonus  = array( 'str' => 16 );
	protected $xp_step   = 250000;
	protected $xp_table  = array( 0, 2000, 4000, 8000, 18000, 35000, 70000, 125000, 250000 );


	use DND_Character_Trait_Dual;


	protected function define_specials() { }

	protected function get_weapon_attacks_per_round( $weapon, $opponent = null ) {
		$atts = parent::get_weapon_attacks_per_round( $weapon );
		if ( $opponent && ( $opponent instanceOf DND_Monster_Monster ) && ( $opponent->hd_value < 8 ) ) {
			$atts = array( $this->level, 1 );
		}
		return $atts;
	}

	protected function get_weapon_attacks_per_round_index( $skill = 'NP' ) {
		$index = parent::get_weapon_attacks_per_round_index( $skill );
		if ( $this->level > 6 ) {
			$index++;
			if ( $this->level > 12 ) {
				$index++;
			}
		}
		return $index;
	}

	protected function get_constitution_hit_point_adjustment( $con ) {
		return $this->attr_get_constitution_hit_point_adjustment( $con );
	}

	protected function get_saving_throw_table() {
		return $this->get_fight_saving_throw_table();
	}

}
