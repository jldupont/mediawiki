<?php
/**
 * @author Jean-Lou Dupont
 * @package AutoRedirect
 * @version @@package-version@@
 * @Id $Id$
 */
if (class_exists('StubManager'))
{
	$wgExtensionCredits['other'][] = array( 
		'name'    => 'AutoRedirect',
		'version' => '@@package-version@@',
		'author'  => 'Jean-Lou Dupont',
		'description' => "Provides a magic word to automatically create redirect pages", 
		'url'		=> 'http://mediawiki.org/wiki/Extension:AutoRedirect',
	);
	
	StubManager::createStub2(	array(	'class' 		=> 'AutoRedirect', 
										'classfilename'	=> dirname(__FILE__).'/AutoRedirect.body.php',
										'mgs' 			=> array( 'autoredirect' ),
									)
							);
}
else
	echo 'Extension:AutoRedirect requires Extension:StubManager';						
//</source>
