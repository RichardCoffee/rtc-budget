<?php

abstract class DND_Monster_Dragon_Dragon extends DND_Monster_Monster {


#	protected $alignment    = 'Neutral';
	protected $appearing    = array( 1, 4, 0 );
#	protected $armor_class  = 10;
#	protected $armor_type   = 11;
	protected $attacks      = array( 'Claw Right' => [ 1, 6, 0 ], 'Claw Left' => [ 1, 6, 0 ], 'Bite' => [ 4, 8, 0 ], 'Breath' => [ 1, 1, 0 ] );
	protected $co_speaking  = 0;
	protected $co_magic_use = 0;
	protected $co_sleeping  = 0;
	protected $frequency    = 'Rare';
	protected $hd_minimum   = 0;
	protected $hd_range     = array( 8, 9, 10 );
#	protected $hd_value     = 8;
#	protected $in_lair      = 0;
#	protected $initiative   = 1;
#	protected $intelligence = 'Animal';
#	protected $magic_user   = null;
	protected $magic_use    = false;
	protected $movement     = array( 'foot' => 9, 'air' => 24 );
	protected $name         = 'Dragon';
#	protected $psionic      = 'Nil';
	protected $race         = 'Dragon';
	protected $reference    = 'Monster Manual page 29-34';
#	protected $resistance   = 'Standard';
	protected $size         = 'Large';
	protected $sleeping     = false;
	protected $spells       = array();
	protected $speaking     = false;
#	protected $treasure     = 'Nil';
#	protected $xp_value     = array();


	abstract protected function determine_magic_spells();


	public function __construct( $args = array() ) {
		parent::__construct( $args );
		if ( isset( $args['spell_list'] ) ) {
			$this->set_magic_user();
			$this->import_spell_list( $args['spell_list'] );
		} else if ( $this->co_magic_use ) {
			if ( $this->check_chance( $this->co_magic_use ) ) {
				$this->set_magic_user();
				$spells = $this->determine_magic_spells();
				$this->add_magic_spells( $spells );
				$this->add_magic_spells_to_specials();
			} else {
				$this->co_magic_use = 0;
			}
		}
		if ( $this->co_speaking ) {
			if ( $this->check_chance( $this->co_speaking ) ) {
				$this->co_speaking = 100;
			} else {
				$this->co_speaking = 0;
			}
		}
	}

	public function __get( $name ) {
		if ( $name === 'movement' ) {
			return $this->movement['air'];
		}
		return parent::__get( $name );
	}

	protected function determine_hit_dice() {
		if ( $this->hit_dice === 0 ) {
			$roll = mt_rand( 1, 8 );
			switch( $roll ) {
				case 1:
				case 2:
					$this->hit_dice = $this->hd_range[0];
					break;
				case 8:
					$this->hit_dice = $this->hd_range[2];
					break;
				case 3:
				case 4:
				case 5:
				case 6:
				case 7:
				default:
					$this->hit_dice = $this->hd_range[1];
			}
		}
	}

	protected function calculate_hit_points() {
		$hit_points = 0;
		if ( $this->hd_minimum === 0 ) {
			$this->hd_minimum = mt_rand( 1, $this->hd_value );
		}
		for( $i = 1; $i <= $this->hit_dice; $i++ ) {
			$hit_points += mt_rand( $this->hd_minimum, $this->hd_value );
		}
		$this->attacks['Breath'][0] = $hit_points;
		return $hit_points;
	}

	protected function determine_specials() {
		$this->specials = array(
			'breath'   => '50% chance of using breath weapon on any given round (max 3/day).',
			'senses'   => "Infravision 60', Detects hidden or invisible creatures within " . sprintf( '%u feet.', $this->hd_minimum * 10 ),
			'treasure' => $this->get_treasure_amounts_description(),
		);
		if ( $this->hd_minimum > 4 ) {
			$this->specials['fear_aura'] = 'Radiates fear aura. Run meatbag, Run!';
		}
	}

	protected function determine_saving_throw() {
		$this->specials['saving'] = sprintf( 'Saves as a %u HD creature.', $this->get_saving_throw_level() );
	}

	protected function get_saving_throw_level() {
		return max( $this->hit_dice, round( $this->hit_points / 4 ) );
	}

