<?php

abstract class DND_Monster_Monster implements JsonSerializable, Serializable {


#	protected $ac_rows      = array(); // DND_Monster_Trait_Combat
	protected $alignment    = 'Neutral';
	protected $appearing    = array( 1, 1, 0 );
	protected $armor_class  = 10;
#	protected $armor_type   = 11;      // DND_Monster_Trait_Combat
	protected $attacks      = array();
#	private   $combat_key   = '';      // DND_Monster_Trait_Combat
	public    $current_hp   = -10000;
	protected $description  = '';
	protected $frequency    = 'Common';
	protected $hd_minimum   = 1;
	protected $hd_value     = 8;
	protected $hit_dice     = 0;
	protected $hit_points   = 0;
	protected $hp_extra     = 0;
	protected $immune       = array();
	protected $in_lair      = 0;
	protected $initiative   = 10;
	protected $intelligence = 'Animal';
	protected $magic_user   = null;
	protected $morale       = true;
	protected $movement     = array( 'foot' => 12 );
	protected $name         = 'Monster';
	protected $psionic      = 'Nil';
	protected $race         = 'Monster';
	protected $reference    = 'Monster Manual page';
	protected $resistance   = 'Standard';
	protected $saving       = array( 'fight' );
	protected $segment      = 0;
	protected $size         = "Medium";
	protected $specials     = array();
	protected $stats        = array( 'str' => 9 );
#	protected $to_hit_row   = array(); // DND_Monster_Trait_Combat
	protected $treasure     = 'Nil';
	protected $vulnerable   = array();
#	protected $weap_allow   = array(); // DND_Character_Trait_Weapons
#	protected $weap_dual    = false;   // DND_Character_Trait_Weapons
#	protected $weapon       = array(); // DND_Character_Trait_Weapons
#	protected $weapons      = array(); // DND_Character_Trait_Weapons
	protected $xp_value     = array( 0, 0, 0, 0 );


	use DND_Character_Trait_Attributes;
	use DND_Character_Trait_SavingThrows;
	use DND_Character_Trait_Utilities;
	use DND_Character_Trait_Weapons;
	use DND_Monster_Trait_Combat {
		determine_armor_class as monster_armor_class;
		get_armor_class as get_armor_flank;
		get_armor_class as get_armor_rear;
		get_armor_class as get_armor_spell;
	}
	use DND_Monster_Trait_Experience;
	use DND_Monster_Trait_Treasure;
	use DND_Monster_Trait_Serialize;
	use DND_Trait_Logging;
	use DND_Trait_Magic { __get as magic__get; }
	use DND_Trait_ParseArgs;


	abstract protected function determine_hit_dice();


	public function __construct( $args = array() ) {
		$this->parse_key( $args ); // DND_Monster_Trait_Combat
		$this->parse_args( $args );
		$this->determine_intelligence();
		$this->determine_hit_dice();
		$this->determine_hit_points();
		$this->determine_armor_class(); // DND_Monster_Trait_Combat
		$this->armor_type = $this->armor_class;
		$this->determine_to_hit_row();
		$this->determine_specials();
		$this->determine_saving_throw();
		$this->initialize_sequence_attacks(); // DND_Monster_Trait_Combat
		if ( $this->current_hp === -10000 ) $this->current_hp = $this->hit_points;
	}

	public function __get( $name ) {
		if ( substr( $name, 0, 4 ) === 'move' ) {
			if ( ( ( $name === 'movement' ) || ( $name === 'move_foot' ) ) && array_key_exists( 'foot', $this->movement ) ) {
				return $this->movement['foot'];
			} else if ( in_array( $name, [ 'move_air', 'move_fly' ] ) && array_key_exists( 'air',   $this->movement ) ) {
				return $this->movement['air'];
			} else if ( ( $name === 'move_earth' ) && array_key_exists( 'earth', $this->movement ) ) {
				return $this->movement['earth'];
			} else if ( in_array( $name, [ 'move_swim', 'move_water' ] ) && array_key_exists( 'swim',  $this->movement ) ) {
				return $this->movement['swim'];
			} else if ( ( $name === 'move_web'   ) && array_key_exists( 'web',   $this->movement ) ) {
				return $this->movement['web'];
			} else {
				$key = array_key_first( $this->movement );
				return $this->movement[ $key ];
			}
		} else if ( $name === 'armor_spell' ) {
			return $this->armor_class;
		}
		if ( ( $name === 'xp_value' ) && ( ( ! $this->xp_value ) || is_array( $this->xp_value ) ) ) {
			$this->determine_xp_value();
		}
		return $this->magic__get( $name );
	}

	public function __toString() {
		return $this->name;
	}


	/**  Setup functions  **/

	protected function determine_intelligence() {
		static $int = null;
		$int = ( $int ) ? $int : new DND_Enum_Intelligence;
		if ( ! array_key_exists( 'int', $this->stats ) ) {
			$rge = $int->range( $this->intelligence );
			if ( strpos( $this->intelligence, 'Low' ) > 0 ) {
				$this->stats['int'] = $rge[0];
			} else {
				$pos = mt_rand( 1, count( $rge ) );
				$this->stats['int'] = $rge[ --$pos ];
			}
		}
		$this->intelligence = $int->get( $this->stats['int'] );
	}

