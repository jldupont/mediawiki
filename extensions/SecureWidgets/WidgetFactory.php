<?php
/**
 * @package SecureWidgets
 * @category Widgets
 * @author Jean-Lou Dupont
 * @version @@package-version@@ 
 * @Id $Id$
 */

class MW_WidgetFactory
	extends ExtensionBaseClass {

	/**
	 * Code store list
	 */
	var $codeStores = array();

	/**
	 * Singleton instance reference
	 */
	private static $instance = null;
	
	var $fetchedOtherStores = false;
	
	/**
	 * Constructor
	 */
	public function __construct() {
	
		if ( self::$instance !== null )
			throw new Exception( __CLASS__. ": there can only be one instance of this class" );
			
		self::$instance = $this;

		parent::__construct();		
		
		$this->registerDefaultStorages();
	}
	
	public function setup() {
	}
	
	/****************************************************************
	 *							PUBLIC 
	 ****************************************************************/
	
	/**
	 * Returns the singleton instance of this class
	 * 
	 * @return Object
	 */
	public static function gs() {
	
		return self::$instance;
	}
	/**
	 * Go through all registered code store
	 * 
	 * @param $name string
	 * @return $obj mixed Widget / MW_SecureWidgetsMessageList
	 */
	public function newFromWidgetName( &$name ) {
	
		$this->fetchOtherStores();
	
		$msgs = new MessageList;
	
		foreach( $this->codeStore as $store ) {
		
			$store->setName( $name );
			$code = $store->getCode();
			if ( $code !== null )
				return new Widget( $name, $code );
			
			$msgs->pushMessages( $store->getLastErrorMessages() );
		}
	
		// error
		return $msgs;
	
	}
	/****************************************************************
	 * 							PROTECTED
	 ****************************************************************/
	
	/**
	 * Uses a 'hook' to look around if other extensions are capable
	 * of providing 'storage' capabilities. 
	 */
	protected function fetchOtherStores() {
	
		// just do this once
		if ( $this->fetchedOtherStores )
			return;
		$this->fetchedOtherStores = true;
		
		wfRunHooks( 'widget_register_storage', array( &$this->codeStore ) );
	}
	/**
	 * Must be placed in order of priority with
	 * regards to searching locations.
	 */
	protected function registerDefaultStorages( ) {
	
		$this->codeStore[] = MW_WidgetCodeStorage_Database::gs();
		$this->codeStore[] = MW_WidgetCodeStorage_Repository::gs();		
		return $this;
	
	}

} //Widget: end class definition

new MW_WidgetFactory;