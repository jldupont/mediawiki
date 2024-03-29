<?php
/**
 * @author Jean-Lou Dupont
 * @package ParserExt
 * @subpackage PermissionFunctions
 * @version @@package-version@@
 * @Id $Id$
 */
//<source lang=php>
class PermissionFunctions
{
	// constants.
	const thisName = 'PermissionFunctions';
	const thisType = 'other';
	
	var $permissionErrorFound;
		
	function __construct( ) 
	{
		$this->permissionErrorFound = false;
	}

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// Functions which are meant to be accessed through 'ParserPhase2' functionality

	/**
	 * Meant to be user through [[Extension:ParserPhase2]]
	 *  (($#checkpermission|required right$))
	 */
	public function mg_checkpermission( &$parser, $requiredRight = 'read' )
	// (($ #checkpermission|required right $))
	// redirects to the standard 'Permission Error' page if the user lacks the $requiredRight
	{
		global $wgUser;
		global $wgTitle;
		global $wgOut;
		
		$ns = $wgTitle->getNamespace();
		
		if (!$wgUser->isAllowed( $requiredRight, $ns ) )
		{
			$this->permissionErrorFound = true;			
			// set a 'context' variable to help other extensions.
			wfRunHooks('PageVarSet', array( 'PermissionError', &$this->permissionErrorFound ) );			
			$wgOut->clearHTML();
			$wgOut->permissionRequired( $requiredRight );
		}
	}
	/**
	 *
	 */
	public function hEndParserPhase2( &$op, &$text )
	{
		if ($this->permissionErrorFound)
			$text = null;
		return true;
	}
	/** FIXME
	 * This parser function is meant to be used in conjuction 
	 * with 'Hierarchical Namespace Permission' extension.
	 * E.g. (($#foreachx|bwPermissionFunctions|getpermissionline| ... $))
	 */	
	public static function getpermissionline( $group, $namespace )
	{
		if (!class_exists('HNP'))
			return "<b>PermissionFunctions:</b> ".wfMsg('error')." <i>Hierarchical Namespace Permission Extension</i>";		
		return HNP::getPermissionGroupNamespace( $group, $namespace );
	}
	public static function usercan( &$user, &$ns, &$pt, &$action )
	{
		if (!class_exists('HNP')) 
			return "<b>PermissionFunctions:</b> ".wfMsg('error')." <i>[[Extension:HNP]] missing</i>";
		
		if ( !is_object( $user ) )
		{
			global $wgUser;
			$user = &$wgUser;
		}
		return HNP::userCanInternal( $user, $ns, $pt, $action );
	}
} // end class.
//</source>