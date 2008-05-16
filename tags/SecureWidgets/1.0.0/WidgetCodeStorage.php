<?php
/**
 * @package SecureWidgets
 * @category Widgets
 * @author Jean-Lou Dupont
 * @version 1.0.0 
 * @Id $Id$
 */

abstract class MW_WidgetCodeStorage 
	extends ExtensionBaseClass {

	const VERSION = '1.0.0';
	const NAME    = 'securewidgets';
		
	/**
	 * Widget Name
	 */
	var $name = null;

	/**
	 * Constructor
	 */
	public function __construct( ) {

		parent::__construct();
	}
	
	public function setName( &$name ) {
	
		$this->name = $name;	
	}
	/**
	 * Retrieve the code from the storage
	 */
	abstract public function getCode();

}