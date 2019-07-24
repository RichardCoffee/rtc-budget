<?php

abstract class DND_Character_Character implements JsonSerializable, Serializable {

	/**
	 *   Note:  unassigned filters: 'character_all_saving_throws', 'character_BW_saving_throw'
	 */

	protected $ac_rows    = array( 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22 );
	protected $armor      = array( 'armor' => 'none', 'bonus' => 0, 'type' => 10, 'class' => 10, 'rear' => 10 );
	protected $armr_allow = array();
	public    $current_hp = -100;
	protected $experience = 0;
	protected $hit_die    = array( 'limit' => -1, 'size' => -1, 'step' => -1 );
	protected $hit_points = 0;
	protected $initiative = array( 'roll' => 0, 'actual' => 0, 'segment' => 0 );
	protected $level      = 0;
	protected $max_move   = 12;
	protected $movement   = 12;
	protected $name       = 'Character Name';
	protected $non_prof   = -100;
	protected $ongoing    = array();
	public    $opponent   = array( 'type' => '', 'ac' => 10, 'at' => 10, 'range' => 5 );
	protected $race       = 'Human';
	public    $segment    = 0;
	protected $shield     = array( 'type' => 'none', 'bonus' => 0 );
	protected $shld_allow = array();
	protected $specials   = array();
	protected $spells     = array();
	protected $stats      = array( 'str' => 3, 'int' => 3, 'wis' => 3, 'dex' => 3, 'con' => 3, 'chr' => 3 );
	protected $weap_allow = array();
	protected $weap_dual  = array();
	protected $weap_init  = array( 'initial' => 1, 'step' => 10 );
	protected $weap_reqs  = array();
	protected $weapon     = array( 'current' => 'none', 'skill' => 'NP', 'attack' => 'hand', 'bonus' => 0 );
	protected $weapons    = array();
	protected $xp_bonus   = array();
	protected $xp_step    = 1000000;
	protected $xp_table   = array( 1000000 );

	use DND_Character_Import_Kregen;
	use DND_Character_Trait_Armor;
	use DND_Character_Trait_Attributes;
	use DND_Character_Trait_Serialize;
	use DND_Character_Trait_Weapons;
	use DND_Trait_Logging;
	use DND_Trait_Magic;
	use DND_Trait_ParseArgs;

	abstract protected function define_specials();

	public function __construct( $args = array() ) {
		if ( isset( $args['ac_rows'] ) ) {
			$this->ac_rows = $args['ac_rows'];
			unset( $args['ac_rows'] );
		}
		$this->parse_args_merge( $args );
		$this->initialize_character();
		if ( isset( $args['spell_list'] ) ) {
			$this->initialize_spell_list( $args['spell_list'] ); // This has to be done after the level has been set.
		}
	}

	public function initialize_character() {
		if ( ( $this->level === 0 ) && ( $this->experience > 0 ) ) {
			$this->level = $this->calculate_level( $this->experience );
		}
		if ( $this->hit_points === 0 ) {
			$this->determine_hit_points();
		}
		if ( $this->weapon['current'] === 'none' ) {
			$this->determine_armor_class(); // this gets called in set_current_weapon()
		} else {
			$this->set_current_weapon( $this->weapon['current'] );
		}
		$this->define_specials();
		$this->determine_initiative();
	}

	public function get_name( $full = false ) {
		if ( $full ) {
			return $this->name;
		} else {
			$name = explode( ' ', $this->name );
			return $name[0];
		}
	}

	protected function calculate_level( $xp ) {
		$level = 0;
		foreach( $this->xp_table as $key => $needed ) {
			$level = $key;
			if ( $xp < $needed ) {
				break;
			}
		}
		$xp -= $this->xp_step;
		while ( $xp > 0 ) {
			$xp -= $this->xp_step;
			$level++;
		}
		return $level;
	}