	public function dragon_fear_aura_saving_throw() {
		$adj = 0;
		if ( $this->hd_minimum === 5 ) {
			$adj = 5;
		} else if ( $this->hd_minimum === 6 ) {
			$adj = 3;
		} else if ( $this->hd_minimum === 7 ) {
			$adj = 1;
		}
		return $adj;
	}

	public function get_appearing_hit_points( $number = 1 ) {
		$number = intval( $number );
		$hit_points = array( $this->hit_points );
		for( $i = 1; $i < $number; $i++ ) {
			$dragon = 0;
			for( $j = 1; $j <= $this->hit_dice; $j++ ) {
				$dragon += mt_rand( $this->hd_minimum, $this->hd_value );
			}
			$hit_points[] = $dragon + $this->hp_extra;
		}
		return $hit_points;
	}

	protected function set_magic_user( $level = 0 ) {
		if ( $this->magic_use ) {
			$level = ( $level ) ? $level : $this->hit_dice;
			$create = 'DND_Character_' . $this->magic_use;
			$this->magic_user = new $create( [ 'level' => $level ] );
			$this->attacks['Spell'] = [ 0, 0, 0 ];
		}
	}

	protected function add_magic_spells( $list ) {
		foreach( $list as $level ) {
			$spell = $this->magic_user->generate_random_spell( $level );
			$this->spells[] = $this->magic_user->get_magic_spell_info( $level, $spell );
		}
	}

	protected function import_spell_list( $list ) {
		foreach( $list as $spell ) {
			$this->spells[] = $this->magic_user->get_magic_spell_info( $spell['level'], $spell['name'] );
		}
		$this->add_magic_spells_to_specials();
	}

	protected function add_magic_spells_to_specials() {
		if ( $this->spells ) {
			$cnt = 1;
			foreach( $this->spells as $spell ) {
				$index = 'spell' . $cnt++;
				$this->specials[ $index ] = sprintf( '%6s: %s', $spell['level'], $spell['name'] );
			}
		}
	}

	public function get_treasure( $possible = '' ) {
		$treasure = array();
		$check = explode( ',', $this->treasure );
#		$this->add_treasure_filters();
		foreach( $check as $type ) {
			$response = parent::get_treasure( $type );
			if ( $response ) {
				$treasure = array_merge( $treasure, $response );
			}
		}
		return $treasure;
	}

	protected function get_treasure_amounts_description() {
		$string = 'No treasure possible.';
		switch( $this->hd_minimum ) {
			case 1:
				$string = '10% chance to have one-quarter of listed treasure.';
				break;
			case 2:
				$string = '25% chance to have one-quarter of listed treasure.';
				break;
			case 3:
				$string = '50% chance to have one-half of listed treasure.';
				break;
			case 4:
			case 5:
			case 6:
				$string = 'Normal chances of having listed treasure.';
				break;
			case 7:
				$string = '50% chance to have 150% of listed treasure.';
				break;
			case 8:
				$string = '75% chance to have 200% of listed treasure.';
				break;
			default:
		}
		return $string;
	}

	public function add_treasure_filters() {
		add_filter( 'monster_treasure_multipliers', [ $this, 'modify_treasure_multipliers' ], 10, 2 );
	}

	public function modify_treasure_multipliers( $mults, $monster ) {
		if ( $monster instanceOf $this ) {
			$mod = 1;
			switch( $this->hd_minimum ) {
				case 1:
				case 2:
					$mod = .25;
					break;
				case 3:
					$mod = .5;
					break;
				case 7:
					$mod = 1.5;
					break;
				case 8:
					$mod = 2;
					break;
				default:
			}
			foreach( $mults as $key => $mult ) {
				$mults[ $key ] *= $mod;
			}
		}
		return $mults;
	}

	public function is_sleeping() {
		if ( $this->check_chance( $this->co_sleeping ) ) {
			$this->co_sleeping = 100;
			$this->sleeping = true;
		}
		return $this->sleeping;
	}

	public function command_line_display() {
		$line  = parent::command_line_display();
		if ( $this->co_speaking === 100 ) {
			$line.= "This dragon speaks common.\n";
		}
		$line .= sprintf( 'sleeping: %u', $this->co_sleeping ) . "%\n";
		return $line;
	}


}