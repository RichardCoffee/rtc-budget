<?php

function get_horse( $type ) {
	$horses = array(
		'draft' => array(
			'type'  => 'Draft horse',
			'class' => 7,
			'move'  => 12,
			'dice'  => [ 3, 8, 0 ],
			'atts'  => [ [ 1, 3, 0 ] ],
			'cost'  => 30,
		),
		'heavy' => array(
			'type'  => 'Heavy warhorse',
			'class' => 7,
			'move'  => 15,
			'dice'  => [ 3, 8, 3 ],
			'atts'  => [ [ 1, 8, 0 ], [ 1, 8, 0 ], [ 1, 3, 0 ] ],
			'cost'  => 300,
		),
		'light' => array(
			'type'  => 'Light warhorse',
			'class' => 7,
			'move'  => 24,
			'dice'  => [ 2, 8, 0 ],
			'atts'  => [ [ 1, 4, 0 ], [ 1, 4, 0 ] ],
			'cost'  => 150,
		),
		'medium' => array(
			'type'  => 'Medium warhorse',
			'class' => 7,
			'move'  => 18,
			'dice'  => [ 2, 8, 2 ],
			'atts'  => [ [ 1, 6, 0 ], [ 1, 6, 0 ], [ 1, 3, 0 ] ],
			'cost'  => 225,
		),
		'paladin' => array(
			'name'  => "Paladin's warhorse",
			'type'  => 'Heavy warhorse',
			'class' => 5,
			'move'  => 18,
			'dice'  => [ 5, 8, 5 ],
			'atts'  => [ [ 1, 8, 0 ], [ 1, 8, 0 ], [ 1, 3, 0 ] ],
			'cost'  => 'N/A',
		),
		'pony' => array(
			'type'  => 'Pony',
			'class' => 7,
			'move'  => 12,
			'dice'  => [ 1, 8, 1 ],
			'atts'  => [ [ 1, 2, 0 ] ],
			'cost'  => 15,
		),
		'riding' => array(
			'type'  => 'Riding',
			'class' => 7,
			'move'  => 24,
			'dice'  => [ 2, 8, 0 ],
			'atts'  => [ [ 1, 3, 0 ] ],
			'cost'  => 25,
		),
		'wild' => array(
			'type'  => 'Wild horse',
			'class' => 7,
			'move'  => 24,
			'dice'  => [ 2, 8, 0 ],
			'atts'  => [ [ 1, 3, 0 ] ],
			'cost'  => 'nil',
		),
	);
	if ( array_key_exists( $type ) ) {
		return $horses[ $type ];
	}
	return $horses;
}