	public function set_level( $level ) {
		$this->level = $level;
		$old_hp = $this->hit_points;
		$this->determine_hit_points();
		if ( $this->current_hp < $this->hit_points ) {
			$this->current_hp += $this->hit_points - $old_hp;
		}
		if ( method_exists( $this, 'reload_spells' ) ) {
			$this->reload_spells();
		}
	}

	public function add_experience( $xp ) {
		$bonus = true;
		foreach( $this->xp_bonus as $stat => $limit ) {
			if ( $this->stats[ $stat ] < $limit ) {
				$bonus = false;
			}
		}
		$this->experience += ( $bonus ) ? round( $xp * 1.1 ) : $xp;
		$level = $this->calculate_level( $this->experience );
		if ( $level > $this->level ) {
			$this->set_level( $level );
		}
	}

	protected function determine_hit_points() {
		$base = $this->hit_die['size'] + $this->get_constitution_hit_point_adjustment( $this->stats['con'] );
		$this->hit_points = $base * min( $this->hit_die['limit'], $this->level );
		if ( $this->level > $this->hit_die['limit'] ) {
			$this->hit_points += ( $this->level - $this->hit_die['limit'] ) * $this->hit_die['step'];
		}
		if ( $this->current_hp === -100 ) {
			$this->current_hp  = $this->hit_points;
		}
	}

	protected function get_constitution_hit_point_adjustment( $con ) {
		$bonus = $this->attr_get_constitution_hit_point_adjustment( $con );
		return min( $bonus, 2 );
	}

	public function get_hit_points() {
		return $this->current_hp + apply_filters( 'character_temporary_hit_points', 0, $this );
	}

	protected function determine_armor_class() {
		$no_shld = in_array( $this->weapon['attack'], $this->get_weapons_not_allowed_shield() );
		$this->armor['type'] = $this->get_armor_ac_value( $this->armor['armor'] );
		$this->armor['rear'] = $this->armor['type'];
		if ( ! ( ( $this->shield['type'] === 'none' ) || $no_shld ) ) {
			$this->armor['type']--;
		}
		$this->armor['class'] = $this->armor['type'];
		$this->movement = min( $this->max_move, $this->get_armor_base_movement( $this->armor['armor'], $this->movement ) + $this->armor['bonus'] );
		$this->armor['class'] += $this->get_armor_class_dexterity_adjustment( $this->stats['dex'] );
		$this->armor['class'] -= $this->armor['bonus'];
		$this->armor['rear']  -= $this->armor['bonus'];
		$this->armor['class'] -= ( $no_shld ) ? 0 : $this->shield['bonus'];
		$this->armor['class'] -= apply_filters( 'character_armor_class_adjustments', 0, $this );
	}

	protected function determine_initiative() {
		if ( $this->initiative['roll'] > 0 ) {
			$this->initiative['actual']  = $this->initiative['roll'] + $this->get_missile_to_hit_adjustment( $this->stats['dex'] );
			$this->initiative['segment'] = 11 - $this->initiative['actual'];
			$this->segment = $this->initiative['segment'];
		}
	}

	protected function initialize_spell_list( $book ) {
		if ( $book ) {
			foreach( $book as $level => $list ) {
				foreach( $list as $spell ) {
					$this->spells[ $level ][ $spell ] = $this->get_spell_data( $level, $spell );
				}
			}
		}
	}

	public function set_alternative_movement() {
		if ( $this->movement === 6 ) {
			$this->movement = '6a';
		} else if ( $this->movement === '6a' ) {
			$this->movement = 6;
		}
	}

	public function is_off_hand_weapon() {
		$off = false;
		if ( isset( $this->weap_dual[1] ) && ( $this->weapon['current'] === $this->weap_dual[1] ) ) {
			$off = true;
		}
		return $off;
	}

	public function set_primary_weapon() {
		if ( isset( $this->weap_dual[0] ) ) {
			$this->set_current_weapon( $this->weap_dual[0] );
		}
	}

