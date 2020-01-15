<?php

trait DND_Combat_GetOpts {


	protected $opts = array();


	protected function get_opts() {
		$this->opts = getopt( 'hr:st::x', [ 'add:', 'att:', 'crit:', 'desc::', 'fumble:', 'enc:', 'help', 'hit:', 'hold:', 'mi:', 'pre:', 'st:' ] );
		$this->process_immediate_opts();
	}

	protected function process_immediate_opts() {
		if ( $this->opts ) {
			foreach( $this->opts as $key => $option ) {
				switch( $key ) {
					case 'h':
					case 'help':
						$this->show_help();
						exit;
					default:
				}
			}
		}
	}

	protected function process_opts() {
		if ( $this->opts ) {
			foreach( $this->opts as $key => $option ) {
				switch( $key ) {
					case 'r':
						$this->range = intval( $this->opts['r'], 10 );
						break;
					case 's':
						$this->update_holds();
						break;
					case 't':
						$t = new DND_Treasure;
						$t->show_possible_monster_treasure( $this->enemy, $this->opts['t'] );
						exit;
					case 'x':
						$this->show_experience_value();
						exit;
					case 'add':
						$this->add_to_party( $this->opts['add'] );
						break;
					case 'att':
						$this->remove_holding( $this->opts['att'] );
						break;
					case 'crit':
						$this->critical_hit( $this->opts['crit'] );
						break;
					case 'desc':
						$this->change_shown_enemy( $this->opts['desc'] );
						break;
					case 'fumble':
						$this->fumble_roll_result( $this->opts['fumble'] );
						break;
					case 'enc':
						$this->generate_encounter( $this->opts['enc'] );
						break;
					case 'hit':
						$this->record_damage();
						break;
					case 'hold':
						$this->add_holding( $this->opts['hold'] );
						break;
					case 'mi':
						$this->monster_initiative();
						break;
					case 'pre':
						$this->pre_cast_spell();
						break;
					case 'st':
						$this->show_saving_throws( $this->opts['st'] );
						break;
					default:
				}
			}
		}
	}

	protected function show_help() {
		echo "

	php command_line.php [OPTIONS] [NAME [WEAPON|SPELL#]]


	-h, --help      Display this help screen.

	-r n            Control missile weapon range, where n = range in feet.

	-s              Increment the combat segment.

	-t              Show possible monster treasure, if any.

	-x              Show monster experience point value.

	--add=name      Add a character to the party.  The csv file must exist.  Will overwrite a character already in the party.

	--att=name      Remove a character from the hold list, because the character is attacking on this segment.

	--crit=#[:b|p]  Display the possible result of a critical hit, where # is the number rolled on percentile dice.
	                Second parameter of 'b' or 'p' can be added to indicate blunt or piercing damage, otherwise defaults to slashing damage.

	--desc=#        Display the description information for the selected enemy.

	--fumble=#      Display the possible result of a fumble roll, where # is the number rolled on percentile dice.

	--enc=<terrain>:<area>  Possibly generate a random encounter where terrain can be 'CC','CW','TC','TW','TSC','TSW' and area can be 'M','H','F','S','P','D'
	                        For water encounters terrain can be 'CF','CS','TF','TS','TSF','TSS' and area can be 'S','D'

	--hit=name:#    Use to record damage to a combatant, format is <name>:<damage>.  Use a negative number to indicate healing.

	--hold=name[:#] Place a combatant's attack on hold.  Adding a segment value indicates that the combatant can attack on the specified segment.

	--mi=number     Set the monster's initiative.

	--pre=name:#    Use this when a character casts a spell before combat, where '#' indicates the spell's number, from the numbered spell list.

	--st=name       Show the saving throws for the indicated combatant.

";
	}

	protected function critical_hit( $param ) {
		$info = explode( ':', $param );
		$roll = $info[0];
		$type = ( array_key_exists( 1, $info ) ) ? $info[1] : 's';
		$type = ( in_array( $type, [ 'b', 'p', 's' ] ) ) ? $type : 's';
		$this->critical_hit_result( $roll, $type );
	}

	protected function record_damage() {
		$sitrep = explode( ':', $this->opts['hit'] );
		$name   = $sitrep[0];
		$damage = $sitrep[1];
		$this->object_damage( $name, $damage );
	}

	protected function monster_initiative() {
		$sitrep = explode( ':', $this->opts['mi'] );
		if ( count( $sitrep ) === 1 ) {
			$init = intval( $sitrep[0], 10 );
			$this->set_monster_initiative_all( $init );
		} else if ( count( $sitrep ) === 2 ) {
			$num  = intval( $sitrep[0], 10 );
			$init = intval( $sitrep[1], 10 );
			$obj  = $this->get_specific_enemy( $num );
			$obj->set_initiative( $init );
		}
	}

	protected function pre_cast_spell( $unused1 = '', $unused2 = '', $unused3 = '' ) {
		if ( $this->segment < 2 ) {
			$sitrep = explode( ':', $this->opts['pre'] );
			$name   = $sitrep[0];
			if ( array_key_exists( $name, $this->party ) ) {
				$number = intval( $sitrep[1], 10 );
				if ( $number ) {
					$spell = $this->get_numbered_spell( $this->party[ $name ], $number );
					if ( $spell ) {
						$target = ( array_key_exists( 2, $sitrep ) ) ? $sitrep[2] : $name;
						$result = $this->start_casting( $name, $spell, $target );
						if ( $result ) {
							$cast = $this->find_casting( $name );
							$this->finish_casting( $cast );
							$this->remove_casting( $name );
							echo "\n{$cast['caster']} has cast {$cast['name']} on {$cast['target']}\n\n";
#							exit;
						}
					}
				}
			}
		}
	}

	public function process_arguments( $argv ) {
		if ( $argv && ! $this->opts ) {
			$count = count( $argv );
			if ( $count > 1 ) {
				$name = $argv[1];
				if ( array_key_exists( $name, $this->party ) ) {
					if ( $count > 2 ) {
						if ( intval( $argv[2] ) ) {
							$spell = $this->get_numbered_spell( $this->party[ $name ], $argv[2] );
							if ( $spell ) $this->gopa_start_casting( $name, $spell, $argv );
						} else if ( method_exists( $this->party[ $name ], 'locate_magic_spell' ) && ( $spell = $this->party[ $name ]->locate_magic_spell( $argv[2] ) ) ) {
							$this->gopa_start_casting( $name, $spell, $argv );
						} else {
							if ( ( $count === 4 ) && method_exists( $this->party[ $name ], 'set_dual_weapons' ) ) {
								$this->party[ $name ]->set_dual_weapons( $argv[2], $argv[3] );
							}
							$this->change_weapon( $this->party[ $name ], $argv[2] );
						}
					} else if ( $this->party[ $name ]->weapon['current'] === 'Spell' ) {
						$this->show_possible_spells( $this->party[ $name ] );
						$this->show_possible_weapons( $this->party[ $name ] );
					} else {
						$this->show_possible_weapons( $this->party[ $name ] );
					}
				}
			}
		}
	}

	protected function gopa_start_casting( $name, $spell, $argv ) {
		echo "\n{$argv[1]}\n";
		$target = ( array_key_exists( 3, $argv ) ) ? $argv[3] : $name;
		$this->start_casting( $name, $spell, $target );
	}


}