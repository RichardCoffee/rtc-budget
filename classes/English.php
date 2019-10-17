<?php

/*
 *  classes/ordinal.php
 *
 */

require_once('Enum.php');

class English extends Enum {


	protected $set = array();


	use DND_Trait_Singleton;


	protected function __construct( $args = array() ) {
		$this->set = array( 'Zero',
			'One',           'Two',          'Three',        'Four',        'Five',
			'Six',           'Seven',        'Eight',        'Nine',        'Ten',
			'Eleven',        'Twelve',       'Thirteen',     'Fourteen',    'Fifteen',
			'Sixteen',       'Seventeen',    'Eighteen',     'Nineteen',    'Twenty',
			'Twenty-One',    'Twenty-Two',   'Twenty-Three', 'Twenty-Four', 'Twenty-Five',
			'Twenty-Six',    'Twenty-Seven', 'Twenty-Eight', 'Twenty-Nine', 'Thirty',
		);
		if ( $args ) $this->set[0] = (array)$args[0];
	}

}