	protected function determine_hit_points() {
		if ( ( $this->hit_points === 0 ) && ( $this->hit_dice > 0 ) ) {
			$this->hit_points = $this->calculate_hit_points();
		}
	}

	protected function calculate_hit_points( $appearing = false ) {
		$hit_points = 0;
		for( $i = 1; $i <= $this->hit_dice; $i++ ) {
			$hit_points += mt_rand( $this->hd_minimum, $this->hd_value );
		}
		$hit_points += $this->hp_extra;
		return $hit_points;
	}

	protected function determine_specials() {
		$this->specials['reference'] = $this->reference;
		do_action( 'monster_determine_specials' );
	}

	protected function determine_saving_throw() {
		$this->specials['saving'] = sprintf( 'Saves as a %u HD creature.', $this->get_saving_throw_level() );
	}

	public function get_saving_throw_level() {
		$level = $this->hit_dice;
		$level+= ceil( $this->hp_extra / 4 );
		return $level;
	}


	/**  Magic functions  **/

	protected function set_magic_user( $level = 0, $args = array() ) {
		$args['level'] = ( $level ) ? $level : $this->hit_dice;
		$create = 'DND_Character_' . $this->magic_use;
		$this->magic_user = new $create( $args );
		$this->attacks['Spell'] = [ 0, 0, 0 ];
		if ( ! in_array( 'magic', $this->saving ) ) $this->saving[] = 'magic';
		$this->vulnerable[] = 'magic';
	}


	/**  Get functions  **/

	public function get_armor_type() {
		return $this->get_armor_class();
	}

	public function get_class() {
		return array_reverse( explode( '_', get_class( $this ) ) )[0];
	}

	public function get_hit_points() {
		return $this->current_hp;
	}

	public function get_name( $underscore = false ) {
		if ( $underscore ) return str_replace( ' ', '_', $this->name );
		return $this->name;
	}

	public function get_number_appearing() {
		$num = $this->appearing[2];
		for( $i = 1; $i <= $this->appearing[0]; $i++ ) {
			$num += mt_rand( 1, $this->appearing[1] );
		}
		return $num;
	}

	protected function generate_additionals( $base, $number = 0 ) {
		$adds   = array( $base );
		$class  = get_class( $base );
		$number = ( $number ) ? $number : $base->get_number_appearing();
		for( $i = 2; $i <= $number; $i++ ) {
			$adds[] = new $class;
		}
		return $adds;
	}

	protected function get_saving_throw_table() {
		return $this->get_combined_saving_throw_table( $this->saving );
	}


	/**  Set functions  **/

	public function set_initiative( $roll ) {
		$this->initiative = 11 - $roll;
		$this->segment = max( $this->initiative, $this->segment );
	}

	public function set_attack_segment( $new ) {
		$this->segment = max( $this->segment, intval( $new ) );
	}

	public function set_current_weapon( $new ) {
		$info = $this->get_attack_info( $new );
		if ( $info ) {
			$this->weapon = $info;
			$this->weapon['bonus'] = apply_filters( 'dnd1e_weapon_damage_bonus', $this->weapon['bonus'], $this );
			return true;
		}
		return false;
	}


	/**  Utility functions  **/

	protected function check_chance( $chance ) {
		$perc = intval( $chance );
		if ( $perc ) {
			$roll = mt_rand( 1, 100 );
			if ( ! ( $roll > $perc ) ) {
				return true;
			}
		}
		return false;
	}

	public function check_for_lair() {
		if ( $this->in_lair && ( $this->in_lair < 100 ) ) {
			if ( $this->check_chance( $this->in_lair ) ) {
				$this->in_lair = 100;
			} else {
				$this->in_lair = 0;
			}
		}
		return ( $this->in_lair ) ? true : false;
	}

	public function get_treasure( $possible = '' ) {
		$response = 'No Treasure Available.';
		if ( empty( $possible ) ) $possible = $this->treasure;
		if ( ! ( $possible === 'Nil' ) ) {
			if ( $test = $this->get_treasure_possibilities( $possible ) ) {
				$response = $test;
			}
		}
		return $response;
	}

	public function monster_damage_string( $target ) { return false; }

	public function spend_manna( $spell ) { }


	/**  Command Line  **/

	public function command_line_display() {
		$line = "{$this->name}: ";
		$line.= "AC:{$this->armor_class}";
		$line.= ", HD:{$this->hit_dice}";
		if ( $this->hp_extra ) {
			$line .= "+{$this->hp_extra}";
		}
		$line .= ", HP:{$this->current_hp}/{$this->hit_points}\n";
		if ( intval( $this->resistance ) ) {
			$this->specials['resistance'] = sprintf( 'Magic Resistance: %u%%', $this->resistance );
		}
		ksort( $this->specials );
		foreach( $this->specials as $string ) {
			$line.= $string . "\n";
		}
		return $line;
	}


}
