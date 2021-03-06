<?php

class DND_Character_Import_Kregen extends DND_Character_Import_CSV {


#	public    $character      = null;    // DND_Character_Import_CSV
	protected $classes        = array();
#	protected $data           = array(); // DND_Character_Import_CSV
#	public    $import_message = '';      // DND_Character_Import_CSV
#	public    $import_status  = '';      // DND_Character_Import_CSV
	protected $import_task    = 'import';
	private   $in_spells      = false;
#	protected $name           = '';      // DND_Character_Import_CSV
	protected $time_line      = array();


	protected function parse_class( $data ) {
		$line = array_values( array_filter( explode( ',', $data ) ) );
		$this->name = $line[0];
		$class = 'DND_Character_' . $this->check_class_name( $line[1] );
		$check = $this->check_class_name( $line[3] );
		if ( $check ) {
			$class.= $check;
			$check = $this->check_class_name( $line[5] );
			if ( $check ) {
				$class.= $check;
				$check = $this->check_class_name( $line[7] );
				if ( $check ) {
					$class .= $check;
				}
			}
		}
		return $class;
	}

	protected function check_class_name( $string ) {
		$class = '';
#echo "class: $string\n";
		switch( $string ) {
			case 'Climb': // shows up on Barbarian spreadsheet
			case 'Dr':    // shows up on Ranger / RangerThief spreadsheets
			case 'MP':    // shows up on some spreadsheets where the character uses magic
			case 'Race':
				break;
			case 'MU':
			case 'Magic User':
			case '"Magic User"':
				$class = 'MagicUser';
				break;
			default:
				$class = $string;
		}
		if ( $class ) {
			$this->classes[] = $class;
		}
		return $class;
	}

	protected function determine_experience() {
#print_r($this->time_line);
		if ( $this->time_line ) {
			ksort( $this->time_line );
			$last = array_pop( $this->time_line );
			$this->character->add_experience( $last[1] );
		}
	}


	/**  Parse functions  **/

	protected function parse_line( $line ) {
		if ( $this->import_task === 'import' ) {
			$this->parse_line_import( $line );
		} else if ( $this->import_task === 'weapons' ) {
			$this->parse_line_weapons( $line );
		} else {
			$this->parse_line_spells( $line );
		}
		$this->track_experience( $line );
	}

	protected function parse_line_import( $line ) {
		switch( $line[0] ) {
			case 'HP':
#				$this->data['current_hp'] = $line[1];
				break;
			case 'AC':
#				$index = array_search( 'XP', $line );
#				$this->data['experience'] = $line[ ++$index ];
				break;
			case 'Str':
				$this->data['stats'] = array();
				$this->data['stats']['str'] = $line[1];
				break;
			case 'Int':
				$this->data['stats']['int'] = $line[1];
				break;
			case 'Wis':
				$this->data['stats']['wis'] = $line[1];
				break;
			case 'Dex':
				$this->data['stats']['dex'] = $line[1];
				break;
			case 'Con':
				$this->data['stats']['con'] = $line[1];
				break;
			case 'Chr':
				$this->data['stats']['chr'] = $line[1];
				break;
			case 'Armor':
				$this->data['armor'] = array();
				$armor = $this->parse_name( $line[1] );
				$this->data['armor']['armor'] = ( $armor === '(armor)' ) ? 'none' : $armor;
				if ( ! array_key_exists( 2, $line ) ) break;
				$test = intval( $line[2] );
				if ( $test > 0 ) {
					$this->data['armor']['bonus'] = $test;
					$index = 3;
				} else {
					$index = 2;
				}
				if ( $line[ $index ] === '(shield)' ) {
				} else {
					$this->data['shield'] = array();
					$this->data['shield']['type'] = $line[ $index ];
					$test = intval( $line[ ++$index ] );
					if ( $test < 6 ) {
						$this->shield['bonus'] = $test;
					}
				}
				break;
			case 'Needed':
				break;
			case 'WP':
				break;
			default:
				if ( in_array( 'Race', $line ) ) {
					$index = array_search( 'Race', $line );
					$this->data['name'] = $this->parse_name( $line[0] );
					$this->data['race'] = $line[ ++$index ];
				} else if ( in_array( 'Base', $line ) && in_array( 'Base', [ $line[3], $line[4] ] ) ) {
					$this->import_task = 'weapons';
					$this->data['weapons'] = array();
				}
		}
	}