	public function set_dual_weapon() {
		if ( isset( $this->weap_dual[1] ) ) {
			$this->set_current_weapon( $this->weap_dual[1] );
		}
	}

	public function set_current_weapon( $new = '' ) {
		if ( ! empty ( $new ) && ( empty( $this->weap_allow ) || ( ( ! empty( $this->weap_allow ) ) && in_array( $new, $this->weap_allow ) ) ) ) {
			if ( ! $this->weapons_check( $new ) ) {
				return false;
			}
			$this->weapon = array( 'current' => $new, 'skill' => 'NP', 'attacks' => array( 1, 1 ), 'bonus' => 0 );
			if ( ( ! empty( $this->weapons ) ) && isset( $this->weapons[ $new ] ) ) {
				$this->weapon = array_merge( $this->weapon, $this->weapons[ $new ] );
			} else {
				// TODO: show alert for non-proficient weapon use
			}
			$data = $this->get_weapon_info( $this->weapon['current'] );
			$this->weapon = array_merge( $this->weapon, $data );
			$atts  = $this->get_weapon_attacks_array( $data['attack'] );
			$index = $this->get_weapon_attacks_per_round_index( $this->weapon['skill'] );
			$this->weapon['attacks'] = $atts[ $index ];
			if ( stripos( $this->weapon['current'], 'off-hand' ) !== false ) {
				$primary = $this->get_weapon_info( $this->weap_dual[0] );
				$primatt = $this->get_weapon_attacks_array( $primary['attack'] );
				$primidx = $this->get_weapon_attacks_per_round_index( $this->weapons[ $this->weap_dual[0] ]['skill'] );
				$prime   = $primatt[ $primidx ];
				if ( $prime[1] === $this->weapon['attacks'][1] ) {
					$this->weapon['attacks'][0] += $prime[0];
				} else {
					$this->weapon['attacks'][0] += ( $prime[1] === 2 ) ? ( $this->weapon['attacks'][0] + $prime[0] ) : ( $prime[0] * 2 ) ;
					$this->weapon['attacks'][1] += ( $prime[1] === 2 ) ? 1 : 0;
				}
			}
		}
		$this->determine_armor_class();
		return true;
	}

	public function get_to_hit_number( $target_ac = -11, $target_at = -1, $range = -1 ) {
		$prin = false;
#if ( $this->get_name() === 'Ivan' ) $prin = true;
		if ( ! empty( $this->opponent['type'] ) ) {
			$target_ac = $this->opponent['ac'];
			$target_at = $this->opponent['at'];
			$range     = $this->opponent['range'];
		}
		if ( $target_ac === -11 ) return 100;
		$target_at = max( $target_ac, $target_at, 0 );
		$to_hit  = $this->get_to_hit_base( $target_ac );
		if ( $prin ) printf( 'B%2u ', $to_hit );
		$to_hit -= $this->get_weapon_type_adjustment( $this->weapon['current'], $target_at );
		if ( $prin ) printf( 'T%2u ', $to_hit );
		if ( in_array( $this->weapon['attack'], $this->get_weapons_using_strength_bonuses() ) ) {
			$to_hit -= $this->get_strength_to_hit_bonus( $this->stats['str'] );
			if ( $prin ) printf( 'S%2u ', $to_hit );
			$to_hit -= $this->get_weapon_proficiency_bonus( $this->weapon['skill'] );
			if ( $prin ) printf( 'P%2u ', $to_hit );
		} else if ( in_array( $this->weapon['attack'], $this->get_weapons_using_missile_adjustment() ) ) {
			$to_hit -= $this->get_missile_to_hit_adjustment( $this->stats['dex'] );
			if ( $prin ) printf( 'M%2s ', $to_hit );
			$to_hit -= $this->get_missile_range_adjustment( $this->weapon['range'], $range );
			if ( $prin ) printf( 'R%2u ', $to_hit );
			$to_hit -= $this->get_missile_proficiency_bonus( $this->weapon, $range );
			if ( $prin ) printf( 'P%2s ', $to_hit );
		}
		$to_hit -= $this->weapon['bonus'];
		if ( $prin ) printf( 'W%2s ', $to_hit );
		$to_hit -= apply_filters( 'character_to_hit_opponent', 0, $to_hit, $this );
		if ( $prin ) printf( 'F%2s ', $to_hit );
		return $to_hit;
	}

	protected function get_to_hit_base( $target_ac = 10 ) {
		$index = 10 - $target_ac;
		$table = $this->to_hit_ac_table();
		$row   = $this->ac_rows[ $this->level ];
		$base  = $table[ $row ];
		if ( isset( $base[ $index ] ) ) {
			return $base[ $index ];
		}
		return 10000;
	}

	public function get_weapon_damage( $size ) {
		$string = "Size passed: $size, can only be 'Small', 'Medium', or 'Large'";
		$test   = substr( $size, 0, 1 );
		if ( in_array( $test, [ 'S', 'M', 'L' ] ) ) {
			if ( $test === 'L' ) {
				$string = $this->weapon['damage'][1];
			} else {
				$string = $this->weapon['damage'][0];
			}
		}
		return $string;
	}

	public function get_weapon_damage_bonus( $range = -1 ) {
		if ( $this->opponent['range'] > 0 ) { $range = $this->opponent['range']; }
		$bonus = $this->get_missile_proficiency_bonus( $this->weapon, $range, 'damage' );
#echo "prof: $bonus\n";
		if ( in_array( $this->weapon['attack'], $this->get_weapons_using_strength_damage() ) ) {
			$bonus += $this->get_strength_damage_bonus( $this->stats['str'] );
#echo " str: $bonus\n";
		}
		$bonus += $this->weapon['bonus'];
#echo "weap: $bonus\n";
		return apply_filters( 'character_weapon_damage_bonus', $bonus, $this );
	}

	protected function get_weapon_proficiencies_total() {
		$profs = 0;
		foreach( $this->weapons as $weapon => $data ) {
			if ( $weapon === 'Spell' ) continue;
			switch( $data['skill'] ) {
				case 'NP':
					break;
				case 'PF':
					$profs++;
					break;
				case 'SP':
					$profs += 2;
					break;
				case 'DS':
					$profs += 3;
					break;
				default:
			}
		}
		return $profs;
	}

	public function get_available_weapon_proficiencies() {
		$total = $this->weap_init['initial'] + intval( ( $this->level - 1 ) / $this->weap_init['step'] );
		$current = $this->get_weapon_proficiencies_total();
		return $total - $current;
	}

	public function get_spell_list() {
		return $this->spells;
	}

	/** Filters Effects **/

	public function this_character_only( $purpose, $spell, $char ) {
		if ( $char->get_name() === $spell['target'] ) {
			return true;
		}
		return false;
	}

	public function check_temporary_hit_points( $damage ) {
		$damage = intval( $damage );
		if ( $damage > 0 ) {
			$ongoing = get_transient( 'dnd1e_ongoing' );
			foreach( $ongoing as $name => $effect ) {
				if ( $effect['target'] === $this->get_name() ) {
					if ( isset( $effect['condition'] ) ) {
						// TODO: check for aoe conditions
						if ( $effect['condition'] === 'this_character_only' ) {
							foreach( $effect['filters'] as $key => $filter ) {
								if ( $filter[0] === 'character_temporary_hit_points' ) {
									$ongoing[ $name ]['filters'][ $key ][1] -= $damage;
									$ongoing[ $name ]['filters'][ $key ][1]  = max( 0, $ongoing[ $name ]['filters'][ $key ][1] );
									if ( $ongoing[ $name ]['filters'][ $key ][1] === 0 ) {
										unset( $ongoing[ $name ] );
										break;
									}
									set_transient( 'dnd1e_ongoing', $ongoing );
								}
							}
						}
					}
				}
			}
		}
	}


}