	protected function parse_line_weapons( $line ) {
		if ( $line[0] === 'Non-Proficiency' ) {
			$this->import_task = 'spells';
			$this->data['spell_import'] = array();
			return;
		}
		$bonus = 0;
		$index = 0;
		if ( intval( $line[0] ) > 0 ) {
			$bonus = intval( $line[0] );
			$index = 1;
		}
		$weapon = '';
		if ( strpos( $line[ $index ], '"' ) === 0 ) {
			if ( substr( $line[ $index + 1 ], -1 ) === '"' ) {
				$weapon = substr( $line[ $index ], 1 ) . ',' . substr( $line[ $index + 1 ], 0, -1 );
			}
		} else {
			$weapon = $line[ $index ];
		}
		if ( $weapon ) $this->set_weapon_skill( $weapon, $line, $bonus );
	}

	protected function parse_line_spells( $line ) {
		$skip1 = array( '"First Lvl"', '"C: First Lvl"', '"C: Sixth Lvl"', '"MU: Cantrips"', '"MU: Fifth Lvl"', '"First Level"', 'Cantrips', '"D: First"', '"C:First Lvl"' );
		if ( in_array( $line[0], $skip1 ) ) {
			$this->in_spells = true;
			return;
		}
		if ( ! $this->in_spells ) return;
		$skip2 = array_merge(
			array( 'Spc', 0.1, '%' ),
			array( 'Pick Pockets', 'Open Locks', 'Find Traps', 'Move Silently', 'Hide Shadow', 'Hear Noise', 'Climb Walls', 'Languages' )
		);
		foreach( $line as $item ) {
			$name = $this->parse_name( $item );
			if ( intval( $name, 10 ) > 0 )         continue;
			if ( in_array( $name, $skip2 ) )       continue;
			if ( substr( $name, 0, 2 ) === 'UA' )  continue;
			if ( substr( $name, 0, 3 ) === ' PH' ) continue;
			if ( ( $pos = strpos( $name, ' PH' ) ) > 0 ) { $name = substr( $name, 0, $pos ); }
			if ( ( $pos = strpos( $name, ' UA' ) ) > 0 ) { $name = substr( $name, 0, $pos ); }
			$this->data['spell_import'][] = $name;
		}
	}

	private function parse_name( $name ) {
		if ( substr( $name, 0, 1 ) === '"' ) {
			$name = substr( $name, 1 );
			$name = substr( $name, 0, -1 );
		}
		return $name;
	}

	private function set_weapon_skill( $weapon, $line, $bonus ) {
		$info = array( 'bonus' => $bonus, 'skill' => 'NP' );
		if ( in_array( '*', $line, true ) ) {
			$keys = array_keys( $line, '*', true );
			switch( count( $keys ) ) {
				case 1:
					$info['skill'] = 'PF';
					break;
				case 2:
					$info['skill'] = 'SP';
					break;
				case 3:
					$info['skill'] = 'DS';
					break;
				default:
			}
		} else if ( $weapon === 'Spell' ) {
			$info['skill'] = 'PF';
		}
		$this->data['weapons'][ $weapon ] = $info;
	}

	protected function track_experience( $line ) {
		$mul = count( $this->classes );
		$cnt = count( $line );
#print_r($line);
		foreach( $line as $k => $item ) {
			if ( strpos( $item, '/' ) === false ) continue;
			$check = intval( $item, 10 );
			if ( ! $check ) continue;
			$check = strtotime( $item );
			if ( $check ) {
				$date = new DateTime( $item );
				$year = $date->format('Y');
#echo "$check $year\n";
			if ( ( $year > 2010 ) && ( $year < 2025 ) ) {
				if ( array_key_exists( $k + 1, $line ) ) {
					$this->time_line["$item"] = array( $line[ $k + 1 ] );
					if ( $k + 2 < $cnt ) $this->time_line["$item"][] = $line[ $k + 2 ];
#					if ( ( $mul > 1 ) && ( $k + 3 < $cnt ) ) $this->time_line["$item"][] = $line[ $k + 3 ];
				}
			}
			}
		}
#print_r($this->time_line);
	}


}
